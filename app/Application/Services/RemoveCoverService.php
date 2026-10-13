<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\CoverStorage;
use App\Domain\Errors\DomainError;

/** DELETE /books/{id}/cover. data-model.md §7: first cover_key = null is committed, then the binary is deleted. */
final readonly class RemoveCoverService
{
    public function __construct(
        private BookRepository $books,
        private CoverStorage $covers,
    ) {}

    public function remove(string $id): void
    {
        $book = $this->books->findActive($id) ?? throw new DomainError('NOT_FOUND', 'El libro no existe.');
        if ($book->coverKey === null) {
            return;
        }

        $this->books->save($book->withoutCover());
        $this->covers->delete($book->coverKey);
    }
}
