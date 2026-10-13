<?php

namespace App\Domain;

use DateTimeImmutable;

/**
 * One book inside a loan. Title, ISBN and category name are frozen at loan time (RN-4, ADR-004).
 * Only returned_at ever changes (RN-5).
 */
final readonly class LoanItem
{
    public function __construct(
        public string $id,
        public string $bookId,
        public string $bookTitle,
        public string $isbn,
        public string $categoryName,
        public DateTimeImmutable $dueAt,
        public ?DateTimeImmutable $returnedAt,
    ) {}

    public function isReturned(): bool
    {
        return $this->returnedAt !== null;
    }

    /** CA-09.1: not returned and due_at before now. */
    public function isOverdue(DateTimeImmutable $now): bool
    {
        return ! $this->isReturned() && $this->dueAt < $now;
    }

    /** Derived, never stored (Constitution, Article VII). */
    public function daysLate(DateTimeImmutable $now): int
    {
        return $this->isOverdue($now) ? $this->dueAt->diff($now)->days : 0;
    }

    public function markReturned(DateTimeImmutable $at): self
    {
        return new self($this->id, $this->bookId, $this->bookTitle, $this->isbn, $this->categoryName, $this->dueAt, $at);
    }
}
