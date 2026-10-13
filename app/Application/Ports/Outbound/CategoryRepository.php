<?php

namespace App\Application\Ports\Outbound;

/** Port: the five fixed categories (Q4). Read only: seeded by the migration. */
interface CategoryRepository
{
    /** @return list<array{id: string, name: string}> ordered by name */
    public function all(): array;

    public function nameOf(string $id): ?string;
}
