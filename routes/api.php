<?php

use App\Adapters\Inbound\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Only the routes of api-contract.md. Prefix /api/v1 is set in bootstrap/app.php.

Route::get('/health', HealthController::class);
