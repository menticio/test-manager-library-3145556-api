<?php

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Input;
use App\Adapters\Inbound\Http\Present;
use App\Application\Services\CreateLibrarianService;
use App\Application\Services\LoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** §3.1 POST /auth/login and §3.2 POST /users. */
final class AuthController
{
    public function login(Request $request, LoginService $service): JsonResponse
    {
        $username = $request->json('username');
        $password = $request->json('password');

        return new JsonResponse($service->login(is_string($username) ? $username : '', is_string($password) ? $password : ''));
    }

    public function createUser(Request $request, CreateLibrarianService $service): JsonResponse
    {
        $user = $service->create(Input::string($request, 'username'), Input::string($request, 'password'), Input::string($request, 'role'));

        return new JsonResponse(Present::user($user), 201);
    }
}
