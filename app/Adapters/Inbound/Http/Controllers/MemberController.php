<?php

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Input;
use App\Adapters\Inbound\Http\Present;
use App\Application\Services\CreateMemberService;
use App\Application\Services\GetMemberService;
use App\Application\Services\SearchMembersService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** §3.5 members (admin, librarian). */
final class MemberController
{
    public function search(Request $request, SearchMembersService $service): JsonResponse
    {
        $page = $service->search(Input::optionalQuery($request, 'q'), Input::optionalQueryInt($request, 'page'), Input::optionalQueryInt($request, 'size'));

        return new JsonResponse(Present::page($page, [Present::class, 'member']), 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function show(string $id, GetMemberService $service): JsonResponse
    {
        $found = $service->get($id);

        return new JsonResponse(Present::member($found['member']) + ['active_loans' => $found['active_loans']], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function store(Request $request, CreateMemberService $service): JsonResponse
    {
        $member = $service->create(Input::string($request, 'full_name'), Input::string($request, 'document_number'));

        return new JsonResponse(Present::member($member), 201, [], JSON_UNESCAPED_UNICODE);
    }
}
