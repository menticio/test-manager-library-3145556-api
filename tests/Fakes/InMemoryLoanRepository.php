<?php

namespace Tests\Fakes;

use App\Application\Ports\Outbound\ConcurrencyConflict;
use App\Application\Ports\Outbound\LoanRepository;
use App\Domain\Loan;

final class InMemoryLoanRepository implements LoanRepository
{
    /** @var array<string, Loan> */
    public array $loans = [];

    /** Number of upcoming addLoan calls that must fail with a version conflict. */
    public int $conflictsToSimulate = 0;

    public function __construct(private ?InMemoryBookRepository $books = null) {}

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
}
