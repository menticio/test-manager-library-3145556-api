<?php

namespace App\Adapters\Outbound\Clock;

use App\Application\Ports\Outbound\Clock;
use DateTimeImmutable;
use DateTimeZone;

/**
 * D-05: the real clock, always UTC (RN-6).
 * CLOCK_OFFSET_DAYS (default 0) shifts it by whole days so the demo seeder (T-21) can create overdue loans
 * through the API, without SQL and without waiting 14 days (plan.md §4). The domain does not change.
 */
final class SystemClock implements Clock
{
    public function __construct(private readonly int $offsetDays = 0) {}

    public function now(): DateTimeImmutable
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $this->offsetDays === 0 ? $now : $now->modify(sprintf('%+d days', $this->offsetDays));
    }
}
