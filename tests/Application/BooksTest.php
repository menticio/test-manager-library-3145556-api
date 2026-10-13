<?php

// T-08 evidence: HU-03 and HU-04 pass against fakes.

use App\Application\Services\CreateBookService;
use App\Application\Services\GetBookService;
use App\Application\Services\GetCoverService;
use App\Application\Services\ListCategoriesService;
use App\Application\Services\RemoveCoverService;
use App\Application\Services\RetireBookService;
use App\Application\Services\SearchBooksService;
use App\Application\Services\UpdateBookService;
use App\Application\Services\UploadCoverService;
use App\Domain\Errors\DomainError;
use App\Domain\Loan;
use Tests\Fakes\FixedClock;
use Tests\Fakes\InMemoryBookRepository;
use Tests\Fakes\InMemoryCategoryRepository as Categories;
use Tests\Fakes\InMemoryCoverStorage;
use Tests\Fakes\InMemoryLoanRepository;

function codeOf(callable $call): ?string
{
    try {
        $call();
    } catch (DomainError $e) {
        return $e->errorCode;
    }

    return null;
}

const PNG = "\x89PNG\r\n\x1A\n";

beforeEach(function () {
    $this->books = new InMemoryBookRepository;
    $this->categories = new Categories;
    $this->covers = new InMemoryCoverStorage;
    $this->loans = new InMemoryLoanRepository;
    $this->clock = new FixedClock(new DateTimeImmutable('2026-10-13T15:00:00Z'));
    $this->create = new CreateBookService($this->books, $this->categories);
    $this->search = new SearchBooksService($this->books);
    $this->get = new GetBookService($this->books);
    $this->update = new UpdateBookService($this->books, $this->categories);
    $this->retire = new RetireBookService($this->books, $this->loans, $this->clock);
    $this->upload = new UploadCoverService($this->books, $this->covers);
    $this->removeCover = new RemoveCoverService($this->books, $this->covers);
    $this->getCover = new GetCoverService($this->books, $this->covers);
});

it('lists the five categories ordered by name', function () {
    $names = array_column((new ListCategoriesService($this->categories))->list(), 'name');
    expect($names)->toBe(['Ciencia', 'Historia', 'Infantil', 'Novela', 'Tecnología']);
});

it('creates a book with available copies equal to the total (CA-03.1)', function () {
    $book = $this->create->create('Cien años de soledad', 'García Márquez', '9780307474728', Categories::NOVELA, 4);
    expect($book->availableCopies)->toBe(4)->and($this->books->findActive($book->id))->not->toBeNull();
});

it('rejects a repeated ISBN among active books with ISBN_TAKEN, but accepts it again after retiring (CA-03.2, ADR-003)', function () {
    $book = $this->create->create('A', 'X', '9780307474728', Categories::NOVELA, 1);
    expect(codeOf(fn () => $this->create->create('B', 'Y', '9780307474728', Categories::NOVELA, 1)))->toBe('ISBN_TAKEN');

    $this->retire->retire($book->id);
    expect($this->create->create('B', 'Y', '9780307474728', Categories::NOVELA, 1)->isbn->value)->toBe('9780307474728');
});

it('validates ISBN, total and category with VALIDATION_ERROR (CA-03.2, CA-03.3)', function () {
    expect(codeOf(fn () => $this->create->create('A', 'X', '123', Categories::NOVELA, 1)))->toBe('VALIDATION_ERROR')
        ->and(codeOf(fn () => $this->create->create('A', 'X', '9780307474728', Categories::NOVELA, 0)))->toBe('VALIDATION_ERROR')
        ->and(codeOf(fn () => $this->create->create('A', 'X', '9780307474728', 'no-such-category', 1)))->toBe('VALIDATION_ERROR');
});

it('changes the total and moves available by the same difference, never below 0 (CA-03.3)', function () {
    $book = $this->create->create('A', 'X', '9780307474728', Categories::NOVELA, 3);
    $this->books->save($book->withdraw()->withdraw()); // 2 lent
    expect($this->update->update($book->id, 'A2', 'X2', Categories::CIENCIA, 5)->availableCopies)->toBe(3)
        ->and(codeOf(fn () => $this->update->update($book->id, 'A2', 'X2', Categories::CIENCIA, 1)))->toBe('VALIDATION_ERROR');
});

it('searches by partial title or author, category, active only, ordered by title, with total (CA-04.1, CA-04.2)', function () {
    $this->create->create('Zorba', 'Kazantzakis', '9780000000001', Categories::NOVELA, 1);
    $this->create->create('Breve historia del tiempo', 'Hawking', '9780000000002', Categories::CIENCIA, 1);
    $this->create->create('Cien años de soledad', 'García Márquez', '9780000000003', Categories::NOVELA, 1);
    $retired = $this->create->create('Cien sonetos', 'Neruda', '9780000000004', Categories::NOVELA, 1);
    $this->retire->retire($retired->id);

    $page = $this->search->search('CIEN', null, 1, 20);
    expect($page->total)->toBe(1)->and($page->items[0]->title)->toBe('Cien años de soledad');

    $novels = $this->search->search(null, Categories::NOVELA, 1, 20);
    expect(array_map(fn ($b) => $b->title, $novels->items))->toBe(['Cien años de soledad', 'Zorba']);

    expect($this->search->search('hawking', null, 1, 20)->total)->toBe(1);
});

it('paginates with size up to 50 (CA-04.1)', function () {
    foreach (range(1, 3) as $i) {
        $this->create->create("Libro {$i}", 'X', '978000000000'.$i, Categories::NOVELA, 1);
    }
    $page = $this->search->search(null, null, 2, 2);
    expect($page->items)->toHaveCount(1)->and($page->total)->toBe(3)->and($page->page)->toBe(2)
        ->and(codeOf(fn () => $this->search->search(null, null, 1, 51)))->toBe('VALIDATION_ERROR');
});

it('answers NOT_FOUND for an unknown or retired book (CA-04.3)', function () {
    $book = $this->create->create('A', 'X', '9780307474728', Categories::NOVELA, 1);
    $this->retire->retire($book->id);
    expect(codeOf(fn () => $this->get->get($book->id)))->toBe('NOT_FOUND')
        ->and(codeOf(fn () => $this->get->get('00000000-0000-4000-8000-000000000000')))->toBe('NOT_FOUND');
});

it('retires logically: the book stays stored with deleted_at (CA-03.5, ADR-003)', function () {
    $book = $this->create->create('A', 'X', '9780307474728', Categories::NOVELA, 1);
    $this->retire->retire($book->id);
    expect($this->books->books[$book->id]->deletedAt)->toEqual($this->clock->now());
});

it('does not retire a book with open loans: BOOK_HAS_OPEN_LOANS (CA-03.5)', function () {
    $book = $this->create->create('A', 'X', '9780307474728', Categories::NOVELA, 1);
    [$loan] = Loan::lend('loan-1', 'member-1', 'user-1', $this->clock->now(), [['book' => $book, 'category_name' => 'Novela']]);
    $this->loans->loans[$loan->id] = $loan;
    expect(codeOf(fn () => $this->retire->retire($book->id)))->toBe('BOOK_HAS_OPEN_LOANS');
});

it('stores a PNG or JPG cover as an opaque key; null without cover (CA-03.4)', function () {
    $book = $this->create->create('A', 'X', '9780307474728', Categories::NOVELA, 1);
    expect($book->coverKey)->toBeNull();

    $withCover = $this->upload->upload($book->id, PNG.str_repeat('x', 100));
    expect($withCover->coverKey)->not->toBeNull()->not->toContain('/')
        ->and($this->getCover->get($book->id)['content_type'])->toBe('image/png');

    $this->removeCover->remove($book->id);
    expect($this->books->findActive($book->id)->coverKey)->toBeNull()
        ->and($this->covers->files)->toBe([])
        ->and(codeOf(fn () => $this->getCover->get($book->id)))->toBe('NOT_FOUND');
});

it('validates the cover by content and size: not an image or over 2 MB -> 422 (CA-03.4)', function () {
    $book = $this->create->create('A', 'X', '9780307474728', Categories::NOVELA, 1);
    expect(codeOf(fn () => $this->upload->upload($book->id, 'GIF89a fake')))->toBe('VALIDATION_ERROR')
        ->and(codeOf(fn () => $this->upload->upload($book->id, PNG.str_repeat('x', 3 * 1024 * 1024))))->toBe('VALIDATION_ERROR')
        ->and($this->upload->upload($book->id, PNG.str_repeat('x', 2 * 1024 * 1024 - 8))->coverKey)->not->toBeNull();
});
