<?php

namespace App\Domain;

use App\Domain\Errors\DomainError;

/**
 * Internal operator (spec.md §1). The domain never sees the clear password: only the hash made by the PasswordHasher port (D-09).
 * Role is admin or librarian. The admin is provisioned at startup from the environment (D-10);
 * through the API only librarians are created (CA-02.3).
 */
final readonly class User
{
    public const ADMIN = 'admin';

    public const LIBRARIAN = 'librarian';

    public const ROLES = [self::ADMIN, self::LIBRARIAN];

    public string $username;

    private function __construct(
        public string $id,
        string $username,
        public string $passwordHash,
        public string $role,
    ) {
        $this->username = self::normalize($username);
        if ($passwordHash === '') {
            throw new \LogicException('A user always has a password hash.');
        }
        if (! in_array($role, self::ROLES, true)) {
            throw DomainError::validation('role', 'admin o librarian', 'El rol debe ser admin o librarian.');
        }
    }

    /** POST /users: the API only creates librarians; asking for any other role is 422 (CA-02.1, CA-02.3). */
    public static function registerLibrarian(string $id, string $username, string $passwordHash, string $requestedRole): self
    {
        if ($requestedRole !== self::LIBRARIAN) {
            throw DomainError::validation('role', 'solo admite librarian', 'Por la API solo se pueden crear usuarios con rol librarian.');
        }

        return new self($id, $username, $passwordHash, self::LIBRARIAN);
    }

    /** Startup only (T-17): the initial admin comes from ADMIN_USERNAME / ADMIN_PASSWORD, never from the API. */
    public static function provisionAdmin(string $id, string $username, string $passwordHash): self
    {
        return new self($id, $username, $passwordHash, self::ADMIN);
    }

    public static function reconstitute(string $id, string $username, string $passwordHash, string $role): self
    {
        return new self($id, $username, $passwordHash, $role);
    }

    /** CA-02.2: stored lowercase and trimmed. */
    public static function normalize(string $username): string
    {
        $username = mb_strtolower(trim($username));
        if ($username === '' || mb_strlen($username) > 120) {
            throw DomainError::validation('username', 'obligatorio, máximo 120 caracteres',
                'El nombre de usuario es obligatorio y admite máximo 120 caracteres.');
        }

        return $username;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ADMIN;
    }
}
