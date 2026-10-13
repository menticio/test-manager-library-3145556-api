<?php

use App\Adapters\Inbound\Http\Controllers\AuthController;
use App\Adapters\Inbound\Http\Controllers\BookController;
use App\Adapters\Inbound\Http\Controllers\HealthController;
use App\Adapters\Inbound\Http\Controllers\LoanController;
use App\Adapters\Inbound\Http\Controllers\MemberController;
use App\Adapters\Inbound\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// Exactly the routes of api-contract.md §3 (no more, no less). Prefix /api/v1 is set in bootstrap/app.php.

// §3.1 public
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/health', HealthController::class);

Route::middleware('token')->group(function () {
    // §3.2 users (admin)
    Route::post('/users', [AuthController::class, 'createUser'])->middleware('role:admin');

    // §3.3 categories and §3.4 books (authenticated to read, admin to change)
    Route::get('/categories', [BookController::class, 'categories']);
    Route::get('/books', [BookController::class, 'search']);
    Route::get('/books/{id}', [BookController::class, 'show']);
    Route::get('/books/{id}/cover', [BookController::class, 'cover']);
    Route::middleware('role:admin')->group(function () {
        Route::post('/books', [BookController::class, 'store']);
        Route::put('/books/{id}', [BookController::class, 'update']);
        Route::delete('/books/{id}', [BookController::class, 'destroy']);
        Route::put('/books/{id}/cover', [BookController::class, 'putCover']);
        Route::delete('/books/{id}/cover', [BookController::class, 'deleteCover']);
    });

    // §3.5 members and §3.6 loans (admin, librarian)
    Route::middleware('role:admin,librarian')->group(function () {
        Route::get('/members', [MemberController::class, 'search']);
        Route::get('/members/{id}', [MemberController::class, 'show']);
        Route::post('/members', [MemberController::class, 'store']);

        Route::post('/loans', [LoanController::class, 'store']);
        Route::get('/loans', [LoanController::class, 'index']);
        Route::get('/loans/overdue', [LoanController::class, 'overdue']);
        Route::get('/loans/{id}', [LoanController::class, 'show']);
        Route::post('/loans/{loan_id}/items/{item_id}/return', [LoanController::class, 'returnItem']);
    });

    // §3.7 report (admin)
    Route::get('/reports/most-borrowed', [ReportController::class, 'mostBorrowed'])->middleware('role:admin');
});
