<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\PasswordHasher;
use App\Application\Ports\Outbound\UserRepository;
use App\Domain\Errors\DomainError;
use App\Domain\Id;
use App\Domain\User;

/** HU-02: the admin creates a librarian (POST /users). */
final readonly class CreateLibrarianService
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
    ) {}

    public function create(string $username, string $password, string $role): User
    {
        if ($password === '') {
            throw DomainError::validation('password', 'obligatoria', 'La contraseña es obligatoria.');
        }

        // CA-02.3: role admin through the API -> 422 (checked by the domain before any hashing work matters).
        $user = User::registerLibrarian(Id::new(), $username, $this->hasher->hash($password), $role);

        // CA-02.2: username unique after normalization -> 409.
        if ($this->users->usernameExists($user->username)) {
            throw new DomainError('USERNAME_TAKEN', "El nombre de usuario «{$user->username}» ya existe.",
                [['field' => 'username', 'issue' => 'repetido']]);
        }

        $this->users->add($user);

        return $user;
    }
}
