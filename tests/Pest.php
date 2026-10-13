<?php

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

// Integration and contract levels (plan.md §3): real PostgreSQL from infra; each test is rolled back.
// The token key and the covers folder are generated per run: no secret lives in the repository (Article IX).
pest()->extend(TestCase::class)
    ->beforeEach(function () {
        config(['library.jwt_secret' => bin2hex(random_bytes(32)), 'library.covers_dir' => sys_get_temp_dir().'/library-covers-test']);
        DB::beginTransaction();
    })
    ->afterEach(fn () => DB::rollBack())
    ->in('Integration', 'Contract');
