<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\ReportReader;
use App\Domain\Errors\DomainError;
use App\Domain\ValueObjects\DateRange;
use DateTimeImmutable;
use DateTimeZone;

/** HU-10: most borrowed books in a date range (GET /reports/most-borrowed, admin only). */
final readonly class MostBorrowedReportService
{
    public function __construct(private ReportReader $reader) {}

    /** @return array{from: string, to: string, rows: list<array{book_id: string, book_title: string, category_name: string, times_borrowed: int}>} */
    public function report(?string $from, ?string $to): array
    {
        $range = new DateRange(self::date('from', $from), self::date('to', $to)); // CA-10.2: end before start -> 422

        return [
            'from' => $range->from->format('Y-m-d'),
            'to' => $range->to->format('Y-m-d'),
            'rows' => $this->reader->mostBorrowed($range), // CA-10.2: no loans -> empty list, not an error
        ];
    }

    private static function date(string $field, ?string $value): DateTimeImmutable
    {
        $date = $value === null ? false : DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw DomainError::validation($field, 'fecha obligatoria AAAA-MM-DD', "El parámetro {$field} es obligatorio y debe ser una fecha AAAA-MM-DD.");
        }

        return $date;
    }
}
