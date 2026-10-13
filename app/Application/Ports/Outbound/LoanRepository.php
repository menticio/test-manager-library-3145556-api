<?php

namespace App\Application\Ports\Outbound;

use App\Application\Page;
use App\Domain\Book;
use App\Domain\Loan;
use App\Domain\LoanItem;
use App\Domain\ValueObjects\DateRange;
use DateTimeImmutable;

/** Port: loans. Loans and returns are never deleted (RN-5): there is no delete. */
interface LoanRepository
{
    /** CA-03.5: lines of this book not yet returned. */
    public function countUnreturnedForBook(string $bookId): int;

    /** Q7: lines of a member not yet returned (count and overdue check, CA-06.4, CA-06.5). @return list<LoanItem> */
    public function unreturnedItemsOfMember(string $memberId): array;

    /**
     * Stores the loan, its lines and the withdrawn books in ONE transaction (architecture.md §4).
     * Each book is written only if its copies did not change since it was read (ADR-002); otherwise nothing is stored.
     *
     * @param  list<Book>  $books  books returned by Loan::lend, with their copies already withdrawn
     *
     * @throws ConcurrencyConflict
     */
    public function addLoan(Loan $loan, array $books): void;

    /** Q5: loan with its lines. */
    public function find(string $id): ?Loan;

    /**
     * Stores returned_at of one line and the restocked book in ONE transaction.
     * The line is written only while returned_at is still null, so a line never adds a copy twice (CA-07.2).
     *
     * @throws ConcurrencyConflict
     */
    public function saveReturn(Loan $loan, string $itemId, Book $book): void;

    /**
     * Q6: loans by loaned_at range (both days inclusive), computed status and member, newest first (CA-08.2).
     * The status filter is evaluated against $now because the status is not stored (CA-08.3).
     *
     * @return Page<Loan>
     */
    public function search(?DateRange $range, ?string $status, ?string $memberId, DateTimeImmutable $now, int $page, int $size): Page;
}
