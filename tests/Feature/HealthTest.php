<?php

// T-01 evidence: GET /api/v1/health answers {"status":"ok"} without a token (api-contract.md §3.1).

it('answers ok on the public health endpoint', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertExactJson(['status' => 'ok']);
});

it('does not expose Laravel default routes outside the contract', function () {
    $this->get('/')->assertNotFound();
    $this->get('/up')->assertNotFound();
});

it('registers no route outside api-contract.md', function () {
    $uris = collect(app('router')->getRoutes()->getRoutes())->map->uri()->all();

    expect($uris)->toBe(['api/v1/health']);
});
