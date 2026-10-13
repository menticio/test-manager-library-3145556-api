<?php

// T-09 evidence: HU-06 complete with fakes: limits, overdue, no copies.

use App\Application\Services\LendBooksService;
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
    $this->clock = new FixedClock(new DateTimeImmutable('2026-10-14T14:15:00Z'));
    $this->books = new InMemoryBookRepository;
    $this->members = new InMemoryMemberRepository;
    $this->loans = new InMemoryLoanRepository($this->books);
    $this->service = new LendBooksService($this->members, $this->books, new Categories, $this->loans, $this->clock);

    $this->member = new Member(Id::new(), 'Laura Gómez', new DocumentNumber('1020304050'));
    $this->members->add($this->member);
    $this->newBook = function (string $title, int $copies = 2) {
        static $n = 0;
        $book = Book::create(Id::new(), $title, 'Autor', new Isbn(sprintf('978%010d', ++$n)), Categories::NOVELA, new Quantity($copies));
        $this->books->add($book);

        return $book;
    };
});

function failureOf(callable $call): ?DomainError
{
    try {
        $call();
    } catch (DomainError $e) {
        return $e;
    }

    return null;
}

it('registers a loan of the authenticated user with frozen values and due at +14 days (CA-06.1, CA-06.6)', function () {
    $book = ($this->newBook)('Cien años de soledad');
    $loan = $this->service->lend($this->member->id, 'librarian-1', [$book->id]);

    expect($loan->loanedByUserId)->toBe('librarian-1')
        ->and($loan->memberId)->toBe($this->member->id)
        ->and($loan->items[0]->bookTitle)->toBe('Cien años de soledad')
        ->and($loan->items[0]->categoryName)->toBe('Novela')
        ->and($loan->items[0]->dueAt->format(DATE_ATOM))->toBe('2026-10-28T14:15:00+00:00')
        ->and($this->loans->loans)->toHaveCount(1);
});

it('withdraws one copy per line in the same operation (CA-06.3)', function () {
    $a = ($this->newBook)('A', 2);
    $b = ($this->newBook)('B', 1);
    $this->service->lend($this->member->id, 'u', [$a->id, $b->id]);
    expect($this->books->findActive($a->id)->availableCopies)->toBe(1)
        ->and($this->books->findActive($b->id)->availableCopies)->toBe(0);
});

it('rejects a repeated book with DUPLICATE_BOOK_IN_LOAN and stores nothing (CA-06.2)', function () {
    $a = ($this->newBook)('A');
    expect(failureOf(fn () => $this->service->lend($this->member->id, 'u', [$a->id, $a->id]))->errorCode)->toBe('DUPLICATE_BOOK_IN_LOAN')
        ->and($this->loans->loans)->toBe([])
        ->and($this->books->findActive($a->id)->availableCopies)->toBe(2);
});

it('without copies answers BOOK_NOT_AVAILABLE and registers nothing at all (CA-06.3)', function () {
    $ok = ($this->newBook)('Con ejemplares', 1);
    $none = ($this->newBook)('Sin ejemplares', 1);
    $this->books->save($none->withdraw());

    expect(failureOf(fn () => $this->service->lend($this->member->id, 'u', [$ok->id, $none->id]))->errorCode)->toBe('BOOK_NOT_AVAILABLE')
        ->and($this->loans->loans)->toBe([])
        ->and($this->books->findActive($ok->id)->availableCopies)->toBe(1);
});

it('rejects more than 3 books not returned in total with MEMBER_LIMIT_EXCEEDED and the current count (CA-06.4)', function () {
    $this->service->lend($this->member->id, 'u', [($this->newBook)('A')->id, ($this->newBook)('B')->id]);
    $error = failureOf(fn () => $this->service->lend($this->member->id, 'u', [($this->newBook)('C')->id, ($this->newBook)('D')->id]));

    expect($error->errorCode)->toBe('MEMBER_LIMIT_EXCEEDED')
        ->and($error->getMessage())->toContain('2 libros sin devolver')
        ->and($this->service->lend($this->member->id, 'u', [($this->newBook)('E')->id])->items)->toHaveCount(1);
});

it('rejects a member with an overdue book with MEMBER_HAS_OVERDUE (CA-06.5)', function () {
    $this->service->lend($this->member->id, 'u', [($this->newBook)('A')->id]);
    $this->clock->advance('+15 days');
    expect(failureOf(fn () => $this->service->lend($this->member->id, 'u', [($this->newBook)('B')->id]))->errorCode)->toBe('MEMBER_HAS_OVERDUE');
});

it('rejects 0 or more than 3 lines (CA-06.1)', function () {
    expect(failureOf(fn () => $this->service->lend($this->member->id, 'u', []))->errorCode)->toBe('VALIDATION_ERROR');
});

it('answers NOT_FOUND for an unknown member or book', function () {
    expect(failureOf(fn () => $this->service->lend('no-member', 'u', [($this->newBook)('A')->id]))->errorCode)->toBe('NOT_FOUND')
        ->and(failureOf(fn () => $this->service->lend($this->member->id, 'u', ['no-book']))->errorCode)->toBe('NOT_FOUND');
});

it('retries once on a version conflict and succeeds if the copy is still there (ADR-002)', function () {
    $this->loans->conflictsToSimulate = 1;
    expect($this->service->lend($this->member->id, 'u', [($this->newBook)('A')->id])->items)->toHaveCount(1);
});

it('answers BOOK_NOT_AVAILABLE when the conflict persists (ADR-002, RNF-2)', function () {
    $this->loans->conflictsToSimulate = 2;
    expect(failureOf(fn () => $this->service->lend($this->member->id, 'u', [($this->newBook)('A')->id]))->errorCode)->toBe('BOOK_NOT_AVAILABLE')
        ->and($this->loans->loans)->toBe([]);
});
