<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\CoverStorage;
use App\Domain\Book;
use App\Domain\Errors\DomainError;

/** PUT /books/{id}/cover: stores the binary outside the database and keeps only an opaque key (D-08). */
final readonly class UploadCoverService
{
    public function __construct(
        private BookRepository $books,
        private CoverStorage $covers,
    ) {}

    public function upload(string $id, string $bytes): Book
    {
        $book = $this->books->findActive($id) ?? throw new DomainError('NOT_FOUND', 'El libro no existe.');
        $extension = CoverImage::validate($bytes);

        $previousKey = $book->coverKey;
        $book = $book->withCover($this->covers->put($bytes, $extension));
        $this->books->save($book);

        // data-model.md §7: the old binary is deleted only after the new key is stored.
        if ($previousKey !== null) {
            $this->covers->delete($previousKey);
        }

        return $book;
    }
}
