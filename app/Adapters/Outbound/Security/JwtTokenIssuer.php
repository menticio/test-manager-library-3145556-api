<?php

namespace App\Adapters\Outbound\Security;

use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\TokenIssuer;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;
use Throwable;

/** D-11: JWT HS256 for 8 hours, key from JWT_SECRET. Claims: sub and role only, no personal data (api-contract.md §4). */
final class JwtTokenIssuer implements TokenIssuer
{
    private const TTL_SECONDS = 8 * 60 * 60;

    private const ALGORITHM = 'HS256';

    public function __construct(
        private readonly string $secret,
        private readonly Clock $clock,
    ) {
        if ($secret === '') {
            throw new InvalidArgumentException('JWT_SECRET is required.');
        }
    }

    public function issue(string $userId, string $role): string
    {
        $now = $this->clock->now()->getTimestamp();

        return JWT::encode(['sub' => $userId, 'role' => $role, 'iat' => $now, 'exp' => $now + self::TTL_SECONDS],
            $this->secret, self::ALGORITHM);
    }

    public function ttlSeconds(): int
    {
        return self::TTL_SECONDS;
    }

    public function verify(string $token): ?array
    {
        $previous = JWT::$timestamp;
        JWT::$timestamp = $this->clock->now()->getTimestamp();
        try {
            $claims = JWT::decode($token, new Key($this->secret, self::ALGORITHM));
        } catch (Throwable) {
            return null; // invalid, tampered or expired: the HTTP adapter answers 401
        } finally {
            JWT::$timestamp = $previous;
        }

        return is_string($claims->sub ?? null) && is_string($claims->role ?? null)
            ? ['sub' => $claims->sub, 'role' => $claims->role]
            : null;
    }
}
