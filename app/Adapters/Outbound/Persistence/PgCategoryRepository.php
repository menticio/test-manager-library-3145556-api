<?php

namespace App\Adapters\Outbound\Persistence;

use App\Application\Ports\Outbound\CategoryRepository;
use Illuminate\Support\Facades\DB;

final class PgCategoryRepository implements CategoryRepository
{
    public function all(): array
    {
        return DB::table('category')->orderBy('name')->get(['id', 'name'])
            ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name])->all();
    }

    public function nameOf(string $id): ?string
    {
        if (! self::isUuid($id)) {
            return null;
        }

        return DB::table('category')->where('id', $id)->value('name');
    }

    public static function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }
}
