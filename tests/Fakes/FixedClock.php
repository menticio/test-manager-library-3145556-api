<?php

namespace Tests\Fakes;

use App\Application\Ports\Outbound\Clock;
use DateTimeImmutable;

/** Fake Clock: time only moves when the test says so. */
final class FixedClock implements Clock
{
    public function __construct(private DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}
