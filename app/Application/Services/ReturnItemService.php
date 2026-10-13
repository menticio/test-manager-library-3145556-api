<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\ConcurrencyConflict;
use App\Application\Ports\Outbound\LoanRepository;
use App\Domain\Errors\DomainError;
use App\Domain\LoanItem;
use LogicException;

/** HU-07: return one line (POST /loans/{loan_id}/items/{item_id}/return). */
final readonly class ReturnItemService
{
    public function __construct(
        private LoanRepository $loans,
        private BookRepository $books,
        private Clock $clock,
    ) {}

    public function return(string $loanId, string $itemId): LoanItem
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->attempt($loanId, $itemId);
            } catch (ConcurrencyConflict) {
                if ($attempt === 2) {
                    throw new DomainError('ITEM_ALREADY_RETURNED', 'La línea ya fue devuelta.');
                }
            }
        }
    }

    private function attempt(string $loanId, string $itemId): LoanItem
    {
        $loan = $this->loans->find($loanId) ?? throw new DomainError('NOT_FOUND', 'El préstamo no existe.');
        $item = $loan->item($itemId) ?? throw new DomainError('NOT_FOUND', 'La línea de préstamo no existe.');

        // A book with unreturned lines cannot be retired (CA-03.5), so it is always active here.
        $book = $this->books->findActive($item->bookId) ?? throw new LogicException('Book of an open line must be active.');

        // CA-07.1 / CA-07.2: sets returned_at and adds 1 copy, once. A second return is ITEM_ALREADY_RETURNED.
        [$loan, $book] = $loan->returnItem($itemId, $book, $this->clock->now());
        $this->loans->saveReturn($loan, $itemId, $book);

        return $loan->item($itemId);
    }
}
