<?php

namespace Tests\Fakes;

use App\Application\Ports\Outbound\UserRepository;
use App\Domain\User;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string, User> keyed by username */
    public array $users = [];

    public function findByUsername(string $username): ?User
    {
        return $this->users[$username] ?? null;
    }

    public function usernameExists(string $username): bool
    {
        return isset($this->users[$username]);
    }

    public function add(User $user): void
    {
        $this->users[$user->username] = $user;
    }
}
