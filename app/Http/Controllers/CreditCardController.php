<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCreditCardRequest;
use App\Http\Requests\UpdateCreditCardRequest;
use App\Http\Resources\CreditCardResource;
use App\Models\CreditCard;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditCardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->paginated($request, $request->user()->creditCards()->active()->latest(), CreditCardResource::class);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return $this->ok(new CreditCardResource($this->findCard($request, $id)));
    }

    public function store(StoreCreditCardRequest $request): JsonResponse
    {
        $data = $request->validated();

        $card = $request->user()->creditCards()->create([
            'bank_name' => $data['bankName'],
            'card_name' => $data['cardName'],
            'last_four' => $data['lastFour'] ?? null,
            'credit_limit_paise' => Money::rupeesToPaise($data['creditLimitRupees']),
            'outstanding_paise' => Money::rupeesToPaise($data['outstandingRupees'] ?? 0),
            'apr_bps' => Money::percentToBps($data['aprPercent']),
            'min_payment_paise' => empty($data['minPaymentRupees']) ? null : Money::rupeesToPaise($data['minPaymentRupees']),
            'due_day' => $data['dueDay'] ?? null,
        ]);

        return $this->ok(new CreditCardResource($card), 'Credit card added', 201);
    }

    public function update(UpdateCreditCardRequest $request, string $id): JsonResponse
    {
        $card = $this->findCard($request, $id);
        $data = $request->validated();

        $fields = [
            'bankName' => fn ($v) => ['bank_name' => $v],
            'cardName' => fn ($v) => ['card_name' => $v],
            'lastFour' => fn ($v) => ['last_four' => $v],
            'creditLimitRupees' => fn ($v) => ['credit_limit_paise' => Money::rupeesToPaise($v)],
            'outstandingRupees' => fn ($v) => ['outstanding_paise' => Money::rupeesToPaise($v)],
            'aprPercent' => fn ($v) => ['apr_bps' => Money::percentToBps($v)],
            'minPaymentRupees' => fn ($v) => ['min_payment_paise' => Money::rupeesToPaise($v)],
            'dueDay' => fn ($v) => ['due_day' => $v],
        ];

        foreach ($fields as $key => $map) {
            if (array_key_exists($key, $data)) {
                $card->fill($map($data[$key]));
            }
        }
        $card->save();

        return $this->ok(new CreditCardResource($card), 'Credit card updated');
    }

    /**
     * Soft delete: the card is marked closed and drops out of active lists.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $card = $this->findCard($request, $id);
        $card->update(['status' => 'closed']);

        return $this->ok(['id' => $card->id, 'status' => 'closed'], 'Credit card deleted');
    }

    private function findCard(Request $request, string $id): CreditCard
    {
        return $request->user()->creditCards()->find($id) ?? abort(404, 'Credit card not found');
    }
}
