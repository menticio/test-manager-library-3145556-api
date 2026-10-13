<?php

namespace Tests\Fakes;

use App\Application\Ports\Outbound\ConcurrencyConflict;
use App\Application\Ports\Outbound\LoanRepository;
use App\Application\Ports\Outbound\OverdueReader;
use App\Application\Ports\Outbound\ReportReader;
use App\Domain\Book;
use App\Domain\Loan;
use App\Domain\ValueObjects\DateRange;
use DateTimeImmutable;

final class InMemoryLoanRepository implements LoanRepository, OverdueReader, ReportReader
{
    /** @var array<string, Loan> */
    public array $loans = [];

    /** Number of upcoming addLoan calls that must fail with a version conflict. */
    public int $conflictsToSimulate = 0;

    public function __construct(
        private ?InMemoryBookRepository $books = null,
        private ?InMemoryMemberRepository $members = null,
    ) {}

    public function countUnreturnedForBook(string $bookId): int
    {
        $count = 0;
        foreach ($this->loans as $loan) {
            foreach ($loan->items as $item) {
                $count += ($item->bookId === $bookId && ! $item->isReturned()) ? 1 : 0;
            }
        }

        return $count;
    }

    public function unreturnedItemsOfMember(string $memberId): array
    {
        $items = [];
        foreach ($this->loans as $loan) {
            if ($loan->memberId !== $memberId) {
                continue;
            }
            foreach ($loan->items as $item) {
                if (! $item->isReturned()) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    public function addLoan(Loan $loan, array $books): void
    {
        if ($this->conflictsToSimulate > 0) {
            $this->conflictsToSimulate--;
            throw new ConcurrencyConflict;
        }
        foreach ($books as $book) {
            $this->books?->save($book);
        }
        $this->loans[$loan->id] = $loan;
    }

    public function find(string $id): ?Loan
    {
        return $this->loans[$id] ?? null;
    }

    public function saveReturn(Loan $loan, string $itemId, Book $book): void
    {
        if ($this->loans[$loan->id]->item($itemId)?->isReturned()) {
            throw new ConcurrencyConflict;
        }
        $this->books?->save($book);
        $this->loans[$loan->id] = $loan;
    }

    /** Same rule as the real query: grouped by the FROZEN values of each line, never by the live catalog. */
    public function mostBorrowed(DateRange $range): array
    {
        $end = $range->to->modify('+1 day');
        $rows = [];
        foreach ($this->loans as $loan) {
            if ($loan->loanedAt < $range->from || $loan->loanedAt >= $end) {
                continue;
            }
            foreach ($loan->items as $item) {
                $key = $item->bookId."\0".$item->bookTitle."\0".$item->categoryName;
                $rows[$key] ??= ['book_id' => $item->bookId, 'book_title' => $item->bookTitle, 'category_name' => $item->categoryName, 'times_borrowed' => 0];
                $rows[$key]['times_borrowed']++;
            }
        }
        $rows = array_values($rows);
        usort($rows, fn ($a, $b) => [$b['times_borrowed'], $a['book_title']] <=> [$a['times_borrowed'], $b['book_title']]);

        return $rows;
    }

    public function unreturnedDueBefore(DateTimeImmutable $now): array
    {
        $rows = [];
        foreach ($this->loans as $loan) {
            foreach ($loan->items as $item) {
                if ($item->isOverdue($now)) {
                    $rows[] = ['loan_id' => $loan->id, 'item_id' => $item->id, 'book_title' => $item->bookTitle,
                        'member_name' => $this->members?->find($loan->memberId)?->fullName ?? '', 'due_at' => $item->dueAt];
                }
            }
        }

        return $rows;
    }
}
