<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\CategoryRepository;

/** GET /categories: the five categories ordered by name. */
final readonly class ListCategoriesService
{
    public function __construct(private CategoryRepository $categories) {}

    /** @return list<array{id: string, name: string}> */
    public function list(): array
    {
        return $this->categories->all();
    }
}
