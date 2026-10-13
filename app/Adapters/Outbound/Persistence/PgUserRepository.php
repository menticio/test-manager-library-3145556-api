<?php

namespace App\Adapters\Outbound\Persistence;

use App\Application\Ports\Outbound\UserRepository;
use App\Domain\Errors\DomainError;
use App\Domain\User;
use Illuminate\Support\Facades\DB;

/** Users in library."user" (the query builder quotes the reserved name). password_hash never leaves this adapter except to the hasher. */
final class PgUserRepository implements UserRepository
{
    public function findByUsername(string $username): ?User
    {
        $row = DB::table('user')->where('username', $username)->first();

        return $row === null ? null : User::reconstitute($row->id, $row->username, $row->password_hash, $row->role);
    }

    public function usernameExists(string $username): bool
    {
        return DB::table('user')->where('username', $username)->exists();
    }

    public function add(User $user): void
    {
        Pg::unique(
            fn () => DB::table('user')->insert([
                'id' => $user->id,
                'username' => $user->username,
                'password_hash' => $user->passwordHash,
                'role' => $user->role,
            ]),
            new DomainError('USERNAME_TAKEN', "El nombre de usuario «{$user->username}» ya existe.",
                [['field' => 'username', 'issue' => 'repetido']]),
        );
    }
}
