<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendReminderRequest;
use App\Notifications\PaymentReminder;
use App\Support\Money;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /**
     * Queue a WhatsApp/SMS payment reminder to the customer's own registered
     * phone. A phone number in the request is ignored so the endpoint cannot
     * be used to message arbitrary numbers.
     */
    public function reminder(SendReminderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        if (! $user->phone) {
            abort(400, 'Add a phone number to your profile to receive reminders');
        }

        $notification = new PaymentReminder(
            channel: $data['channel'] ?? 'whatsapp',
            accountName: $data['accountName'],
            amountRupees: Money::num((float) $data['amountRupees']),
            dueDate: $data['dueDate'],
        );

        $user->notify($notification);

        // The frontend reads `payload` at the top level, not `data`
        return response()->json([
            'success' => true,
            'message' => strtoupper($notification->channel)." reminder queued for {$user->phone}",
            'payload' => [
                'channel' => $notification->channel,
                'recipient' => $user->phone,
                'accountName' => $notification->accountName,
                'amountRupees' => $notification->amountRupees,
                'dueDate' => $notification->dueDate,
                'content' => $notification->content($user),
                'dispatchStatus' => 'QUEUED',
                'sentAt' => now()->toJSON(),
            ],
        ], 202, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
