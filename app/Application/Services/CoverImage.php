<?php

namespace App\Application\Services;

use App\Domain\Errors\DomainError;

/** Cover rules (CA-03.4, api-contract.md §4): JPEG or PNG validated by content, not by extension; max 2 MB. */
final class CoverImage
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    /** @return 'png'|'jpg'|null */
    public static function typeOf(string $bytes): ?string
    {
        return match (true) {
            str_starts_with($bytes, "\x89PNG\r\n\x1A\n") => 'png',
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'jpg',
            default => null,
        };
    }

    /** @return 'png'|'jpg' */
    public static function validate(string $bytes): string
    {
        if (strlen($bytes) > self::MAX_BYTES) {
            throw DomainError::validation('file', 'máximo 2 MB', 'La portada no puede superar 2 MB.');
        }

        return self::typeOf($bytes)
            ?? throw DomainError::validation('file', 'solo JPG o PNG', 'La portada debe ser una imagen JPG o PNG.');
    }
}
