<?php

namespace Tests\Fakes;

use App\Application\Page;
use App\Application\Ports\Outbound\BookRepository;
use App\Domain\Book;

final class InMemoryBookRepository implements BookRepository
{
    /** @var array<string, Book> every book, active or retired */
    public array $books = [];

    public function findActive(string $id): ?Book
    {
        $book = $this->books[$id] ?? null;

        return $book?->isActive() ? $book : null;
    }

    public function findActiveByIds(array $ids): array
    {
        $found = [];
        foreach ($ids as $id) {
            if ($book = $this->findActive($id)) {
                $found[$id] = $book;
            }
        }

        return $found;
    }

    public function activeIsbnExists(string $isbn): bool
    {
        foreach ($this->books as $book) {
            if ($book->isActive() && $book->isbn->value === $isbn) {
                return true;
            }
        }

        return false;
    }

    public function add(Book $book): void
    {
        $this->books[$book->id] = $book;
    }

    public function save(Book $book): void
    {
        $this->books[$book->id] = $book;
    }

    public function search(?string $text, ?string $categoryId, int $page, int $size): Page
    {
        $matches = array_values(array_filter($this->books, fn (Book $b) => $b->isActive()
            && ($categoryId === null || $b->categoryId === $categoryId)
            && ($text === null || mb_stripos($b->title, $text) !== false || mb_stripos($b->author, $text) !== false)));
        usort($matches, fn (Book $a, Book $b) => strcmp($a->title, $b->title));

        return new Page(array_slice($matches, ($page - 1) * $size, $size), $page, $size, count($matches));
    }
}
