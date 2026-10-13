<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\CategoryRepository;
use App\Domain\Book;
use App\Domain\Errors\DomainError;
use App\Domain\ValueObjects\Quantity;

/** PUT /books/{id}: replaces title, author, category and total (CA-03.3). */
final readonly class UpdateBookService
{
    public function __construct(
        private BookRepository $books,
        private CategoryRepository $categories,
    ) {}

    public function update(string $id, string $title, string $author, string $categoryId, int $totalCopies): Book
    {
        $book = $this->books->findActive($id) ?? throw new DomainError('NOT_FOUND', 'El libro no existe.');
        if ($this->categories->nameOf($categoryId) === null) {
            throw DomainError::validation('category_id', 'no existe', 'La categoría no existe.');
        }

        $book = $book->replaceDetails($title, $author, $categoryId, new Quantity($totalCopies));
        $this->books->save($book);

        return $book;
    }
}
