<?php

// T-10 evidence: HU-07 and HU-09; returning twice -> ITEM_ALREADY_RETURNED and copies are added only once.

use App\Application\Services\LendBooksService;
use App\Application\Services\OverdueService;
use App\Application\Services\ReturnItemService;
use App\Domain\Book;
use App\Domain\Errors\DomainError;
use App\Domain\Id;
use App\Domain\Loan;
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
    $this->clock = new FixedClock(new DateTimeImmutable('2026-10-01T10:00:00Z'));
    $this->books = new InMemoryBookRepository;
    $this->members = new InMemoryMemberRepository;
    $this->loans = new InMemoryLoanRepository($this->books, $this->members);
    $this->lend = new LendBooksService($this->members, $this->books, new Categories, $this->loans, $this->clock);
    $this->return = new ReturnItemService($this->loans, $this->books, $this->clock);
    $this->overdue = new OverdueService($this->loans, $this->clock);

    $this->newMember = function (string $name, string $doc) {
        $member = new Member(Id::new(), $name, new DocumentNumber($doc));
        $this->members->add($member);

        return $member;
    };
    $this->newBook = function (string $title, string $isbn, int $copies = 1) {
        $book = Book::create(Id::new(), $title, 'Autor', new Isbn($isbn), Categories::NOVELA, new Quantity($copies));
        $this->books->add($book);

        return $book;
    };
});

it('returns one line: sets returned_at and adds 1 copy (CA-07.1)', function () {
    $book = ($this->newBook)('A', '9780000000001', 1);
    $loan = $this->lend->lend(($this->newMember)('Laura', '1')->id, 'u', [$book->id]);
    expect($this->books->findActive($book->id)->availableCopies)->toBe(0);

    $this->clock->advance('+3 days');
    $item = $this->return->return($loan->id, $loan->items[0]->id);
    expect($item->returnedAt)->toEqual($this->clock->now())
        ->and($this->books->findActive($book->id)->availableCopies)->toBe(1);
});

it('returning a line twice is ITEM_ALREADY_RETURNED and never adds twice (CA-07.2, CA-07.3)', function () {
    $book = ($this->newBook)('A', '9780000000001', 1);
    $loan = $this->lend->lend(($this->newMember)('Laura', '1')->id, 'u', [$book->id]);
    $this->return->return($loan->id, $loan->items[0]->id);

    try {
        $this->return->return($loan->id, $loan->items[0]->id);
        $this->fail('expected ITEM_ALREADY_RETURNED');
    } catch (DomainError $e) {
        expect($e->errorCode)->toBe('ITEM_ALREADY_RETURNED');
    }
    expect($this->books->findActive($book->id)->availableCopies)->toBe(1);
});

it('marks the loan closed when all lines are returned (CA-07.4)', function () {
    $a = ($this->newBook)('A', '9780000000001');
    $b = ($this->newBook)('B', '9780000000002');
    $loan = $this->lend->lend(($this->newMember)('Laura', '1')->id, 'u', [$a->id, $b->id]);

    $this->return->return($loan->id, $loan->items[0]->id);
    expect($this->loans->find($loan->id)->status($this->clock->now()))->toBe(Loan::OPEN);

    $this->return->return($loan->id, $loan->items[1]->id);
    expect($this->loans->find($loan->id)->status($this->clock->now()))->toBe(Loan::CLOSED);
});

it('answers NOT_FOUND for an unknown loan or line', function () {
    $loan = $this->lend->lend(($this->newMember)('Laura', '1')->id, 'u', [($this->newBook)('A', '9780000000001')->id]);
    expect(fn () => $this->return->return('nope', 'nope'))->toThrow(DomainError::class, 'El préstamo no existe.')
        ->and(fn () => $this->return->return($loan->id, 'nope'))->toThrow(DomainError::class, 'La línea de préstamo no existe.');
});

it('lists overdue lines with book, member and days late, most days late first (CA-09.1, CA-09.2)', function () {
    $older = $this->lend->lend(($this->newMember)('Laura', '1')->id, 'u', [($this->newBook)('Viejo', '9780000000001')->id]);
    $this->clock->advance('+5 days');
    $this->lend->lend(($this->newMember)('Pedro', '2')->id, 'u', [($this->newBook)('Nuevo', '9780000000002')->id]);
    $returned = $this->lend->lend(($this->newMember)('Ana', '3')->id, 'u', [($this->newBook)('Devuelto', '9780000000003')->id]);
    $this->return->return($returned->id, $returned->items[0]->id);

    $this->clock->advance('+16 days'); // day 21: Viejo (due day 14) 7 days late, Nuevo (due day 19) 2 days late

    $rows = $this->overdue->list();
    expect(array_column($rows, 'book_title'))->toBe(['Viejo', 'Nuevo'])
        ->and(array_column($rows, 'days_late'))->toBe([7, 2])
        ->and(array_column($rows, 'member_name'))->toBe(['Laura', 'Pedro'])
        ->and($rows[0]['loan_id'])->toBe($older->id);
});

it('does not list a line that is due exactly now (due_at must be before now)', function () {
    $this->lend->lend(($this->newMember)('Laura', '1')->id, 'u', [($this->newBook)('A', '9780000000001')->id]);
    $this->clock->advance('+14 days');
    expect($this->overdue->list())->toBe([]);
});
