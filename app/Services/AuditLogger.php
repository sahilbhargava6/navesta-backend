<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * Record an action. The actor defaults to the signed-in user (API token,
     * customer session or admin panel); console/queue work is "system".
     */
    public function record(string $action, ?Model $subject = null, array $metadata = [], ?User $actor = null): AuditLog
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        // Only a user the current request already authenticated: never trigger
        // authentication from here (that would cache a user on the guard)
        if ($actor === null && Auth::hasUser()) {
            $user = Auth::user();
            $actor = $user instanceof User ? $user : null;
        }

        return AuditLog::create([
            'actor_id' => $actor?->getKey(),
            'actor_role' => match (true) {
                $actor === null && $request === null => 'system',
                $actor === null => 'guest',
                $actor->isAdmin() => 'admin',
                default => 'customer',
            },
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey() !== null ? (string) $subject->getKey() : null,
            'metadata' => $metadata ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
        ]);
    }
}
