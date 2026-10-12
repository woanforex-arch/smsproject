<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessFlutterwaveWebhook;
use App\Services\FlutterwaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FlutterwaveController extends Controller
{
    public function __construct(private readonly FlutterwaveService $gateway) {}

    /**
     * Receive Flutterwave v3 event callbacks.
     *
     * v3 sends the configured secret hash verbatim in the `verif-hash`
     * header — there is no HMAC over the body in v3 (that is the v4 scheme).
     *
     * Always returns 200 for a verified request so Flutterwave does not retry
     * a callback we have durably accepted.
     */
    public function handle(Request $request): JsonResponse
    {
        if (! $this->gateway->isValidWebhookSignature($request->header('verif-hash'))) {
            Log::warning('Rejected Flutterwave webhook with an invalid verif-hash', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['ok' => false, 'error' => 'unauthorised'], 401);
        }

        $payload = $request->json()->all();

        if (($payload['event'] ?? null) !== 'charge.completed') {
            // Other events (transfers, subscriptions) are not our concern.
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        Log::info('Flutterwave charge.completed received', [
            'tx_ref' => $payload['data']['tx_ref'] ?? null,
            'status' => $payload['data']['status'] ?? null,
        ]);

        // Queue the work so a slow fulfilment never causes a webhook timeout
        // (Flutterwave retries a few times, 30 minutes apart).
        ProcessFlutterwaveWebhook::dispatch($payload);

        return response()->json(['ok' => true]);
    }
}