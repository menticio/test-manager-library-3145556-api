<?php

use App\Adapters\Inbound\Http\ErrorResponse;
use App\Adapters\Inbound\Http\Middleware\AuthenticateToken;
use App\Adapters\Inbound\Http\Middleware\RequireRole;
use App\Domain\Errors\DomainError;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'token' => AuthenticateToken::class,
            'role' => RequireRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Business errors are expected answers, not incidents: never logged (and they never carry a password).
        $exceptions->dontReport([DomainError::class]);

        // architecture.md §5: only the HTTP adapter turns errors into the single body of api-contract.md §1.
        $exceptions->render(fn (Throwable $e, Request $request) => ErrorResponse::from($e));
    })->create();
