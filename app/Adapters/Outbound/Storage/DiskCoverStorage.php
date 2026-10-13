<?php

namespace App\Adapters\Outbound\Storage;

use App\Application\Ports\Outbound\CoverStorage;

/**
 * D-08: cover binaries on local disk (COVERS_DIR). The database keeps only the opaque key, never a path.
 * Swapping disk for S3 is a new adapter plus one line in the composition (Article IV, OCP).
 */
final class DiskCoverStorage implements CoverStorage
{
    public function __construct(private readonly string $directory) {}

    public function put(string $bytes, string $extension): string
    {
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }
        $key = bin2hex(random_bytes(16)).'.'.$extension;
        file_put_contents($this->path($key), $bytes, LOCK_EX);

        return $key;
    }

    public function get(string $key): ?string
    {
        $path = $this->safePath($key);
        if ($path === null || ! is_file($path)) {
            return null;
        }
        $bytes = file_get_contents($path);

        return $bytes === false ? null : $bytes;
    }

    public function delete(string $key): void
    {
        $path = $this->safePath($key);
        if ($path !== null && is_file($path)) {
            unlink($path);
        }
    }

    /** Only keys made by put() are accepted: no separators, so a key never escapes the folder. */
    private function safePath(string $key): ?string
    {
        return preg_match('/^[0-9a-f]{32}\.(png|jpg)$/', $key) === 1 ? $this->path($key) : null;
    }

    private function path(string $key): string
    {
        return rtrim($this->directory, '/\\').DIRECTORY_SEPARATOR.$key;
    }
}
