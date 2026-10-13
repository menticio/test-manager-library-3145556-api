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

it('registers exactly the routes of api-contract.md §3, no more and no less', function () {
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->flatMap(fn ($route) => array_map(fn ($method) => "{$method} /{$route->uri()}", array_diff($route->methods(), ['HEAD'])))
        ->sort()->values()->all();

    expect($routes)->toBe(collect([
        'POST /api/v1/auth/login', 'GET /api/v1/health',
        'POST /api/v1/users',
        'GET /api/v1/categories',
        'GET /api/v1/books', 'GET /api/v1/books/{id}', 'POST /api/v1/books', 'PUT /api/v1/books/{id}', 'DELETE /api/v1/books/{id}',
        'PUT /api/v1/books/{id}/cover', 'DELETE /api/v1/books/{id}/cover', 'GET /api/v1/books/{id}/cover',
        'GET /api/v1/members', 'GET /api/v1/members/{id}', 'POST /api/v1/members',
        'POST /api/v1/loans', 'GET /api/v1/loans', 'GET /api/v1/loans/{id}', 'POST /api/v1/loans/{loan_id}/items/{item_id}/return', 'GET /api/v1/loans/overdue',
        'GET /api/v1/reports/most-borrowed',
    ])->sort()->values()->all());
});
