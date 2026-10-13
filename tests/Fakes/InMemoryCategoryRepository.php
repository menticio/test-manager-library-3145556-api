<?php

namespace Tests\Fakes;

use App\Application\Ports\Outbound\CategoryRepository;

/** The five categories of data-model.md §9. */
final class InMemoryCategoryRepository implements CategoryRepository
{
    public const NOVELA = '11111111-1111-4111-8111-111111111111';

    public const CIENCIA = '22222222-2222-4222-8222-222222222222';

    /** @var array<string, string> */
    public array $names = [
        self::NOVELA => 'Novela',
        self::CIENCIA => 'Ciencia',
        '33333333-3333-4333-8333-333333333333' => 'Tecnología',
        '44444444-4444-4444-8444-444444444444' => 'Historia',
        '55555555-5555-4555-8555-555555555555' => 'Infantil',
    ];

    public function all(): array
    {
        $rows = [];
        foreach ($this->names as $id => $name) {
            $rows[] = ['id' => $id, 'name' => $name];
        }
        usort($rows, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $rows;
    }

    public function nameOf(string $id): ?string
    {
        return $this->names[$id] ?? null;
    }
}
