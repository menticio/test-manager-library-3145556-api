<?php

namespace App\Application\Ports\Outbound;

use App\Domain\ValueObjects\DateRange;

/**
 * Read port (Q9, ADR-004): one aggregated query computed by the engine, never stored.
 * Groups by book_id, book_title and category_name AS FROZEN in loan_item; never joins the live catalog.
 * No member and no librarian in the result (CA-10.3).
 */
interface ReportReader
{
    /**
     * Loans with loaned_at from $range->from (00:00 UTC) to the end of $range->to (both days inclusive).
     *
     * @return list<array{book_id: string, book_title: string, category_name: string, times_borrowed: int}>
     *                                                                                                      ordered by times_borrowed desc, then title
     */
    public function mostBorrowed(DateRange $range): array;
}
