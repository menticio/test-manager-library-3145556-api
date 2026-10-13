<?php

// T-07 evidence: HU-01 and HU-02 pass against fakes (no database, no Laravel).

use App\Application\Services\CreateLibrarianService;
use App\Application\Services\LoginService;
use App\Domain\Errors\DomainError;
use App\Domain\Id;
use App\Domain\User;
use Tests\Fakes\FakePasswordHasher;
use Tests\Fakes\FakeTokenIssuer;
use Tests\Fakes\InMemoryUserRepository;

beforeEach(function () {
    $this->users = new InMemoryUserRepository;
    $this->hasher = new FakePasswordHasher;
    $this->login = new LoginService($this->users, $this->hasher, new FakeTokenIssuer);
    $this->createLibrarian = new CreateLibrarianService($this->users, $this->hasher);
    $this->users->add(User::registerLibrarian(Id::new(), 'ana', $this->hasher->hash('s3cret-pass'), 'librarian'));
});

function errorCodeOf(callable $call): ?string
{
    try {
        $call();
    } catch (DomainError $e) {
        return $e->errorCode;
    }

    return null;
}

it('returns a Bearer token valid for 8 hours with the role (CA-01.1)', function () {
    $result = $this->login->login('Ana ', 's3cret-pass');
    expect($result['token_type'])->toBe('Bearer')
        ->and($result['expires_in'])->toBe(28800)
        ->and($result['role'])->toBe('librarian')
        ->and($result['access_token'])->not->toBeEmpty();
});

it('answers INVALID_CREDENTIALS with the same message for a wrong user or a wrong password (CA-01.2)', function () {
    $messages = [];
    foreach ([['nobody', 's3cret-pass'], ['ana', 'wrong']] as [$user, $pass]) {
        try {
            $this->login->login($user, $pass);
        } catch (DomainError $e) {
            expect($e->errorCode)->toBe('INVALID_CREDENTIALS');
            $messages[] = $e->getMessage();
        }
    }
    expect($messages)->toHaveCount(2)->and($messages[0])->toBe($messages[1]);
});

it('creates a librarian with a normalized username and only the hash stored (CA-02.1, CA-02.2)', function () {
    $user = $this->createLibrarian->create(' Luis ', 'another-pass', 'librarian');
    expect($user->username)->toBe('luis')
        ->and($user->role)->toBe('librarian')
        ->and($user->passwordHash)->not->toContain('another-pass')
        ->and($this->users->usernameExists('luis'))->toBeTrue();
});

it('rejects a repeated username after normalization with USERNAME_TAKEN (CA-02.2)', function () {
    expect(errorCodeOf(fn () => $this->createLibrarian->create('ANA ', 'x-pass', 'librarian')))->toBe('USERNAME_TAKEN');
});

it('rejects the admin role through the API with 422 VALIDATION_ERROR, not 403 (CA-02.3)', function () {
    expect(errorCodeOf(fn () => $this->createLibrarian->create('eve', 'x-pass', 'admin')))->toBe('VALIDATION_ERROR')
        ->and($this->users->usernameExists('eve'))->toBeFalse();
});

it('requires a password', function () {
    expect(errorCodeOf(fn () => $this->createLibrarian->create('eve', '', 'librarian')))->toBe('VALIDATION_ERROR');
});
