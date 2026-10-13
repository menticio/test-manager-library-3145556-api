<?php

namespace App\Adapters\Inbound\Http\Middleware;

use App\Domain\Errors\DomainError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * api-contract.md §4: the role is checked on every endpoint, BEFORE looking up the resource,
 * so a 403 never reveals whether the resource exists.
 */
final class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! in_array($request->attributes->get('role'), $roles, true)) {
            throw new DomainError('FORBIDDEN_ROLE', 'Su rol no tiene permiso para esta operación.');
        }

        return $next($request);
    }
}
