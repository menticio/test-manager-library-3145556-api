<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\PasswordHasher;
use App\Application\Ports\Outbound\UserRepository;
use App\Domain\Id;
use App\Domain\User;
use InvalidArgumentException;

/**
 * D-10: the deployment provisions the admin from ADMIN_USERNAME / ADMIN_PASSWORD at startup.
 * Nobody grants admin at runtime (spec.md §1) and the database cannot produce the hash (data-model.md §9).
 */
final readonly class ProvisionAdminService
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
    ) {}

    /** @return bool true when the admin was created, false when it already existed (idempotent restart) */
    public function provision(string $username, string $password): bool
    {
        if (trim($username) === '' || $password === '') {
            throw new InvalidArgumentException('ADMIN_USERNAME and ADMIN_PASSWORD are required.');
        }
        if ($this->users->usernameExists(User::normalize($username))) {
            return false;
        }

        $this->users->add(User::provisionAdmin(Id::new(), $username, $this->hasher->hash($password)));

        return true;
    }
}
