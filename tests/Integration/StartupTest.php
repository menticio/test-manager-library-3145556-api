<?php

// T-17 evidence: from an empty user table, the startup command creates the admin and the admin can log in through the API.

use Illuminate\Support\Facades\DB;

it('provisions the admin at startup and logs in as admin through the API', function () {
    config(['library.admin_username' => 'root', 'library.admin_password' => 'from-env-pass']);
    expect(DB::table('user')->count())->toBe(0);

    $this->artisan('library:provision-admin')->assertSuccessful();
    $this->artisan('library:provision-admin')->assertSuccessful(); // idempotent

    expect(DB::table('user')->where('role', 'admin')->count())->toBe(1);
    $this->postJson('/api/v1/auth/login', ['username' => 'root', 'password' => 'from-env-pass'])
        ->assertOk()->assertJson(['role' => 'admin']);
});

it('fails the startup when the admin variables are missing', function () {
    config(['library.admin_username' => '', 'library.admin_password' => '']);
    $this->artisan('library:provision-admin')->assertFailed();
});
