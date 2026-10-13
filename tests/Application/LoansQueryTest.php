<?php

// HU-08 evidence: a loan with its lines and computed status; list by range, status and member, newest first.

use App\Application\Services\GetLoanService;
use App\Application\Services\LendBooksService;
use App\Application\Services\ListLoansService;
use App\Application\Services\ReturnItemService;
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
    $this->clock = new FixedClock(new DateTimeImmutable('2026-10-01T10:00:00Z'));
    $this->books = new InMemoryBookRepository;
    $this->members = new InMemoryMemberRepository;
    $this->loans = new InMemoryLoanRepository($this->books, $this->members);
    $this->lend = new LendBooksService($this->members, $this->books, new Categories, $this->loans, $this->clock);
    $this->get = new GetLoanService($this->loans, $this->clock);
    $this->list = new ListLoansService($this->loans, $this->clock);

    $this->laura = new Member(Id::new(), 'Laura', new DocumentNumber('1'));
    $this->pedro = new Member(Id::new(), 'Pedro', new DocumentNumber('2'));
    $this->members->add($this->laura);
    $this->members->add($this->pedro);
    $this->bookId = function (string $isbn) {
        $book = Book::create(Id::new(), "Libro {$isbn}", 'Autor', new Isbn($isbn), Categories::NOVELA, new Quantity(3));
        $this->books->add($book);

        return $book->id;
    };
});

it('shows a loan with its lines and computed status (CA-08.1, CA-08.3)', function () {
    $loan = $this->lend->lend($this->laura->id, 'u', [($this->bookId)('9780000000001')]);
    $found = $this->get->get($loan->id);
    expect($found['loan']->items)->toHaveCount(1)->and($found['status'])->toBe('open');

    $this->clock->advance('+20 days');
    expect($this->get->get($loan->id)['status'])->toBe('overdue')
        ->and(fn () => $this->get->get('nope'))->toThrow(DomainError::class);
});

it('lists by date range, status and member, newest first, paginated (CA-08.2)', function () {
    $first = $this->lend->lend($this->laura->id, 'u', [($this->bookId)('9780000000001')]);  // 2026-10-01
    $this->clock->advance('+3 days');
    $second = $this->lend->lend($this->pedro->id, 'u', [($this->bookId)('9780000000002')]); // 2026-10-04
    (new ReturnItemService($this->loans, $this->books, $this->clock))->return($second->id, $second->items[0]->id);

    expect(array_map(fn ($r) => $r['loan']->id, $this->list->list(null, null, null, null, null, null)->items))->toBe([$second->id, $first->id])
        ->and($this->list->list(null, null, 'closed', null, null, null)->items[0]['loan']->id)->toBe($second->id)
        ->and($this->list->list(null, null, 'open', null, null, null)->items[0]['status'])->toBe('open')
        ->and($this->list->list(null, null, null, $this->laura->id, null, null)->total)->toBe(1)
        ->and($this->list->list('2026-10-01', '2026-10-01', null, null, null, null)->items[0]['loan']->id)->toBe($first->id)
        ->and($this->list->list(null, null, null, null, 1, 1)->total)->toBe(2);
});

it('validates status and dates', function () {
    expect(fn () => $this->list->list(null, null, 'lost', null, null, null))->toThrow(DomainError::class)
        ->and(fn () => $this->list->list('2026-10-31', '2026-10-01', null, null, null, null))->toThrow(DomainError::class);
});
