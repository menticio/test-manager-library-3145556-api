<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\PasswordHasher;
use App\Application\Ports\Outbound\TokenIssuer;
use App\Application\Ports\Outbound\UserRepository;
use App\Domain\Errors\DomainError;

/** HU-01: log in and receive a token (POST /auth/login). */
final readonly class LoginService
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private TokenIssuer $tokens,
    ) {}

    /** @return array{access_token: string, token_type: string, expires_in: int, role: string} */
    public function login(string $username, string $password): array
    {
        $user = $this->users->findByUsername(mb_strtolower(trim($username)));

        // CA-01.2: same answer whether the user or the password failed.
        if ($user === null || ! $this->hasher->verify($password, $user->passwordHash)) {
            throw new DomainError('INVALID_CREDENTIALS', 'Usuario o contraseña incorrectos.');
        }

        return [
            'access_token' => $this->tokens->issue($user->id, $user->role),
            'token_type' => 'Bearer',
            'expires_in' => $this->tokens->ttlSeconds(),
            'role' => $user->role,
        ];
    }
}
