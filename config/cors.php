<?php

// api-contract.md §4: CORS only for the frontend origin configured by the environment.
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
    'allowed_origins' => [env('CORS_ORIGIN', 'http://localhost:5173')],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
