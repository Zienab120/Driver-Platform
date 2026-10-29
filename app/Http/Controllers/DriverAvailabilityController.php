<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Stub showing the approval gate. Persist availability however your dispatch module needs. */
class DriverAvailabilityController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['online' => ['required', 'boolean']]);

        // TODO: persist/broadcast availability.
        return response()->json(['data' => ['online' => (bool) $data['online']]]);
    }
}
