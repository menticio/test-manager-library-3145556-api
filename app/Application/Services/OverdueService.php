<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\OverdueReader;

/** HU-09: overdue lines with book, member and days late, most days late first (GET /loans/overdue). */
final readonly class OverdueService
{
    public function __construct(
        private OverdueReader $reader,
        private Clock $clock,
    ) {}

    /** @return list<array{loan_id: string, item_id: string, book_title: string, member_name: string, due_at: \DateTimeImmutable, days_late: int}> */
    public function list(): array
    {
        $now = $this->clock->now();
        $rows = array_map(
            fn (array $row) => $row + ['days_late' => $row['due_at']->diff($now)->days],
            $this->reader->unreturnedDueBefore($now),
        );

        // CA-09.2: most days late first; days late are computed, never stored (Article VII).
        usort($rows, fn (array $a, array $b) => $a['due_at'] <=> $b['due_at']);

        return $rows;
    }
}
