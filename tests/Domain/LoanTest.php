<?php

// T-05 evidence: with a fake Clock the status goes open -> overdue -> closed; due at 14 days; 1 to 3 lines.

use App\Domain\Book;
use App\Domain\Errors\DomainError;
use App\Domain\Id;
use App\Domain\Loan;
use App\Domain\ValueObjects\Isbn;
use App\Domain\ValueObjects\Quantity;
use Tests\Fakes\FixedClock;

function aBook(string $title = 'Cien años de soledad', int $copies = 2, string $isbn = '9780307474728'): Book
{
    return Book::create(Id::new(), $title, 'Autor', new Isbn($isbn), '11111111-1111-4111-8111-111111111111', new Quantity($copies));
}

function lendBooks(FixedClock $clock, Book ...$books): array
{
    return Loan::lend(Id::new(), 'member-1', 'user-1', $clock->now(),
        array_map(fn (Book $b) => ['book' => $b, 'category_name' => 'Novela'], $books));
}

beforeEach(function () {
    $this->clock = new FixedClock(new DateTimeImmutable('2026-10-14T14:15:00Z'));
});

it('sets due_at to loaned_at + 14 days (CA-06.6)', function () {
    [$loan] = lendBooks($this->clock, aBook());
    expect($loan->items[0]->dueAt->format(DATE_ATOM))->toBe('2026-10-28T14:15:00+00:00');
});

it('withdraws one copy per line in the same operation (CA-06.3)', function () {
    [, $books] = lendBooks($this->clock, aBook(copies: 2));
    expect($books[0]->availableCopies)->toBe(1);
});

it('freezes title, ISBN and category name at loan time (CA-06.6, ADR-004)', function () {
    [$loan] = lendBooks($this->clock, aBook('Título original'));
    expect($loan->items[0]->bookTitle)->toBe('Título original')
        ->and($loan->items[0]->isbn)->toBe('9780307474728')
        ->and($loan->items[0]->categoryName)->toBe('Novela');
});

it('goes open -> overdue -> closed with a fake Clock (CA-08.1)', function () {
    [$loan, [$book]] = lendBooks($this->clock, aBook());
    expect($loan->status($this->clock->now()))->toBe(Loan::OPEN);

    $this->clock->advance('+14 days');
    expect($loan->status($this->clock->now()))->toBe(Loan::OPEN); // due_at is not before now yet

    $this->clock->advance('+1 day');
    expect($loan->status($this->clock->now()))->toBe(Loan::OVERDUE)
        ->and($loan->items[0]->daysLate($this->clock->now()))->toBe(1);

    [$loan] = $loan->returnItem($loan->items[0]->id, $book, $this->clock->now());
    expect($loan->status($this->clock->now()))->toBe(Loan::CLOSED);
});

it('stays open while some lines are pending and none is overdue (CA-07.4)', function () {
    [$loan, [$a]] = lendBooks($this->clock, aBook('A', isbn: '9780000000001'), aBook('B', isbn: '9780000000002'));
    [$loan] = $loan->returnItem($loan->items[0]->id, $a, $this->clock->now());
    expect($loan->status($this->clock->now()))->toBe(Loan::OPEN);
});

it('accepts 1 to 3 lines and rejects 0 or 4 with VALIDATION_ERROR (CA-06.1)', function () {
    [$loan] = lendBooks($this->clock, aBook('A', isbn: '9780000000001'), aBook('B', isbn: '9780000000002'), aBook('C', isbn: '9780000000003'));
    expect($loan->items)->toHaveCount(3)
        ->and(fn () => lendBooks($this->clock))->toThrow(DomainError::class, 'Un préstamo debe tener entre 1 y 3 libros.')
        ->and(fn () => lendBooks($this->clock, aBook('A', isbn: '9780000000001'), aBook('B', isbn: '9780000000002'),
            aBook('C', isbn: '9780000000003'), aBook('D', isbn: '9780000000004')))->toThrow(DomainError::class);
});

it('rejects the same book twice with DUPLICATE_BOOK_IN_LOAN (CA-06.2)', function () {
    $book = aBook();
    try {
        lendBooks($this->clock, $book, $book);
        $this->fail('expected DUPLICATE_BOOK_IN_LOAN');
    } catch (DomainError $e) {
        expect($e->errorCode)->toBe('DUPLICATE_BOOK_IN_LOAN')->and($e->details[0]['field'])->toBe('items[1].book_id');
    }
});

it('fails with BOOK_NOT_AVAILABLE and points at the line when a book has no copies (CA-06.3)', function () {
    $empty = aBook(copies: 1)->withdraw();
    try {
        lendBooks($this->clock, $empty);
        $this->fail('expected BOOK_NOT_AVAILABLE');
    } catch (DomainError $e) {
        expect($e->errorCode)->toBe('BOOK_NOT_AVAILABLE')->and($e->details[0])->toBe(['field' => 'items[0].book_id', 'issue' => 'sin ejemplares']);
    }
});

it('returns a line once: restocks one copy, a second return is ITEM_ALREADY_RETURNED (CA-07.1, CA-07.2)', function () {
    [$loan, [$book]] = lendBooks($this->clock, aBook(copies: 1));
    [$loan, $book] = $loan->returnItem($loan->items[0]->id, $book, $this->clock->now());
    expect($book->availableCopies)->toBe(1)
        ->and($loan->items[0]->returnedAt)->toEqual($this->clock->now());

    try {
        $loan->returnItem($loan->items[0]->id, $book, $this->clock->now());
        $this->fail('expected ITEM_ALREADY_RETURNED');
    } catch (DomainError $e) {
        expect($e->errorCode)->toBe('ITEM_ALREADY_RETURNED')->and($book->availableCopies)->toBe(1);
    }
});
