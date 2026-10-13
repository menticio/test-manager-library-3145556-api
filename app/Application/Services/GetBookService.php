<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\BookRepository;
use App\Domain\Book;
use App\Domain\Errors\DomainError;

/** CA-04.3: unknown or retired book -> 404. */
final readonly class GetBookService
{
    public function __construct(private BookRepository $books) {}

    public function get(string $id): Book
    {
        return $this->books->findActive($id) ?? throw new DomainError('NOT_FOUND', 'El libro no existe.');
    }
}
