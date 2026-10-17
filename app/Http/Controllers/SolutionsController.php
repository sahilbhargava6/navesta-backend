<?php

namespace App\Http\Controllers;

use App\Services\DebtSolutionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SolutionsController extends Controller
{
    public function __construct(private DebtSolutionsService $solutions) {}

    public function avalanche(Request $request): JsonResponse
    {
        return $this->ok($this->solutions->avalanche($request->user(), $this->extraMonthly($request)));
    }

    public function snowball(Request $request): JsonResponse
    {
        return $this->ok($this->solutions->snowball($request->user(), $this->extraMonthly($request)));
    }

    /**
     * Optional ?extraMonthlyRupees= on top of the minimum payments.
     */
    private function extraMonthly(Request $request): float
    {
        $request->validate(['extraMonthlyRupees' => ['nullable', 'numeric', 'min:0', 'max:10000000', 'decimal:0,2']]);

        return (float) $request->query('extraMonthlyRupees', 0);
    }

    public function eligibility(Request $request): JsonResponse
    {
        return $this->ok($this->solutions->partnerEligibility($request->user()));
    }

    public function amortization(Request $request, string $loanId): JsonResponse
    {
        $loan = $request->user()->loans()->find($loanId) ?? abort(404, 'Loan not found');

        return $this->ok($this->solutions->amortization($loan));
    }

    public function cibilTips(Request $request): JsonResponse
    {
        return $this->ok($this->solutions->cibilTips($request->user()));
    }
}
