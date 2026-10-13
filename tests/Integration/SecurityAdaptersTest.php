<?php

// T-14 evidence: Argon2id hashing and JWT HS256 for 8 hours with sub and role only (D-09, D-11, api-contract.md §4).

use App\Adapters\Outbound\Security\Argon2idPasswordHasher;
use App\Adapters\Outbound\Security\JwtTokenIssuer;
use Tests\Fakes\FixedClock;

beforeEach(function () {
    $this->clock = new FixedClock(new DateTimeImmutable('2026-10-13T12:00:00Z'));
    $this->issuer = new JwtTokenIssuer(str_repeat('k', 64), $this->clock);
});

it('hashes with Argon2id and never keeps the clear password (D-09, RNF-3)', function () {
    $hasher = new Argon2idPasswordHasher;
    $hash = $hasher->hash('s3cret-pass');

    expect($hash)->toStartWith('$argon2id$')->not->toContain('s3cret-pass')
        ->and($hasher->verify('s3cret-pass', $hash))->toBeTrue()
        ->and($hasher->verify('wrong', $hash))->toBeFalse()
        ->and($hasher->hash('s3cret-pass'))->not->toBe($hash); // salted
});

it('issues an HS256 token valid for 8 hours with sub and role only (CA-01.1, D-11)', function () {
    $token = $this->issuer->issue('user-id-1', 'librarian');
    [$header, $payload] = array_map(fn ($part) => json_decode(base64_decode(strtr($part, '-_', '+/')), true), explode('.', $token));

    expect($header['alg'])->toBe('HS256')
        ->and(array_keys($payload))->toEqualCanonicalizing(['sub', 'role', 'iat', 'exp'])
        ->and($payload['exp'] - $payload['iat'])->toBe(28800)
        ->and($this->issuer->ttlSeconds())->toBe(28800)
        ->and($this->issuer->verify($token))->toBe(['sub' => 'user-id-1', 'role' => 'librarian']);
});

it('rejects an expired, tampered or foreign token (CA-01.3)', function () {
    $token = $this->issuer->issue('user-id-1', 'librarian');
    $parts = explode('.', $token);
    $forged = $parts[0].'.'.rtrim(strtr(base64_encode(json_encode(['sub' => 'user-id-1', 'role' => 'admin', 'iat' => 1, 'exp' => 9999999999])), '+/', '-_'), '=').'.'.$parts[2];

    expect($this->issuer->verify($forged))->toBeNull()
        ->and((new JwtTokenIssuer(str_repeat('x', 64), $this->clock))->verify($token))->toBeNull()
        ->and($this->issuer->verify('not-a-token'))->toBeNull();

    $this->clock->advance('+8 hours +1 second');
    expect($this->issuer->verify($token))->toBeNull();
});

it('refuses to start without a JWT_SECRET (Article IX: secrets come from the environment)', function () {
    expect(fn () => new JwtTokenIssuer('', $this->clock))->toThrow(InvalidArgumentException::class);
});
