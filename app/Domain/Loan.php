<?php

namespace App\Domain;

use App\Domain\Errors\DomainError;
use DateInterval;
use DateTimeImmutable;
use LogicException;

/**
 * Aggregate root Loan (architecture.md §3): one member, 1 to 3 lines, no repeated book, due at loaned_at + 14 days.
 * Immutable fact: only returned_at of a line changes (RN-5, CA-06.7). The status is computed, never stored (Article VII).
 */
final readonly class Loan
{
    public const LOAN_DAYS = 14;

    public const MAX_ITEMS = 3;

    public const OPEN = 'open';

    public const OVERDUE = 'overdue';

    public const CLOSED = 'closed';

    /** @param list<LoanItem> $items */
    private function __construct(
        public string $id,
        public string $memberId,
        public string $loanedByUserId,
        public DateTimeImmutable $loanedAt,
        public array $items,
    ) {}

    /**
     * Registers a loan (HU-06). Each line withdraws one copy from its book in the same operation (CA-06.3).
     *
     * @param  list<array{book: Book, category_name: string}>  $lines
     * @return array{0: Loan, 1: list<Book>} the loan and the books with their copies already withdrawn
     */
    public static function lend(string $id, string $memberId, string $loanedByUserId, DateTimeImmutable $loanedAt, array $lines): array
    {
        $count = count($lines);
        if ($count < 1 || $count > self::MAX_ITEMS) {
            throw DomainError::validation('items', 'entre 1 y 3 libros', 'Un préstamo debe tener entre 1 y 3 libros.');
        }

        $loan = new self($id, $memberId, $loanedByUserId, $loanedAt, []);
        $books = [];
        foreach ($lines as $line) {
            [$loan, $books[]] = $loan->addItem(Id::new(), $line['book'], $line['category_name']);
        }

        return [$loan, $books];
    }

    /** @param list<LoanItem> $items */
    public static function reconstitute(string $id, string $memberId, string $loanedByUserId, DateTimeImmutable $loanedAt, array $items): self
    {
        return new self($id, $memberId, $loanedByUserId, $loanedAt, $items);
    }

    /**
     * Loan::addItem calls Book::withdraw before adding the line: both are one operation (architecture.md §3).
     *
     * @return array{0: Loan, 1: Book}
     */
    public function addItem(string $itemId, Book $book, string $categoryName): array
    {
        $position = count($this->items);
        if ($position >= self::MAX_ITEMS) {
            throw DomainError::validation('items', 'máximo 3 libros', 'Un préstamo admite máximo 3 libros.');
        }
        foreach ($this->items as $item) {
            if ($item->bookId === $book->id) {
                throw new DomainError('DUPLICATE_BOOK_IN_LOAN', "El libro «{$book->title}» está repetido en el préstamo.",
                    [['field' => "items[{$position}].book_id", 'issue' => 'repetido']]);
            }
        }

        try {
            $book = $book->withdraw();
        } catch (DomainError $e) {
            throw new DomainError($e->errorCode, $e->getMessage(), [['field' => "items[{$position}].book_id", 'issue' => 'sin ejemplares']]);
        }

        $item = new LoanItem($itemId, $book->id, $book->title, $book->isbn->value, $categoryName,
            $this->loanedAt->add(new DateInterval('P'.self::LOAN_DAYS.'D')), null);

        return [new self($this->id, $this->memberId, $this->loanedByUserId, $this->loanedAt, [...$this->items, $item]), $book];
    }

    /**
     * HU-07: returns one line; Book::restock is called once per line. A second return is ITEM_ALREADY_RETURNED (CA-07.2).
     *
     * @return array{0: Loan, 1: Book}
     */
    public function returnItem(string $itemId, Book $book, DateTimeImmutable $now): array
    {
        $items = $this->items;
        foreach ($items as $i => $item) {
            if ($item->id !== $itemId) {
                continue;
            }
            if ($item->isReturned()) {
                throw new DomainError('ITEM_ALREADY_RETURNED', "La línea de «{$item->bookTitle}» ya fue devuelta.");
            }
            if ($item->bookId !== $book->id) {
                throw new LogicException('The book does not belong to this loan line.');
            }
            $items[$i] = $item->markReturned($now);

            return [new self($this->id, $this->memberId, $this->loanedByUserId, $this->loanedAt, $items), $book->restock()];
        }

        throw new DomainError('NOT_FOUND', 'La línea de préstamo no existe.');
    }

    /** open: lines pending and none overdue; overdue: some pending line past due; closed: all returned (api-contract.md §3.6). */
    public function status(DateTimeImmutable $now): string
    {
        $pending = array_filter($this->items, fn (LoanItem $item) => ! $item->isReturned());
        if ($pending === []) {
            return self::CLOSED;
        }
        foreach ($pending as $item) {
            if ($item->isOverdue($now)) {
                return self::OVERDUE;
            }
        }

        return self::OPEN;
    }

    public function item(string $itemId): ?LoanItem
    {
        foreach ($this->items as $item) {
            if ($item->id === $itemId) {
                return $item;
            }
        }

        return null;
    }
}
