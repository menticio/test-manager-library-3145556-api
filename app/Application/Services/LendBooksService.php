<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\CategoryRepository;
use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\ConcurrencyConflict;
use App\Application\Ports\Outbound\LoanRepository;
use App\Application\Ports\Outbound\MemberRepository;
use App\Domain\Errors\DomainError;
use App\Domain\Id;
use App\Domain\Loan;
use App\Domain\LoanItem;

/** HU-06: register a loan (POST /loans). Lends and does nothing else (SRP). */
final readonly class LendBooksService
{
    private const MAX_UNRETURNED_PER_MEMBER = 3; // RN-2

    public function __construct(
        private MemberRepository $members,
        private BookRepository $books,
        private CategoryRepository $categories,
        private LoanRepository $loans,
        private Clock $clock,
    ) {}

    /** @param list<string> $bookIds one per requested line, in request order */
    public function lend(string $memberId, string $loanedByUserId, array $bookIds): Loan
    {
        if ($this->members->find($memberId) === null) {
            throw new DomainError('NOT_FOUND', 'El socio no existe.', [['field' => 'member_id', 'issue' => 'no existe']]);
        }

        // ADR-002: optimistic concurrency, one retry if the copy is still available.
        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->attempt($memberId, $loanedByUserId, $bookIds);
            } catch (ConcurrencyConflict) {
                if ($attempt === 2) {
                    throw new DomainError('BOOK_NOT_AVAILABLE', 'Otro préstamo tomó el último ejemplar. Intente de nuevo.');
                }
            }
        }
    }

    /** @param list<string> $bookIds */
    private function attempt(string $memberId, string $loanedByUserId, array $bookIds): Loan
    {
        $now = $this->clock->now();
        $unreturned = $this->loans->unreturnedItemsOfMember($memberId);

        // CA-06.5 / RN-3: a member with an overdue book cannot borrow (409).
        if (array_filter($unreturned, fn (LoanItem $item) => $item->isOverdue($now)) !== []) {
            throw new DomainError('MEMBER_HAS_OVERDUE', 'El socio tiene libros vencidos y debe devolverlos antes de pedir otro.');
        }

        // CA-06.4 / RN-2: at most 3 books not returned in total (422 with the current count).
        $current = count($unreturned);
        if ($current + count($bookIds) > self::MAX_UNRETURNED_PER_MEMBER) {
            throw new DomainError('MEMBER_LIMIT_EXCEEDED',
                "El socio tiene {$current} libros sin devolver; el máximo es 3.",
                [['field' => 'items', 'issue' => "{$current} sin devolver"]]);
        }

        // Q3: books by batch. Unknown or retired -> 404.
        $found = $this->books->findActiveByIds(array_values(array_unique($bookIds)));
        $lines = [];
        foreach ($bookIds as $i => $bookId) {
            $book = $found[$bookId] ?? throw new DomainError('NOT_FOUND', 'El libro no existe.',
                [['field' => "items[{$i}].book_id", 'issue' => 'no existe']]);
            $lines[] = ['book' => $book, 'category_name' => (string) $this->categories->nameOf($book->categoryId)];
        }

        // Domain: 1 to 3 lines, no repeated book, one copy withdrawn per line, due at +14 days, frozen values.
        [$loan, $books] = Loan::lend(Id::new(), $memberId, $loanedByUserId, $now, $lines);

        $this->loans->addLoan($loan, $books);

        return $loan;
    }
}
