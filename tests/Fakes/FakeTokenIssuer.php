<?php

namespace Tests\Fakes;

use App\Application\Ports\Outbound\TokenIssuer;

final class FakeTokenIssuer implements TokenIssuer
{
    public function issue(string $userId, string $role): string
    {
        return "token:{$userId}:{$role}";
    }

    public function ttlSeconds(): int
    {
        return 28800;
    }

    public function verify(string $token): ?array
    {
        $parts = explode(':', $token);

        return count($parts) === 3 && $parts[0] === 'token' ? ['sub' => $parts[1], 'role' => $parts[2]] : null;
    }
}
