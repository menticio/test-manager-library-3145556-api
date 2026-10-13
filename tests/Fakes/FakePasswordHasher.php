<?php

namespace Tests\Fakes;

use App\Application\Ports\Outbound\PasswordHasher;

/** Deterministic fake: "hash" is a prefix, so tests never need Argon2. */
final class FakePasswordHasher implements PasswordHasher
{
    public function hash(string $plainPassword): string
    {
        return 'hashed:'.strrev($plainPassword);
    }

    public function verify(string $plainPassword, string $hash): bool
    {
        return $hash === $this->hash($plainPassword);
    }
}
