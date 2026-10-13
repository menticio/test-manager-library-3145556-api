<?php

namespace App\Application\Services;

use App\Application\Page;
use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\LoanRepository;
use App\Domain\Errors\DomainError;
use App\Domain\Loan;
use App\Domain\ValueObjects\DateRange;
use DateTimeImmutable;
use DateTimeZone;

/** CA-08.2: list loans by date range, status and member, newest first, paginated. Status is computed (CA-08.3). */
final readonly class ListLoansService
{
    public function __construct(
        private LoanRepository $loans,
        private Clock $clock,
    ) {}

    /** @return Page items are array{loan: Loan, status: string} */
    public function list(?string $from, ?string $to, ?string $status, ?string $memberId, ?int $page, ?int $size): Page
    {
        [$page, $size] = Page::validate($page, $size);
        if ($status !== null && ! in_array($status, [Loan::OPEN, Loan::OVERDUE, Loan::CLOSED], true)) {
            throw DomainError::validation('status', 'open, overdue o closed', 'El estado debe ser open, overdue o closed.');
        }

        $range = null;
        if ($from !== null || $to !== null) {
            $range = new DateRange(self::date('from', $from ?? '0001-01-01'), self::date('to', $to ?? '9999-12-31'));
        }

        $now = $this->clock->now();
        $result = $this->loans->search($range, $status, $memberId, $now, $page, $size);

        return new Page(
            array_map(fn (Loan $loan) => ['loan' => $loan, 'status' => $loan->status($now)], $result->items),
            $result->page, $result->size, $result->total,
        );
    }

    private static function date(string $field, string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw DomainError::validation($field, 'fecha AAAA-MM-DD', "El parámetro {$field} debe ser una fecha AAAA-MM-DD.");
        }

        return $date;
    }
}
