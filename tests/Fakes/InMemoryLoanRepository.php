<?php

namespace Tests\Fakes;

use App\Application\Ports\Outbound\LoanRepository;
use App\Domain\Loan;

final class InMemoryLoanRepository implements LoanRepository
{
    /** @var array<string, Loan> */
    public array $loans = [];

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
}
