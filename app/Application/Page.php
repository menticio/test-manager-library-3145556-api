<?php

namespace App\Application;

use App\Domain\Errors\DomainError;

/** Paginated result: { items, page, size, total } (api-contract.md §1). size max 50. */
final readonly class Page
{
    public const MAX_SIZE = 50;

    /** @param list<mixed> $items */
    public function __construct(
        public array $items,
        public int $page,
        public int $size,
        public int $total,
    ) {}

    /** @return array{0: int, 1: int} validated [page, size] */
    public static function validate(?int $page, ?int $size): array
    {
        $page ??= 1;
        $size ??= 20;
        if ($page < 1) {
            throw DomainError::validation('page', 'debe ser mayor o igual a 1', 'La página debe ser mayor o igual a 1.');
        }
        if ($size < 1 || $size > self::MAX_SIZE) {
            throw DomainError::validation('size', 'entre 1 y 50', 'El tamaño de página debe estar entre 1 y 50.');
        }

        return [$page, $size];
    }
}
