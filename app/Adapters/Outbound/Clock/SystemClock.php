<?php

namespace App\Adapters\Outbound\Clock;

use App\Application\Ports\Outbound\Clock;
use DateTimeImmutable;
use DateTimeZone;

/** D-05: the real clock, always UTC (RN-6). */
final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
