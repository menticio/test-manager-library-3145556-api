<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\LoanRepository;
use App\Domain\Errors\DomainError;

/** DELETE /books/{id}: logical removal (CA-03.5, ADR-003). A book with open loans is not retired (409). */
final readonly class RetireBookService
{
    public function __construct(
        private BookRepository $books,
        private LoanRepository $loans,
        private Clock $clock,
    ) {}

    public function retire(string $id): void
    {
        $book = $this->books->findActive($id) ?? throw new DomainError('NOT_FOUND', 'El libro no existe.');
        if ($this->loans->countUnreturnedForBook($id) > 0) {
            throw new DomainError('BOOK_HAS_OPEN_LOANS', "El libro «{$book->title}» tiene préstamos sin devolver y no se puede dar de baja.");
        }

        $this->books->save($book->retire($this->clock->now()));
    }
}
