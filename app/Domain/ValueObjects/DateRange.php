<?php

namespace App\Domain\ValueObjects;

use App\Domain\Errors\DomainError;
use DateTimeImmutable;

/** Inclusive date range; end before start is INVALID_DATE_RANGE (CA-10.2). */
final readonly class DateRange
{
    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
    ) {
        if ($to < $from) {
            throw DomainError::invalidDateRange();
        }
    }
}
