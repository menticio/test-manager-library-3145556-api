<?php

namespace App\Adapters\Outbound\Persistence;

use App\Application\Ports\Outbound\ReportReader;
use App\Domain\ValueObjects\DateRange;
use Illuminate\Support\Facades\DB;

/**
 * Q9 / ADR-004: one aggregated query in the engine, never stored.
 * Groups by the FROZEN loan_item.book_id, book_title and category_name; it never joins book or category.
 * Selects no member and no librarian column (CA-10.3).
 */
final class PgReportReader implements ReportReader
{
    public function mostBorrowed(DateRange $range): array
    {
        return DB::table('loan_item')
            ->join('loan', 'loan.id', '=', 'loan_item.loan_id')
            ->where('loan.loaned_at', '>=', Pg::toTimestamp($range->from))
            ->where('loan.loaned_at', '<', Pg::toTimestamp($range->to->modify('+1 day')))
            ->groupBy('loan_item.book_id', 'loan_item.book_title', 'loan_item.category_name')
            ->orderByDesc('times_borrowed')
            ->orderBy('loan_item.book_title')
            ->get(['loan_item.book_id', 'loan_item.book_title', 'loan_item.category_name', DB::raw('count(*) as times_borrowed')])
            ->map(fn ($row) => [
                'book_id' => $row->book_id,
                'book_title' => $row->book_title,
                'category_name' => $row->category_name,
                'times_borrowed' => (int) $row->times_borrowed,
            ])->all();
    }
}
