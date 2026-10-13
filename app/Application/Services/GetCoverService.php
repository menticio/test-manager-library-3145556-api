<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\CoverStorage;
use App\Domain\Errors\DomainError;

/** GET /books/{id}/cover: the binary or 404 (api-contract.md §3.4). */
final readonly class GetCoverService
{
    public function __construct(
        private BookRepository $books,
        private CoverStorage $covers,
    ) {}

    /** @return array{bytes: string, content_type: string} */
    public function get(string $id): array
    {
        $book = $this->books->findActive($id);
        $bytes = $book?->coverKey === null ? null : $this->covers->get($book->coverKey);
        if ($bytes === null) {
            throw new DomainError('NOT_FOUND', 'El libro no tiene portada.');
        }

        return ['bytes' => $bytes, 'content_type' => CoverImage::typeOf($bytes) === 'png' ? 'image/png' : 'image/jpeg'];
    }
}
