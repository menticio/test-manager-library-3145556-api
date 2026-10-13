<?php

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

// Integration level (plan.md §3): real PostgreSQL from infra; each test is rolled back.
pest()->extend(TestCase::class)
    ->beforeEach(function () {
        config(['database.default' => 'pgsql']);
        DB::beginTransaction();
    })
    ->afterEach(fn () => DB::rollBack())
    ->in('Integration');
