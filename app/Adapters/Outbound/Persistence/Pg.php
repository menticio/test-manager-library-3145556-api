<?php

namespace App\Adapters\Outbound\Persistence;

use App\Domain\Errors\DomainError;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/** Small helpers shared by the PostgreSQL adapters (schema "library", search_path set by config). */
final class Pg
{
    public static function toTimestamp(?DateTimeImmutable $value): ?string
    {
        return $value?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }

    public static function fromTimestamp(?string $value): ?DateTimeImmutable
    {
        return $value === null ? null : (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
    }

    /** Escapes % and _ so user text is matched literally inside ILIKE. */
    public static function likeContains(string $text): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text).'%';
    }

    /**
     * The engine is the last barrier (Article VI): a unique index violation becomes the contract error.
     * The write runs in its own transaction (a savepoint when nested) so a violation never aborts an outer transaction.
     */
    public static function unique(Closure $write, DomainError $whenTaken): void
    {
        try {
            DB::transaction($write);
        } catch (UniqueConstraintViolationException) {
            throw $whenTaken;
        }
    }
}
