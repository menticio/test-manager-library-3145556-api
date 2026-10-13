<?php

namespace App\Adapters\Outbound\Security;

use App\Application\Ports\Outbound\PasswordHasher;

/** D-09: Argon2id. The clear password is never stored nor logged (Article IX, RNF-3). */
final class Argon2idPasswordHasher implements PasswordHasher
{
    public function hash(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_ARGON2ID);
    }

    public function verify(string $plainPassword, string $hash): bool
    {
        return password_verify($plainPassword, $hash);
    }
}
