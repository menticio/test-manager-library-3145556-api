<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\CategoryRepository;
use App\Domain\Book;
use App\Domain\Errors\DomainError;
use App\Domain\Id;
use App\Domain\ValueObjects\Isbn;
use App\Domain\ValueObjects\Quantity;

/** CA-03.1, CA-03.2: create a book; available copies are computed by the server. */
final readonly class CreateBookService
{
    public function __construct(
        private BookRepository $books,
        private CategoryRepository $categories,
    ) {}

    public function create(string $title, string $author, string $isbn, string $categoryId, int $totalCopies): Book
    {
        $book = Book::create(Id::new(), $title, $author, new Isbn($isbn), $categoryId, new Quantity($totalCopies));

        if ($this->categories->nameOf($categoryId) === null) {
            throw DomainError::validation('category_id', 'no existe', 'La categoría no existe.');
        }
        if ($this->books->activeIsbnExists($book->isbn->value)) {
            throw new DomainError('ISBN_TAKEN', "Ya existe un libro activo con el ISBN {$book->isbn->value}.",
                [['field' => 'isbn', 'issue' => 'repetido']]);
        }

        $this->books->add($book);

        return $book;
    }
}
