<?php

namespace App\Application\Ports\Outbound;

use DateTimeImmutable;

/** Port: the current instant in UTC (plan.md D-05). Lets tests check due dates without waiting 14 days. */
interface Clock
{
    public function now(): DateTimeImmutable;
}
