<?php

// T-11 evidence: HU-10; the old title is kept after renaming the book (ADR-004).

use App\Application\Services\LendBooksService;
use App\Application\Services\MostBorrowedReportService;
use App\Application\Services\ReturnItemService;
use App\Application\Services\UpdateBookService;
use App\Domain\Book;
use App\Domain\Errors\DomainError;
use App\Domain\Id;
use App\Domain\Member;
use App\Domain\ValueObjects\DocumentNumber;
use App\Domain\ValueObjects\Isbn;
use App\Domain\ValueObjects\Quantity;
use Tests\Fakes\FixedClock;
use Tests\Fakes\InMemoryBookRepository;
use Tests\Fakes\InMemoryCategoryRepository as Categories;
use Tests\Fakes\InMemoryLoanRepository;
use Tests\Fakes\InMemoryMemberRepository;

beforeEach(function () {
    $this->clock = new FixedClock(new DateTimeImmutable('2026-10-05T10:00:00Z'));
    $this->books = new InMemoryBookRepository;
    $this->members = new InMemoryMemberRepository;
    $this->loans = new InMemoryLoanRepository($this->books, $this->members);
    $this->lend = new LendBooksService($this->members, $this->books, new Categories, $this->loans, $this->clock);
    $this->return = new ReturnItemService($this->loans, $this->books, $this->clock);
    $this->update = new UpdateBookService($this->books, new Categories);
    $this->report = new MostBorrowedReportService($this->loans);

    $this->member = new Member(Id::new(), 'Laura', new DocumentNumber('1'));
    $this->members->add($this->member);
    $this->book = Book::create(Id::new(), 'Título viejo', 'Autor', new Isbn('9780000000001'), Categories::NOVELA, new Quantity(5));
    $this->other = Book::create(Id::new(), 'Otro libro', 'Autor', new Isbn('9780000000002'), Categories::CIENCIA, new Quantity(5));
    $this->books->add($this->book);
    $this->books->add($this->other);

    // Lend and return right away so the member limit never gets in the way.
    $this->borrow = function (string $bookId) {
        $loan = $this->lend->lend($this->member->id, 'u', [$bookId]);
        $this->return->return($loan->id, $loan->items[0]->id);
    };
});

it('returns one row per book and frozen category with times borrowed, most borrowed first (CA-10.1)', function () {
    ($this->borrow)($this->other->id);
    ($this->borrow)($this->book->id);
    ($this->borrow)($this->book->id);

    $report = $this->report->report('2026-10-01', '2026-10-31');
    expect($report['from'])->toBe('2026-10-01')->and($report['to'])->toBe('2026-10-31')
        ->and($report['rows'])->toBe([
            ['book_id' => $this->book->id, 'book_title' => 'Título viejo', 'category_name' => 'Novela', 'times_borrowed' => 2],
            ['book_id' => $this->other->id, 'book_title' => 'Otro libro', 'category_name' => 'Ciencia', 'times_borrowed' => 1],
        ]);
});

it('keeps the old title and category after the book is renamed (CA-10.4, ADR-004, Article VIII)', function () {
    ($this->borrow)($this->book->id);
    $this->update->update($this->book->id, 'Título nuevo', 'Autor', Categories::NOVELA, 5);

    expect($this->report->report('2026-10-01', '2026-10-31')['rows'][0]['book_title'])->toBe('Título viejo');
});

it('shows two rows when the book changed category inside the range: no winner is chosen (ADR-004)', function () {
    ($this->borrow)($this->book->id);
    $this->update->update($this->book->id, 'Título viejo', 'Autor', Categories::CIENCIA, 5);
    ($this->borrow)($this->book->id);

    $rows = $this->report->report('2026-10-01', '2026-10-31')['rows'];
    expect(array_column($rows, 'category_name'))->toEqualCanonicalizing(['Novela', 'Ciencia'])
        ->and(array_column($rows, 'book_id'))->toBe([$this->book->id, $this->book->id]);
});

it('includes both days of the range and excludes loans outside it', function () {
    ($this->borrow)($this->book->id);  // 2026-10-05
    $this->clock->advance('+1 month'); // 2026-11-05
    ($this->borrow)($this->other->id);

    expect($this->report->report('2026-10-05', '2026-10-05')['rows'])->toHaveCount(1)
        ->and($this->report->report('2026-11-05', '2026-11-30')['rows'][0]['book_title'])->toBe('Otro libro');
});

it('answers an empty list for a range without loans, not an error (CA-10.2)', function () {
    expect($this->report->report('2025-01-01', '2025-01-31')['rows'])->toBe([]);
});

it('rejects an end before the start with INVALID_DATE_RANGE (CA-10.2)', function () {
    try {
        $this->report->report('2026-10-31', '2026-10-01');
        $this->fail('expected INVALID_DATE_RANGE');
    } catch (DomainError $e) {
        expect($e->errorCode)->toBe('INVALID_DATE_RANGE');
    }
});

it('requires from and to as dates (VALIDATION_ERROR)', function () {
    expect(fn () => $this->report->report(null, '2026-10-31'))->toThrow(DomainError::class)
        ->and(fn () => $this->report->report('2026-13-01', '2026-10-31'))->toThrow(DomainError::class);
});

it('exposes no member and no librarian in the rows (CA-10.3)', function () {
    ($this->borrow)($this->book->id);
    expect(array_keys($this->report->report('2026-10-01', '2026-10-31')['rows'][0]))
        ->toBe(['book_id', 'book_title', 'category_name', 'times_borrowed']);
});
