<?php

namespace App\Adapters\Inbound\Http\Middleware;

use App\Application\Ports\Outbound\TokenIssuer;
use App\Domain\Errors\DomainError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** CA-01.3: every endpoint except login and health requires a Bearer token; without it, or invalid/expired, 401. */
final class AuthenticateToken
{
    public function __construct(private readonly TokenIssuer $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $claims = $token === null ? null : $this->tokens->verify($token);
        if ($claims === null) {
            throw new DomainError('INVALID_CREDENTIALS', 'Falta el token de acceso o no es válido.');
        }

        $request->attributes->set('user_id', $claims['sub']);
        $request->attributes->set('role', $claims['role']);

        return $next($request);
    }
}
