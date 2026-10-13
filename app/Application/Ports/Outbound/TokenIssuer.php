<?php

namespace App\Application\Ports\Outbound;

/** Port: access tokens (D-11). Token carries sub and role only, no personal data (api-contract.md §4). */
interface TokenIssuer
{
    public function issue(string $userId, string $role): string;

    /** Lifetime in seconds: 8 hours (CA-01.1). */
    public function ttlSeconds(): int;

    /** @return array{sub: string, role: string}|null null when the token is invalid or expired */
    public function verify(string $token): ?array;
}
