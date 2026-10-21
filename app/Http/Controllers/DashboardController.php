<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\DebtSolutionsService;
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

    /**
     * High-performance aggregated endpoint: delivers summary, solutions,
     * partner eligibility and tips in a single round-trip.
     */
    public function full(Request $request, DebtSolutionsService $solutions): JsonResponse
    {
        $user = $request->user();

        return $this->ok([
            'summary' => $this->dashboard->summary($user),
            'avalanche' => $solutions->avalanche($user),
            'snowball' => $solutions->snowball($user),
            'eligibility' => $solutions->partnerEligibility($user),
            'cibilTips' => $solutions->cibilTips($user),
        ]);
    }
}
