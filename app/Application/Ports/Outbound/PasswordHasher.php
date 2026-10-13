<?php

namespace App\Application\Ports\Outbound;

/** Port: password hashing (D-09). The clear password never leaves the use case and is never logged (Article IX). */
interface PasswordHasher
{
    public function hash(string $plainPassword): string;

    public function verify(string $plainPassword, string $hash): bool;
}
