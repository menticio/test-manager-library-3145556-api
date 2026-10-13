<?php

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Input;
use App\Application\Services\MostBorrowedReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** §3.7 GET /reports/most-borrowed (admin). */
final class ReportController
{
    public function mostBorrowed(Request $request, MostBorrowedReportService $service): JsonResponse
    {
        return new JsonResponse($service->report(Input::optionalQuery($request, 'from'), Input::optionalQuery($request, 'to')),
            200, [], JSON_UNESCAPED_UNICODE);
    }
}
