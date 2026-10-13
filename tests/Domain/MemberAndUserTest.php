<?php

// T-06 evidence: "Ana " becomes "ana"; role admin through the API is rejected.

use App\Domain\Errors\DomainError;
use App\Domain\Id;
use App\Domain\Member;
use App\Domain\User;
use App\Domain\ValueObjects\DocumentNumber;

it('normalizes the username to lowercase and trimmed: "Ana " becomes "ana" (CA-02.2)', function () {
    $user = User::registerLibrarian(Id::new(), 'Ana ', 'argon2id-hash', 'librarian');
    expect($user->username)->toBe('ana')->and($user->role)->toBe('librarian');
});

it('rejects the admin role requested through the API with VALIDATION_ERROR (CA-02.3)', function () {
    try {
        User::registerLibrarian(Id::new(), 'eve', 'argon2id-hash', 'admin');
        $this->fail('expected VALIDATION_ERROR');
    } catch (DomainError $e) {
        expect($e->errorCode)->toBe('VALIDATION_ERROR')->and($e->details[0]['field'])->toBe('role');
    }
});

it('only accepts the roles admin and librarian', function () {
    expect(fn () => User::reconstitute(Id::new(), 'x', 'hash', 'superuser'))->toThrow(DomainError::class);
});

it('lets startup provision the admin from the environment (D-10)', function () {
    $admin = User::provisionAdmin(Id::new(), ' Admin ', 'argon2id-hash');
    expect($admin->isAdmin())->toBeTrue()->and($admin->username)->toBe('admin');
});

it('keeps only the password hash, never a clear password', function () {
    $user = User::registerLibrarian(Id::new(), 'ana', 'argon2id-hash', 'librarian');
    expect(array_keys(get_object_vars($user)))->not->toContain('password');
});

it('creates a member with a trimmed name and document (CA-05.1)', function () {
    $member = new Member(Id::new(), ' Laura Gómez ', new DocumentNumber(' 1020304050 '));
    expect($member->fullName)->toBe('Laura Gómez')->and($member->documentNumber->value)->toBe('1020304050');
});

it('requires the member document and name', function () {
    expect(fn () => new DocumentNumber('  '))->toThrow(DomainError::class)
        ->and(fn () => new Member(Id::new(), ' ', new DocumentNumber('1')))->toThrow(DomainError::class);
});
