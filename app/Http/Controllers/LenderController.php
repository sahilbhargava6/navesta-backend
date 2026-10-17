<?php

namespace App\Http\Controllers;

use App\Http\Resources\LenderResource;
use App\Models\Lender;
use App\Services\LenderCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LenderController extends Controller
{
    public function index(Request $request, LenderCatalog $catalog): JsonResponse
    {
        $request->validate(['type' => ['nullable', 'in:bank,nbfc']]);
        $type = $request->query('type');

        $lenders = $catalog->active()
            ->when($type, fn ($lenders) => $lenders->filter(fn (Lender $l) => $l->type->value === $type))
            ->values();

        return $this->ok(LenderResource::collection($lenders));
    }

    public function show(string $id): JsonResponse
    {
        $lender = Lender::find($id) ?? abort(404, 'Lender not found');

        return $this->ok(new LenderResource($lender));
    }
}
