<?php

namespace App\Http\Controllers;

use App\Models\JourneyEvent;
use App\Support\JourneyCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class JourneyEventController extends Controller
{
    /**
     * Records the journey steps a visitor or customer reached (a small batch
     * from the website). Unknown steps are ignored, and the same step from
     * the same person is counted once per dedupe window, so refreshing a page
     * doesn't inflate the funnel. Signed-in customers are linked to their account.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'visitorId' => ['nullable', 'uuid'],
            'events' => ['required', 'array', 'min:1', 'max:20'],
            'events.*.journey' => ['required', 'string', 'max:30'],
            'events.*.step' => ['required', 'string', 'max:40'],
            'events.*.detail' => ['nullable', 'string', 'regex:/^[a-z0-9_-]{1,40}$/'],
        ]);

        $user = $request->user('sanctum');
        $visitorId = $data['visitorId'] ?? null;
        $who = $user ? 'u:'.$user->getKey() : ($visitorId ? 'v:'.$visitorId : null);
        if ($who === null) {
            abort(400, 'visitorId is required when not signed in');
        }

        $window = now()->addMinutes((int) config('navesta.journeys.dedupe_minutes'));
        $recorded = 0;

        foreach ($data['events'] as $event) {
            if (! JourneyCatalog::isValid($event['journey'], $event['step'])) {
                continue;
            }
            $detail = $event['detail'] ?? null;

            if (! Cache::add('journey-step:'.sha1("{$who}|{$event['journey']}|{$event['step']}|{$detail}"), 1, $window)) {
                continue; // already counted recently
            }

            JourneyEvent::create([
                'user_id' => $user?->getKey(),
                'visitor_id' => $visitorId,
                'journey' => $event['journey'],
                'step' => $event['step'],
                'detail' => $detail,
            ]);
            $recorded++;
        }

        return $this->ok(['recorded' => $recorded], null, 202);
    }
}
