<?php

namespace App\Domain;

use App\Domain\Errors\DomainError;
use App\Domain\ValueObjects\Isbn;
use App\Domain\ValueObjects\Quantity;
use DateTimeImmutable;
use LogicException;

/**
 * Aggregate root Book (architecture.md §3).
 * Invariants: 0 <= available <= total (RN-1), total >= 1, title not empty, cover_key null or non-empty.
 * Immutable: every change returns a new Book.
 */
final readonly class Book
{
    public string $title;

    public string $author;

    private function __construct(
        public string $id,
        string $title,
        string $author,
        public Isbn $isbn,
        public string $categoryId,
        public Quantity $totalCopies,
        public int $availableCopies,
        public ?string $coverKey,
        public ?DateTimeImmutable $deletedAt,
    ) {
        $this->title = self::text($title, 'title', 250, 'El título');
        $this->author = self::text($author, 'author', 200, 'El autor');

        if ($availableCopies < 0 || $availableCopies > $totalCopies->value) {
            throw new LogicException('available_copies out of range [0, total_copies].');
        }
        if ($coverKey === '') {
            throw new LogicException('cover_key is null or a non-empty opaque key, never an empty string.');
        }
    }

    /** CA-03.1: available copies start equal to the total. */
    public static function create(string $id, string $title, string $author, Isbn $isbn, string $categoryId, Quantity $totalCopies): self
    {
        return new self($id, $title, $author, $isbn, $categoryId, $totalCopies, $totalCopies->value, null, null);
    }

    /** Rebuilds a stored book (used by the persistence adapter). */
    public static function reconstitute(string $id, string $title, string $author, Isbn $isbn, string $categoryId, Quantity $totalCopies, int $availableCopies, ?string $coverKey, ?DateTimeImmutable $deletedAt): self
    {
        return new self($id, $title, $author, $isbn, $categoryId, $totalCopies, $availableCopies, $coverKey, $deletedAt);
    }

    /** CA-06.3: one loan line takes exactly one copy. */
    public function withdraw(): self
    {
        if ($this->availableCopies === 0) {
            throw new DomainError('BOOK_NOT_AVAILABLE', "No quedan ejemplares disponibles de «{$this->title}».");
        }

        return $this->copy(availableCopies: $this->availableCopies - 1);
    }

    /** CA-07.1: a returned line gives back exactly one copy; never above the total (CA-07.3). */
    public function restock(): self
    {
        if ($this->availableCopies >= $this->totalCopies->value) {
            throw new LogicException('restock would leave available_copies above total_copies.');
        }

        return $this->copy(availableCopies: $this->availableCopies + 1);
    }

    /** PUT /books/{id} replaces title, author, category and total. CA-03.3: available moves by the same difference, never below 0 (422). */
    public function replaceDetails(string $title, string $author, string $categoryId, Quantity $totalCopies): self
    {
        $available = $this->availableCopies + ($totalCopies->value - $this->totalCopies->value);
        if ($available < 0) {
            $lent = $this->totalCopies->value - $this->availableCopies;
            throw DomainError::validation('total_copies', 'menor que los ejemplares prestados',
                "El total no puede ser menor que los {$lent} ejemplares prestados.");
        }

        return new self($this->id, $title, $author, $this->isbn, $categoryId, $totalCopies, $available, $this->coverKey, $this->deletedAt);
    }

    /** CA-03.4: a cover is an opaque key; without cover the key is null. */
    public function withCover(string $coverKey): self
    {
        return new self($this->id, $this->title, $this->author, $this->isbn, $this->categoryId, $this->totalCopies, $this->availableCopies, $coverKey, $this->deletedAt);
    }

    public function withoutCover(): self
    {
        return new self($this->id, $this->title, $this->author, $this->isbn, $this->categoryId, $this->totalCopies, $this->availableCopies, null, $this->deletedAt);
    }

    /** CA-03.5 / ADR-003: logical removal. The "no open loans" rule needs other aggregates and lives in the use case. */
    public function retire(DateTimeImmutable $at): self
    {
        if (! $this->isActive()) {
            throw new DomainError('NOT_FOUND', 'El libro no existe.');
        }

        return new self($this->id, $this->title, $this->author, $this->isbn, $this->categoryId, $this->totalCopies, $this->availableCopies, $this->coverKey, $at);
    }

    public function isActive(): bool
    {
        return $this->deletedAt === null;
    }

    private function copy(int $availableCopies): self
    {
        return new self($this->id, $this->title, $this->author, $this->isbn, $this->categoryId, $this->totalCopies, $availableCopies, $this->coverKey, $this->deletedAt);
    }

    private static function text(string $value, string $field, int $max, string $label): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $max) {
            throw DomainError::validation($field, "obligatorio, máximo {$max} caracteres",
                "{$label} es obligatorio y admite máximo {$max} caracteres.");
        }

        return $value;
    }
}
