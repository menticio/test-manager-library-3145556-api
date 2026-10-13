<?php

namespace App\Application\Ports\Outbound;

/** Port: cover binaries outside the database (D-08). Returns an opaque key, never a path. */
interface CoverStorage
{
    public function put(string $bytes, string $extension): string;

    public function get(string $key): ?string;

    public function delete(string $key): void;
}
