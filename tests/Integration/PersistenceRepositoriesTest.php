<?php

// T-12 evidence: book, member, user and category repositories against real PostgreSQL (plan.md §3, integration level).
// Needs the infra engine migrated (see README). Every test runs inside a transaction that is rolled back.

use App\Adapters\Outbound\Persistence\PgBookRepository;
use App\Adapters\Outbound\Persistence\PgCategoryRepository;
use App\Adapters\Outbound\Persistence\PgMemberRepository;
use App\Adapters\Outbound\Persistence\PgUserRepository;
use App\Domain\Book;
use App\Domain\Errors\DomainError;
use App\Domain\Id;
use App\Domain\Member;
use App\Domain\User;
use App\Domain\ValueObjects\DocumentNumber;
use App\Domain\ValueObjects\Isbn;
use App\Domain\ValueObjects\Quantity;

const NOVELA = '11111111-1111-4111-8111-111111111111';
const CIENCIA = '22222222-2222-4222-8222-222222222222';

function storedBook(PgBookRepository $repo, string $title, string $isbn, string $category = NOVELA, int $copies = 2): Book
{
    $book = Book::create(Id::new(), $title, 'Autor de '.$title, new Isbn($isbn), $category, new Quantity($copies));
    $repo->add($book);

    return $book;
}

function domainCode(callable $call): ?string
{
    try {
        $call();
    } catch (DomainError $e) {
        return $e->errorCode;
    }

    return null;
}

beforeEach(function () {
    $this->books = new PgBookRepository;
    $this->members = new PgMemberRepository;
    $this->users = new PgUserRepository;
    $this->categories = new PgCategoryRepository;
});

it('reads the five seeded categories ordered by name', function () {
    expect(array_column($this->categories->all(), 'name'))->toBe(['Ciencia', 'Historia', 'Infantil', 'Novela', 'Tecnología'])
        ->and($this->categories->nameOf(NOVELA))->toBe('Novela')
        ->and($this->categories->nameOf('00000000-0000-4000-8000-000000000000'))->toBeNull();
});

it('stores and reads a book with every column of data-model.md §3', function () {
    $book = storedBook($this->books, 'Cien años de soledad', '9780307474728', copies: 4);
    $read = $this->books->findActive($book->id);

    expect($read->title)->toBe('Cien años de soledad')
        ->and($read->isbn->value)->toBe('9780307474728')
        ->and($read->categoryId)->toBe(NOVELA)
        ->and($read->totalCopies->value)->toBe(4)
        ->and($read->availableCopies)->toBe(4)
        ->and($read->coverKey)->toBeNull()
        ->and($read->deletedAt)->toBeNull();
});

it('saves changes and hides retired books from every read (ADR-003)', function () {
    $book = storedBook($this->books, 'A', '9780000000001');
    $this->books->save($book->withdraw()->withCover('cover-key.png'));
    expect($this->books->findActive($book->id)->availableCopies)->toBe(1)
        ->and($this->books->findActive($book->id)->coverKey)->toBe('cover-key.png');

    $this->books->save($this->books->findActive($book->id)->retire(new DateTimeImmutable('2026-10-13T15:00:00Z')));
    expect($this->books->findActive($book->id))->toBeNull()
        ->and($this->books->findActiveByIds([$book->id]))->toBe([])
        ->and($this->books->activeIsbnExists('9780000000001'))->toBeFalse();
});

it('maps the partial unique ISBN index to ISBN_TAKEN and allows reusing a retired ISBN (CA-03.2, ADR-003)', function () {
    $book = storedBook($this->books, 'A', '9780000000001');
    expect($this->books->activeIsbnExists('9780000000001'))->toBeTrue()
        ->and(domainCode(fn () => storedBook($this->books, 'B', '9780000000001')))->toBe('ISBN_TAKEN');

    $this->books->save($book->retire(new DateTimeImmutable('2026-10-13T15:00:00Z')));
    expect(storedBook($this->books, 'B', '9780000000001')->title)->toBe('B');
});

it('finds active books by batch (Q3)', function () {
    $a = storedBook($this->books, 'A', '9780000000001');
    $b = storedBook($this->books, 'B', '9780000000002');
    expect(array_keys($this->books->findActiveByIds([$a->id, $b->id, '00000000-0000-4000-8000-000000000000'])))
        ->toEqualCanonicalizing([$a->id, $b->id]);
});

it('searches by partial title or author, category, ordered by title, paginated with total (Q1, HU-04)', function () {
    storedBook($this->books, 'Zorba el griego', '9780000000001');
    storedBook($this->books, 'Breve historia del tiempo', '9780000000002', CIENCIA);
    storedBook($this->books, 'Cien años de soledad', '9780000000003');

    expect($this->books->search('CIEN', null, 1, 20)->items[0]->title)->toBe('Cien años de soledad')
        ->and($this->books->search('autor de breve', null, 1, 20)->total)->toBe(1)
        ->and(array_map(fn ($b) => $b->title, $this->books->search(null, NOVELA, 1, 20)->items))->toBe(['Cien años de soledad', 'Zorba el griego'])
        ->and($this->books->search('%', null, 1, 20)->total)->toBe(0);

    $page = $this->books->search(null, null, 2, 2);
    expect($page->total)->toBe(3)->and($page->items)->toHaveCount(1)->and($page->items[0]->title)->toBe('Zorba el griego');
});

it('stores members, finds them, searches them and maps the unique document to DOCUMENT_TAKEN (HU-05)', function () {
    $member = new Member(Id::new(), 'Laura Gómez', new DocumentNumber('1020304050'));
    $this->members->add($member);
    $this->members->add(new Member(Id::new(), 'Pedro Ruiz', new DocumentNumber('7788990011')));

    expect($this->members->find($member->id)->fullName)->toBe('Laura Gómez')
        ->and($this->members->documentExists('1020304050'))->toBeTrue()
        ->and($this->members->search('laura', 1, 20)->total)->toBe(1)
        ->and($this->members->search('7788', 1, 20)->items[0]->fullName)->toBe('Pedro Ruiz')
        ->and(domainCode(fn () => $this->members->add(new Member(Id::new(), 'Otra', new DocumentNumber('1020304050')))))->toBe('DOCUMENT_TAKEN');
});

it('stores users with only the hash and maps the unique username to USERNAME_TAKEN (HU-02)', function () {
    $this->users->add(User::registerLibrarian(Id::new(), 'Ana ', 'argon-hash', 'librarian'));

    $read = $this->users->findByUsername('ana');
    expect($read->username)->toBe('ana')
        ->and($read->passwordHash)->toBe('argon-hash')
        ->and($read->role)->toBe('librarian')
        ->and($this->users->usernameExists('ana'))->toBeTrue()
        ->and(domainCode(fn () => $this->users->add(User::registerLibrarian(Id::new(), 'ana', 'h', 'librarian'))))->toBe('USERNAME_TAKEN');
});
