<?php

namespace App\Adapters\Outbound\Persistence;

use App\Application\Ports\Outbound\OverdueReader;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/** Q8: uses the partial index loan_item (due_at) WHERE returned_at IS NULL. */
final class PgOverdueReader implements OverdueReader
{
    public function unreturnedDueBefore(DateTimeImmutable $now): array
    {
        return DB::table('loan_item')
            ->join('loan', 'loan.id', '=', 'loan_item.loan_id')
            ->join('member', 'member.id', '=', 'loan.member_id')
            ->whereNull('loan_item.returned_at')
            ->where('loan_item.due_at', '<', Pg::toTimestamp($now))
            ->orderBy('loan_item.due_at')
            ->orderBy('loan_item.id')
            ->get(['loan_item.loan_id', 'loan_item.id as item_id', 'loan_item.book_title', 'member.full_name as member_name', 'loan_item.due_at'])
            ->map(fn ($row) => [
                'loan_id' => $row->loan_id,
                'item_id' => $row->item_id,
                'book_title' => $row->book_title,
                'member_name' => $row->member_name,
                'due_at' => Pg::fromTimestamp($row->due_at),
            ])->all();
    }
}
