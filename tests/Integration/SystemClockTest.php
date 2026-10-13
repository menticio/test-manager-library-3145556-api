<?php

// T-21 support (plan.md §4): the real clock can be shifted by CLOCK_OFFSET_DAYS so the seeder creates overdue loans
// through the API, without SQL and without waiting 14 days. Default 0: production time is untouched.

use App\Adapters\Outbound\Clock\SystemClock;
use App\Application\Ports\Outbound\Clock;

it('returns the current UTC instant with no offset', function () {
    $now = (new SystemClock)->now();
    expect($now->getTimezone()->getName())->toBe('UTC')
        ->and(abs($now->getTimestamp() - time()))->toBeLessThan(5);
});

it('shifts the instant by whole days when an offset is configured', function () {
    $past = (new SystemClock(-20))->now();
    expect(intdiv(time() - $past->getTimestamp(), 86400))->toBe(20);
});

it('is wired with CLOCK_OFFSET_DAYS from the environment (default 0)', function () {
    config(['library.clock_offset_days' => -3]);
    app()->forgetInstance(Clock::class);
    expect(intdiv(time() - app(Clock::class)->now()->getTimestamp(), 86400))->toBe(3);
});
