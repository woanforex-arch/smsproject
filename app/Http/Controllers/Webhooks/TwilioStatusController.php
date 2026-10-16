<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TwilioStatusController extends Controller
{
    public function __construct(private readonly SmsService $sms) {}

    /**
     * Receive Twilio message status callbacks.
     *
     * When TWILIO_WEBHOOK_TOKEN is configured the callback must carry it (as a
     * `token` query/body parameter or an X-Webhook-Token header). Without a
     * configured token — e.g. local/tests — callbacks are accepted as before.
     */
    public function status(Request $request): JsonResponse
    {
        $configuredToken = config('services.twilio.webhook_token');

        if (filled($configuredToken)) {
            $provided = (string) ($request->header('X-Webhook-Token') ?: $request->input('token', ''));

            if (! hash_equals((string) $configuredToken, $provided)) {
                Log::warning('Rejected Twilio status callback with an invalid webhook token');

                return response()->json(['ok' => false, 'error' => 'invalid token'], 403);
            }
        }

        $sid = (string) $request->input('MessageSid', $request->input('SmsSid', ''));
        $status = (string) $request->input('MessageStatus', $request->input('SmsStatus', ''));

        if ($sid === '') {
            return response()->json(['ok' => false, 'error' => 'missing MessageSid'], 400);
        }

        $message = $this->sms->handleDeliveryStatusCallback($sid, $status);

        Log::info('Twilio status callback', [
            'sid' => $sid,
            'status' => $status,
            'matched' => $message !== null,
        ]);

        // Always return 200 so Twilio does not retry a callback we handled.
        return response()->json(['ok' => true]);
    }
}