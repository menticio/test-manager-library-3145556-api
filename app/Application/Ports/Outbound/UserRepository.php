<?php

namespace App\Application\Ports\Outbound;

use App\Domain\User;

/** Port: users (Q10: user by name). */
interface UserRepository
{
    public function findByUsername(string $username): ?User;

    public function usernameExists(string $username): bool;

    /** Throws DomainError USERNAME_TAKEN if the engine unique index rejects it. */
    public function add(User $user): void;
}
