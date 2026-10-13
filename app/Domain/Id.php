<?php

namespace App\Domain;

/** UUID v4 as text (api-contract.md §1). Ids are produced by the domain, never by the database (data-model.md §3). */
final class Id
{
    public static function new(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
