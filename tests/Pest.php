<?php

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Architecture');

// Integration and contract levels (plan.md §3): real PostgreSQL from infra.
// Each test starts from empty business tables INSIDE a transaction that is rolled back at the end, so demo data in the
// same database is untouched and never leaks into a test. The five seeded categories stay (they are part of the schema).
// The token key and the covers folder are generated per run: no secret lives in the repository (Article IX).
pest()->extend(TestCase::class)
    ->beforeEach(function () {
        config(['library.jwt_secret' => bin2hex(random_bytes(32)), 'library.covers_dir' => sys_get_temp_dir().'/library-covers-test']);
        DB::beginTransaction();
        DB::statement('TRUNCATE loan_item, loan, book, member, "user"');
    })
    ->afterEach(fn () => DB::rollBack())
    ->in('Integration', 'Contract');
