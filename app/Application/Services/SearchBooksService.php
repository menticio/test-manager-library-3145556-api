<?php

namespace App\Application\Services;

use App\Application\Page;
use App\Application\Ports\Outbound\BookRepository;

/** HU-04: partial text in title or author, category filter, active only, ordered by title, paginated with total. */
final readonly class SearchBooksService
{
    public function __construct(private BookRepository $books) {}

    public function search(?string $text, ?string $categoryId, ?int $page, ?int $size): Page
    {
        [$page, $size] = Page::validate($page, $size);
        $text = $text === null ? null : trim($text);

        return $this->books->search($text === '' ? null : $text, $categoryId, $page, $size);
    }
}
