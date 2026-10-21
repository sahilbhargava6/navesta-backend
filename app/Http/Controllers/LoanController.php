<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLoanRequest;
use App\Http\Requests\UpdateLoanRequest;
use App\Http\Resources\LoanResource;
use App\Models\Loan;
use App\Services\DebtSolutionsService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->paginated($request, $request->user()->loans()->active()->latest(), LoanResource::class);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return $this->ok(new LoanResource($this->findLoan($request, $id)))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private')
            ->header('Pragma', 'no-cache');
    }

    public function analysis(Request $request, string $id, DebtSolutionsService $solutions): JsonResponse
    {
        return $this->ok($solutions->loanAnalysis($this->findLoan($request, $id)))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private')
            ->header('Pragma', 'no-cache');
    }

    public function store(StoreLoanRequest $request): JsonResponse
    {
        $data = $request->validated();

        $loan = $request->user()->loans()->create([
            'lender_name' => $data['lenderName'],
            'loan_type' => $data['loanType'],
            'principal_paise' => Money::rupeesToPaise($data['principalRupees']),
            'outstanding_paise' => Money::rupeesToPaise($data['outstandingRupees']),
            'interest_rate_bps' => Money::percentToBps($data['interestRatePercent']),
            'emi_paise' => Money::rupeesToPaise($data['emiRupees']),
            'tenure_months' => $data['tenureMonths'],
            'tenure_remaining_months' => $data['tenureRemainingMonths'],
            'next_due_date' => $data['nextDueDate'] ?? null,
        ]);

        return $this->ok(new LoanResource($loan), 'Loan account added', 201);
    }

    public function update(UpdateLoanRequest $request, string $id): JsonResponse
    {
        $loan = $this->findLoan($request, $id);
        $data = $request->validated();

        $fields = [
            'lenderName' => fn ($v) => ['lender_name' => $v],
            'loanType' => fn ($v) => ['loan_type' => $v],
            'principalRupees' => fn ($v) => ['principal_paise' => Money::rupeesToPaise($v)],
            'outstandingRupees' => fn ($v) => ['outstanding_paise' => Money::rupeesToPaise($v)],
            'interestRatePercent' => fn ($v) => ['interest_rate_bps' => Money::percentToBps($v)],
            'emiRupees' => fn ($v) => ['emi_paise' => Money::rupeesToPaise($v)],
            'tenureMonths' => fn ($v) => ['tenure_months' => $v],
            'tenureRemainingMonths' => fn ($v) => ['tenure_remaining_months' => $v],
            'nextDueDate' => fn ($v) => ['next_due_date' => $v ?: null],
        ];

        foreach ($fields as $key => $map) {
            if (array_key_exists($key, $data)) {
                $loan->fill($map($data[$key]));
            }
        }

        if ($loan->outstanding_paise > $loan->principal_paise) {
            abort(400, 'Outstanding amount cannot exceed the principal');
        }
        if ($loan->tenure_remaining_months > $loan->tenure_months) {
            abort(400, 'Tenure remaining cannot exceed the tenure');
        }

        $loan->save();

        return $this->ok(new LoanResource($loan), 'Loan details updated');
    }

    /**
     * Soft delete: the loan is marked closed and drops out of active lists.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $loan = $this->findLoan($request, $id);
        $loan->update(['status' => 'closed']);

        return $this->ok(['id' => $loan->id, 'status' => 'closed'], 'Loan deleted');
    }

    private function findLoan(Request $request, string $id): Loan
    {
        return $request->user()->loans()->find($id) ?? abort(404, 'Loan not found');
    }
}
