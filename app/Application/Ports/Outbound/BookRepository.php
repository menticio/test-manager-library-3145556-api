<?php

namespace App\Application\Ports\Outbound;

use App\Application\Page;
use App\Domain\Book;

/**
 * Port: books. Every read returns ACTIVE books only (ADR-003: deleted_at IS NULL in a single place).
 * There is no delete: books are only retired (ADR-003).
 */
interface BookRepository
{
    public function findActive(string $id): ?Book;

    /** Q3: books by batch before lending. @param list<string> $ids @return array<string, Book> keyed by id */
    public function findActiveByIds(array $ids): array;

    public function activeIsbnExists(string $isbn): bool;

    /** Throws DomainError ISBN_TAKEN if the partial unique index rejects it. */
    public function add(Book $book): void;

    public function save(Book $book): void;

    /** Q1: partial text in title or author, optional category, ordered by title. @return Page<Book> */
    public function search(?string $text, ?string $categoryId, int $page, int $size): Page;
}
