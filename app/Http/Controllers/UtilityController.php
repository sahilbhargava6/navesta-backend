<?php

namespace App\Http\Controllers;

use App\Services\PincodeService;
use App\Support\PincodeLookup;
use Illuminate\Http\JsonResponse;

class UtilityController extends Controller
{
    public function __construct(
        private PincodeService $pincodes,
    ) {}

    /**
     * Verify an Indian PIN code and return its city, district and state for
     * auto-filling address forms. 404 = no such pincode; 503 = could not be
     * checked right now (the form should let the customer type it in).
     */
    public function resolvePincode(string $pincode): JsonResponse
    {
        $result = $this->pincodes->lookup($pincode);

        return match ($result->status) {
            PincodeLookup::VERIFIED => $this->ok($result->toArray(), 'Pincode verified'),
            PincodeLookup::NOT_FOUND => abort(404, "We couldn't find this pincode. Please check it."),
            default => abort(503, "We couldn't check this pincode right now. Please enter your city and state."),
        };
    }
}
