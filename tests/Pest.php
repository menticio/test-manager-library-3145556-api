<?php

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

// Integration and contract levels (plan.md §3): real PostgreSQL from infra; each test is rolled back.
pest()->extend(TestCase::class)
    ->beforeEach(fn () => DB::beginTransaction())
    ->afterEach(fn () => DB::rollBack())
    ->in('Integration', 'Contract');
