<?php

// T-17 (application level): the initial admin comes from the environment at startup, never from the API nor SQL (D-10).

use App\Application\Services\LoginService;
use App\Application\Services\ProvisionAdminService;
use Tests\Fakes\FakePasswordHasher;
use Tests\Fakes\FakeTokenIssuer;
use Tests\Fakes\InMemoryUserRepository;

beforeEach(function () {
    $this->users = new InMemoryUserRepository;
    $this->hasher = new FakePasswordHasher;
    $this->provision = new ProvisionAdminService($this->users, $this->hasher);
});

it('creates the admin from the environment values and lets it log in as admin', function () {
    expect($this->provision->provision('Root ', 'from-env-pass'))->toBeTrue();

    $login = (new LoginService($this->users, $this->hasher, new FakeTokenIssuer))->login('root', 'from-env-pass');
    expect($login['role'])->toBe('admin')
        ->and($this->users->findByUsername('root')->passwordHash)->not->toContain('from-env-pass');
});

it('is idempotent: a restart does not duplicate nor overwrite the admin', function () {
    $this->provision->provision('root', 'first');
    expect($this->provision->provision('root', 'second'))->toBeFalse()
        ->and($this->hasher->verify('first', $this->users->findByUsername('root')->passwordHash))->toBeTrue();
});

it('refuses to start without ADMIN_USERNAME or ADMIN_PASSWORD', function () {
    expect(fn () => $this->provision->provision('', 'x'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->provision->provision('root', ''))->toThrow(InvalidArgumentException::class);
});
