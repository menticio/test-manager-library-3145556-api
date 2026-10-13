<?php

// T-04 evidence: available copies never below 0 and never above the total.

use App\Domain\Book;
use App\Domain\Errors\DomainError;
use App\Domain\Id;
use App\Domain\ValueObjects\Isbn;
use App\Domain\ValueObjects\Quantity;

function book(int $total = 2): Book
{
    return Book::create(Id::new(), ' Cien años de soledad ', 'Gabriel García Márquez', new Isbn('9780307474728'),
        '11111111-1111-4111-8111-111111111111', new Quantity($total));
}

it('starts with available copies equal to the total and a trimmed title (CA-03.1)', function () {
    $book = book(4);
    expect($book->availableCopies)->toBe(4)
        ->and($book->title)->toBe('Cien años de soledad')
        ->and($book->coverKey)->toBeNull()
        ->and($book->isActive())->toBeTrue();
});

it('withdraws one copy at a time and returns a new book', function () {
    $book = book(2);
    $after = $book->withdraw();
    expect($after->availableCopies)->toBe(1)->and($book->availableCopies)->toBe(2);
});

it('never goes below 0: withdrawing with no copies is BOOK_NOT_AVAILABLE', function () {
    $empty = book(1)->withdraw();
    try {
        $empty->withdraw();
        $this->fail('expected BOOK_NOT_AVAILABLE');
    } catch (DomainError $e) {
        expect($e->errorCode)->toBe('BOOK_NOT_AVAILABLE')
            ->and($e->getMessage())->toBe('No quedan ejemplares disponibles de «Cien años de soledad».');
    }
});

it('restocks one copy and never goes above the total', function () {
    $book = book(1)->withdraw()->restock();
    expect($book->availableCopies)->toBe(1)
        ->and(fn () => $book->restock())->toThrow(LogicException::class);
});

it('adjusts available copies by the same difference when the total changes (CA-03.3)', function () {
    $book = book(4)->withdraw()->withdraw(); // 2 lent, 2 available
    expect($book->replaceDetails('T', 'A', $book->categoryId, new Quantity(6))->availableCopies)->toBe(4)
        ->and($book->replaceDetails('T', 'A', $book->categoryId, new Quantity(2))->availableCopies)->toBe(0);
});

it('rejects a total that would leave available copies below 0 with 422 VALIDATION_ERROR', function () {
    $book = book(4)->withdraw()->withdraw()->withdraw(); // 3 lent
    try {
        $book->replaceDetails('T', 'A', $book->categoryId, new Quantity(2));
        $this->fail('expected VALIDATION_ERROR');
    } catch (DomainError $e) {
        expect($e->errorCode)->toBe('VALIDATION_ERROR')->and($e->details[0]['field'])->toBe('total_copies');
    }
});

it('rejects an empty title', function () {
    expect(fn () => Book::create(Id::new(), '   ', 'A', new Isbn('9780307474728'), 'c', new Quantity(1)))
        ->toThrow(DomainError::class);
});

it('keeps an opaque cover key or null, never an empty string (CA-03.4)', function () {
    $book = book()->withCover('covers/3f2a');
    expect($book->coverKey)->toBe('covers/3f2a')
        ->and($book->withoutCover()->coverKey)->toBeNull()
        ->and(fn () => book()->withCover(''))->toThrow(LogicException::class);
});

it('is retired logically, not deleted: it keeps its data and is no longer active (ADR-003)', function () {
    $at = new DateTimeImmutable('2026-10-13T15:00:00Z');
    $retired = book()->retire($at);
    expect($retired->isActive())->toBeFalse()
        ->and($retired->deletedAt)->toEqual($at)
        ->and($retired->title)->toBe('Cien años de soledad')
        ->and(fn () => $retired->retire($at))->toThrow(DomainError::class);
});

it('generates UUID v4 ids in the domain', function () {
    expect(Id::new())->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});
