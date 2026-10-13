<?php

namespace App\Adapters\Inbound\Http;

use App\Application\Page;
use App\Domain\Book;
use App\Domain\Loan;
use App\Domain\LoanItem;
use App\Domain\Member;
use App\Domain\User;
use DateTimeImmutable;
use DateTimeZone;

/** Response shapes of api-contract.md §3: snake_case fields, UUID as text, ISO 8601 UTC dates with Z. */
final class Present
{
    public static function instant(?DateTimeImmutable $value): ?string
    {
        return $value?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    /** @param array<string, string> $categoryNames id => name */
    public static function book(Book $book, array $categoryNames): array
    {
        return [
            'id' => $book->id,
            'title' => $book->title,
            'author' => $book->author,
            'isbn' => $book->isbn->value,
            'category' => ['id' => $book->categoryId, 'name' => $categoryNames[$book->categoryId] ?? null],
            'total_copies' => $book->totalCopies->value,
            'available_copies' => $book->availableCopies,
            'cover_key' => $book->coverKey,
        ];
    }

    /** Never password nor password_hash (Article IX, RNF-3). */
    public static function user(User $user): array
    {
        return ['id' => $user->id, 'username' => $user->username, 'role' => $user->role];
    }

    public static function member(Member $member): array
    {
        return ['id' => $member->id, 'full_name' => $member->fullName, 'document_number' => $member->documentNumber->value];
    }

    public static function loan(Loan $loan, string $status): array
    {
        return [
            'id' => $loan->id,
            'member_id' => $loan->memberId,
            'loaned_at' => self::instant($loan->loanedAt),
            'status' => $status,
            'items' => array_map([self::class, 'item'], $loan->items),
        ];
    }

    public static function item(LoanItem $item): array
    {
        return [
            'id' => $item->id,
            'book_id' => $item->bookId,
            'book_title' => $item->bookTitle,
            'isbn' => $item->isbn,
            'category_name' => $item->categoryName,
            'due_at' => self::instant($item->dueAt),
            'returned_at' => self::instant($item->returnedAt),
        ];
    }

    public static function page(Page $page, callable $item): array
    {
        return ['items' => array_map($item, $page->items), 'page' => $page->page, 'size' => $page->size, 'total' => $page->total];
    }
}
