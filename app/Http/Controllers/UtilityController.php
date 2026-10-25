<?php

namespace App\Http\Controllers;

use App\Services\PincodeService;
use Illuminate\Http\JsonResponse;

class UtilityController extends Controller
{
    public function __construct(
        private PincodeService $pincodes,
    ) {}

    /**
     * Resolve Indian PIN code to city, district and state for auto-filling address forms.
     */
    public function resolvePincode(string $pincode): JsonResponse
    {
        $result = $this->pincodes->lookup($pincode);

        if (! $result) {
            abort(404, 'Invalid or unrecognized 6-digit Indian PIN code');
        }

        return $this->ok($result, 'Pincode resolved successfully');
    }
}
