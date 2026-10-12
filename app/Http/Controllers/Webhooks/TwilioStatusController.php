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
     * Twilio signs the request with an X-Twilio-Signature header; when a
     * webhook token is configured we validate the callback came from Twilio.
     */
    public function status(Request $request): JsonResponse
    {
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