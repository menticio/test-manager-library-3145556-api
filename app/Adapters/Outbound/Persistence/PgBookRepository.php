<?php

namespace App\Adapters\Outbound\Persistence;

use App\Application\Page;
use App\Application\Ports\Outbound\BookRepository;
use App\Domain\Book;
use App\Domain\Errors\DomainError;
use App\Domain\ValueObjects\Isbn;
use App\Domain\ValueObjects\Quantity;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Books in library.book. Every read filters deleted_at IS NULL here, in one place (ADR-003). */
final class PgBookRepository implements BookRepository
{
    private const COLUMNS = ['id', 'title', 'author', 'isbn', 'category_id', 'total_copies', 'available_copies', 'cover_key', 'deleted_at'];

    public function findActive(string $id): ?Book
    {
        if (! PgCategoryRepository::isUuid($id)) {
            return null;
        }
        $row = $this->active()->where('id', $id)->first(self::COLUMNS);

        return $row === null ? null : self::toDomain($row);
    }

    public function findActiveByIds(array $ids): array
    {
        $ids = array_values(array_filter($ids, [PgCategoryRepository::class, 'isUuid']));
        $books = [];
        foreach ($this->active()->whereIn('id', $ids)->get(self::COLUMNS) as $row) {
            $books[$row->id] = self::toDomain($row);
        }

        return $books;
    }

    public function activeIsbnExists(string $isbn): bool
    {
        return $this->active()->where('isbn', $isbn)->exists();
    }

    public function add(Book $book): void
    {
        Pg::unique(fn () => DB::table('book')->insert(self::toRow($book)), self::isbnTaken($book));
    }

    public function save(Book $book): void
    {
        $row = self::toRow($book);
        unset($row['id']);
        Pg::unique(fn () => DB::table('book')->where('id', $book->id)->update($row), self::isbnTaken($book));
    }

    public function search(?string $text, ?string $categoryId, int $page, int $size): Page
    {
        $query = $this->active();
        if ($text !== null) {
            $like = Pg::likeContains($text);
            $query->where(fn (Builder $q) => $q->where('title', 'ilike', $like)->orWhere('author', 'ilike', $like));
        }
        if ($categoryId !== null) {
            PgCategoryRepository::isUuid($categoryId) ? $query->where('category_id', $categoryId) : $query->whereRaw('false');
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy('title')->orderBy('id')->offset(($page - 1) * $size)->limit($size)->get(self::COLUMNS);

        return new Page($rows->map(fn ($row) => self::toDomain($row))->all(), $page, $size, $total);
    }

    private function active(): Builder
    {
        return DB::table('book')->whereNull('deleted_at');
    }

    private static function toDomain(object $row): Book
    {
        return Book::reconstitute($row->id, $row->title, $row->author, new Isbn($row->isbn), $row->category_id,
            new Quantity((int) $row->total_copies), (int) $row->available_copies, $row->cover_key, Pg::fromTimestamp($row->deleted_at));
    }

    /** @return array<string, mixed> */
    private static function toRow(Book $book): array
    {
        return [
            'id' => $book->id,
            'title' => $book->title,
            'author' => $book->author,
            'isbn' => $book->isbn->value,
            'category_id' => $book->categoryId,
            'total_copies' => $book->totalCopies->value,
            'available_copies' => $book->availableCopies,
            'cover_key' => $book->coverKey,
            'deleted_at' => Pg::toTimestamp($book->deletedAt),
        ];
    }

    private static function isbnTaken(Book $book): DomainError
    {
        return new DomainError('ISBN_TAKEN', "Ya existe un libro activo con el ISBN {$book->isbn->value}.",
            [['field' => 'isbn', 'issue' => 'repetido']]);
    }
}
