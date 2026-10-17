<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboard) {}

    public function summary(Request $request): JsonResponse
    {
        return $this->ok($this->dashboard->summary($request->user()));
    }

    public function history(Request $request): JsonResponse
    {
        return $this->ok($this->dashboard->history($request->user()));
    }
}
