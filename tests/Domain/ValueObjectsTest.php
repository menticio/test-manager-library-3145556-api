<?php

// T-03 evidence: ISBN of 13 digits, quantity > 0, end >= start.

use App\Domain\Errors\DomainError;
use App\Domain\ValueObjects\DateRange;
use App\Domain\ValueObjects\Isbn;
use App\Domain\ValueObjects\Quantity;

it('accepts an ISBN of exactly 13 digits', function () {
    expect((new Isbn('9780307474728'))->value)->toBe('9780307474728');
});

it('rejects an ISBN that is not 13 digits', function (string $isbn) {
    expect(fn () => new Isbn($isbn))->toThrow(DomainError::class, 'El ISBN debe tener exactamente 13 dígitos.');
})->with(['123', '97803074747281', '978030747472X', '978-030747472', ' 9780307474728', '']);

it('uses VALIDATION_ERROR for an invalid ISBN', function () {
    try {
        new Isbn('abc');
    } catch (DomainError $e) {
        expect($e->errorCode)->toBe('VALIDATION_ERROR')->and($e->details[0]['field'])->toBe('isbn');
    }
});

it('accepts a quantity of at least 1', function () {
    expect((new Quantity(1))->value)->toBe(1);
});

it('rejects a quantity of 0 or less', function (int $value) {
    expect(fn () => new Quantity($value))->toThrow(DomainError::class);
})->with([0, -1]);

it('accepts a range whose end equals or follows its start', function () {
    $day = new DateTimeImmutable('2026-10-01T00:00:00Z');
    expect(new DateRange($day, $day))->toBeInstanceOf(DateRange::class)
        ->and(new DateRange($day, $day->modify('+30 days')))->toBeInstanceOf(DateRange::class);
});

it('rejects a range whose end is before its start with INVALID_DATE_RANGE', function () {
    try {
        new DateRange(new DateTimeImmutable('2026-10-31'), new DateTimeImmutable('2026-10-01'));
        $this->fail('expected INVALID_DATE_RANGE');
    } catch (DomainError $e) {
        expect($e->errorCode)->toBe('INVALID_DATE_RANGE');
    }
});
