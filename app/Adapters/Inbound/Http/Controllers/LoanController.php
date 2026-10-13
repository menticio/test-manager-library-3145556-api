<?php

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Input;
use App\Adapters\Inbound\Http\Present;
use App\Application\Services\GetLoanService;
use App\Application\Services\LendBooksService;
use App\Application\Services\ListLoansService;
use App\Application\Services\OverdueService;
use App\Application\Services\ReturnItemService;
use App\Domain\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** §3.6 loans (admin, librarian). */
final class LoanController
{
    public function store(Request $request, LendBooksService $service): JsonResponse
    {
        // CA-06.1: the loan is registered by the authenticated user.
        $loan = $service->lend(Input::string($request, 'member_id'), (string) $request->attributes->get('user_id'), Input::bookIds($request));

        return new JsonResponse(Present::loan($loan, Loan::OPEN), 201, [], JSON_UNESCAPED_UNICODE);
    }

    public function index(Request $request, ListLoansService $service): JsonResponse
    {
        $page = $service->list(Input::optionalQuery($request, 'from'), Input::optionalQuery($request, 'to'), Input::optionalQuery($request, 'status'),
            Input::optionalQuery($request, 'member_id'), Input::optionalQueryInt($request, 'page'), Input::optionalQueryInt($request, 'size'));

        return new JsonResponse(Present::page($page, fn (array $row) => Present::loan($row['loan'], $row['status'])), 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function show(string $id, GetLoanService $service): JsonResponse
    {
        $found = $service->get($id);

        return new JsonResponse(Present::loan($found['loan'], $found['status']), 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function returnItem(string $loanId, string $itemId, ReturnItemService $service): JsonResponse
    {
        return new JsonResponse(Present::item($service->return($loanId, $itemId)), 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function overdue(OverdueService $service): JsonResponse
    {
        $items = array_map(fn (array $row) => [
            'loan_id' => $row['loan_id'],
            'item_id' => $row['item_id'],
            'book_title' => $row['book_title'],
            'member_name' => $row['member_name'],
            'due_at' => Present::instant($row['due_at']),
            'days_late' => $row['days_late'],
        ], $service->list());

        return new JsonResponse(['items' => $items], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
