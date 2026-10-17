<?php

namespace App\Services;

use App\Models\Consent;
use App\Models\User;
use Illuminate\Support\Str;

class ConsentService
{
    public const PURPOSES = ['terms', 'privacy', 'reminders'];

    /**
     * Record a grant or withdrawal, with the notice version shown and where
     * it came from.
     */
    public function record(User $user, string $purpose, bool $granted): Consent
    {
        $request = request();

        return $user->consents()->create([
            'purpose' => $purpose,
            'notice_version' => (string) config("navesta.consent.{$purpose}", 'n/a'),
            'granted' => $granted,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);
    }

    /**
     * Current state per purpose (latest row wins).
     *
     * @return array<string, array{granted: bool, noticeVersion: string, at: string}>
     */
    public function current(User $user): array
    {
        return $user->consents()
            ->orderBy('created_at')
            ->get()
            ->keyBy('purpose')
            ->map(fn (Consent $c) => [
                'granted' => $c->granted,
                'noticeVersion' => $c->notice_version,
                'at' => $c->created_at->toJSON(),
            ])
            ->all();
    }
}
