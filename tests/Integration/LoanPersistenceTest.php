<?php

// T-13 evidence: loan repository with the version witness against real PostgreSQL.
// Two loans of the last copy: one succeeds, the other gets BOOK_NOT_AVAILABLE (RNF-2, ADR-002).

use App\Adapters\Outbound\Persistence\PgBookRepository;
use App\Adapters\Outbound\Persistence\PgCategoryRepository;
use App\Adapters\Outbound\Persistence\PgLoanRepository;
use App\Adapters\Outbound\Persistence\PgMemberRepository;
use App\Adapters\Outbound\Persistence\PgOverdueReader;
use App\Adapters\Outbound\Persistence\PgReportReader;
use App\Adapters\Outbound\Persistence\PgUserRepository;
use App\Application\Ports\Outbound\ConcurrencyConflict;
use App\Application\Services\LendBooksService;
use App\Domain\Book;
use App\Domain\Errors\DomainError;
use App\Domain\Id;
use App\Domain\Loan;
use App\Domain\Member;
use App\Domain\User;
use App\Domain\ValueObjects\DateRange;
use App\Domain\ValueObjects\DocumentNumber;
use App\Domain\ValueObjects\Isbn;
use App\Domain\ValueObjects\Quantity;
use Illuminate\Support\Facades\DB;
use Tests\Fakes\FixedClock;

beforeEach(function () {
    $this->books = new PgBookRepository;
    $this->loans = new PgLoanRepository;
    $this->members = new PgMemberRepository;
    $this->now = new DateTimeImmutable('2026-10-01T10:00:00Z');

    $this->member = new Member(Id::new(), 'Laura Gómez', new DocumentNumber('1020304050'));
    $this->members->add($this->member);
    $this->user = User::registerLibrarian(Id::new(), 'ana', 'hash', 'librarian');
    (new PgUserRepository)->add($this->user);

    $this->book = function (string $title, string $isbn, int $copies = 2): Book {
        $book = Book::create(Id::new(), $title, 'Autor', new Isbn($isbn), '11111111-1111-4111-8111-111111111111', new Quantity($copies));
        $this->books->add($book);

        return $book;
    };
    $this->lendAt = function (DateTimeImmutable $at, Book ...$books): Loan {
        [$loan, $withdrawn] = Loan::lend(Id::new(), $this->member->id, $this->user->id, $at,
            array_map(fn (Book $b) => ['book' => $b, 'category_name' => 'Novela'], $books));
        $this->loans->addLoan($loan, $withdrawn);

        return $loan;
    };
});

it('stores the loan, its lines and the withdrawn copies in one operation and reads them back (Q5)', function () {
    $book = ($this->book)('Cien años de soledad', '9780000000001', 2);
    $loan = ($this->lendAt)($this->now, $book);

    $read = $this->loans->find($loan->id);
    expect($read->memberId)->toBe($this->member->id)
        ->and($read->loanedByUserId)->toBe($this->user->id)
        ->and($read->loanedAt)->toEqual($this->now)
        ->and($read->items[0]->bookTitle)->toBe('Cien años de soledad')
        ->and($read->items[0]->dueAt)->toEqual($this->now->modify('+14 days'))
        ->and($read->items[0]->returnedAt)->toBeNull()
        ->and($this->books->findActive($book->id)->availableCopies)->toBe(1)
        ->and($this->loans->countUnreturnedForBook($book->id))->toBe(1)
        ->and($this->loans->unreturnedItemsOfMember($this->member->id))->toHaveCount(1);
});

it('rejects a stale copy count with ConcurrencyConflict and stores nothing (ADR-002)', function () {
    $book = ($this->book)('Último ejemplar', '9780000000001', 1);
    ($this->lendAt)($this->now, $book); // first loan takes the last copy

    // Second loan built from the SAME stale read (available_copies = 1).
    [$late, $withdrawn] = Loan::lend(Id::new(), $this->member->id, $this->user->id, $this->now,
        [['book' => $book, 'category_name' => 'Novela']]);

    expect(fn () => $this->loans->addLoan($late, $withdrawn))->toThrow(ConcurrencyConflict::class)
        ->and($this->loans->find($late->id))->toBeNull()
        ->and($this->books->findActive($book->id)->availableCopies)->toBe(0);
});

it('two loans of the last copy: one is registered, the other gets BOOK_NOT_AVAILABLE (RNF-2)', function () {
    $book = ($this->book)('Último ejemplar', '9780000000001', 1);
    $other = new Member(Id::new(), 'Pedro Ruiz', new DocumentNumber('7788990011'));
    $this->members->add($other);
    $service = new LendBooksService($this->members, $this->books, new PgCategoryRepository, $this->loans, new FixedClock($this->now));

    $first = $service->lend($this->member->id, $this->user->id, [$book->id]);
    try {
        $service->lend($other->id, $this->user->id, [$book->id]);
        $this->fail('expected BOOK_NOT_AVAILABLE');
    } catch (DomainError $e) {
        expect($e->errorCode)->toBe('BOOK_NOT_AVAILABLE');
    }
    expect(DB::table('loan_item')->where('book_id', $book->id)->count())->toBe(1)
        ->and($first->items)->toHaveCount(1);
});

it('the CHECK ck_book_available_range is the last barrier (ADR-002)', function () {
    $book = ($this->book)('A', '9780000000001', 1);
    expect(fn () => DB::transaction(fn () => DB::table('book')->where('id', $book->id)->update(['available_copies' => 2])))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('stores a return once: a second write of the same line is a ConcurrencyConflict (CA-07.2)', function () {
    $book = ($this->book)('A', '9780000000001', 1);
    $loan = ($this->lendAt)($this->now, $this->books->findActive($book->id));
    $current = $this->books->findActive($book->id);

    [$returned, $restocked] = $loan->returnItem($loan->items[0]->id, $current, $this->now->modify('+2 days'));
    $this->loans->saveReturn($returned, $loan->items[0]->id, $restocked);

    expect($this->loans->find($loan->id)->items[0]->returnedAt)->toEqual($this->now->modify('+2 days'))
        ->and($this->books->findActive($book->id)->availableCopies)->toBe(1)
        ->and(fn () => $this->loans->saveReturn($returned, $loan->items[0]->id, $restocked))->toThrow(ConcurrencyConflict::class)
        ->and($this->books->findActive($book->id)->availableCopies)->toBe(1);
});

it('lists loans by range, computed status and member, newest first (Q6, CA-08.2)', function () {
    $old = ($this->lendAt)($this->now, ($this->book)('A', '9780000000001'));
    $new = ($this->lendAt)($this->now->modify('+5 days'), ($this->book)('B', '9780000000002'));
    $at = $this->now->modify('+16 days'); // old is overdue (due day 14), new is open (due day 19)

    $all = $this->loans->search(null, null, null, $at, 1, 20);
    expect(array_map(fn ($l) => $l->id, $all->items))->toBe([$new->id, $old->id])
        ->and($this->loans->search(null, 'overdue', null, $at, 1, 20)->items[0]->id)->toBe($old->id)
        ->and($this->loans->search(null, 'open', null, $at, 1, 20)->items[0]->id)->toBe($new->id)
        ->and($this->loans->search(null, 'closed', null, $at, 1, 20)->total)->toBe(0)
        ->and($this->loans->search(new DateRange(new DateTimeImmutable('2026-10-06'), new DateTimeImmutable('2026-10-06')), null, null, $at, 1, 20)->items[0]->id)->toBe($new->id)
        ->and($this->loans->search(null, null, $this->member->id, $at, 1, 1)->total)->toBe(2);
});

it('reads overdue lines with member name, most late first (Q8, HU-09)', function () {
    ($this->lendAt)($this->now, ($this->book)('Viejo', '9780000000001'));
    ($this->lendAt)($this->now->modify('+3 days'), ($this->book)('Nuevo', '9780000000002'));

    $rows = (new PgOverdueReader)->unreturnedDueBefore($this->now->modify('+20 days'));
    expect(array_column($rows, 'book_title'))->toBe(['Viejo', 'Nuevo'])
        ->and($rows[0]['member_name'])->toBe('Laura Gómez')
        ->and($rows[0]['due_at'])->toEqual($this->now->modify('+14 days'));
});

it('computes the report in the engine with the FROZEN title after renaming the book (Q9, ADR-004)', function () {
    $book = ($this->book)('Título viejo', '9780000000001', 5);
    ($this->lendAt)($this->now, $book);
    $this->books->save($this->books->findActive($book->id)->replaceDetails('Título nuevo', 'Autor', '22222222-2222-4222-8222-222222222222', new Quantity(5)));
    ($this->lendAt)($this->now->modify('+1 day'), $this->books->findActive($book->id));

    $rows = (new PgReportReader)->mostBorrowed(new DateRange(new DateTimeImmutable('2026-10-01'), new DateTimeImmutable('2026-10-31')));
    expect($rows)->toHaveCount(2)
        ->and(array_column($rows, 'book_title'))->toEqualCanonicalizing(['Título viejo', 'Título nuevo'])
        ->and(array_column($rows, 'category_name'))->toEqualCanonicalizing(['Novela', 'Novela'])
        ->and(array_keys($rows[0]))->toBe(['book_id', 'book_title', 'category_name', 'times_borrowed'])
        ->and((new PgReportReader)->mostBorrowed(new DateRange(new DateTimeImmutable('2025-01-01'), new DateTimeImmutable('2025-01-31'))))->toBe([]);
});
