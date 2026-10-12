<?php

namespace App\Jobs;

use App\Services\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessFlutterwaveWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Flutterwave retries failed webhooks a few times over ~2 hours. */
    public int $tries = 5;

    public int $backoff = 60;

    public function __construct(public array $payload) {}

    public function handle(PaymentService $payments): void
    {
        $result = $payments->handleWebhook($this->payload);

        Log::info('Flutterwave webhook processed', [
            'tx_ref' => $this->payload['data']['tx_ref'] ?? null,
            'ok' => $result['ok'],
            'message' => $result['message'],
        ]);
    }
}