<?php

namespace Tests\Fakes;

use App\Application\Ports\Outbound\CoverStorage;

final class InMemoryCoverStorage implements CoverStorage
{
    /** @var array<string, string> */
    public array $files = [];

    public function put(string $bytes, string $extension): string
    {
        $key = bin2hex(random_bytes(8)).'.'.$extension;
        $this->files[$key] = $bytes;

        return $key;
    }

    public function get(string $key): ?string
    {
        return $this->files[$key] ?? null;
    }

    public function delete(string $key): void
    {
        unset($this->files[$key]);
    }
}
