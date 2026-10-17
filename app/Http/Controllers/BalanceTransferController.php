<?php

namespace App\Http\Controllers;

use App\Http\Requests\BtEligibilityRequest;
use App\Http\Requests\StoreBtApplicationRequest;
use App\Http\Resources\BtApplicationResource;
use App\Services\BalanceTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BalanceTransferController extends Controller
{
    public function __construct(private BalanceTransferService $balanceTransfers) {}

    public function eligibility(BtEligibilityRequest $request): JsonResponse
    {
        return $this->ok($this->balanceTransfers->eligibility($request->user(), $request->validated()));
    }

    public function offers(Request $request): JsonResponse
    {
        $query = $request->validate([
            'outstandingRupees' => ['nullable', 'numeric', 'gt:0'],
            'tenureMonths' => ['nullable', 'integer', 'min:1', 'max:600'],
            'type' => ['nullable', 'in:bank,nbfc'],
        ]);

        return $this->ok($this->balanceTransfers->rankedOffers(
            $request->user(),
            (float) ($query['outstandingRupees'] ?? 100000),
            (int) ($query['tenureMonths'] ?? 36),
            $query['type'] ?? null,
        ));
    }

    public function apply(StoreBtApplicationRequest $request): JsonResponse
    {
        $application = $this->balanceTransfers->apply($request->user(), $request->validated());

        return $this->ok(new BtApplicationResource($application), 'BT application submitted', 201);
    }

    public function applications(Request $request): JsonResponse
    {
        return $this->paginated($request, $request->user()->btApplications()->with('lender')->latest(), BtApplicationResource::class);
    }
}
