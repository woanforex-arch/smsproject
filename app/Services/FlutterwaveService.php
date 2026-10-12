<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client for the Flutterwave v3 API.
 *
 * v3 has a single base URL; sandbox vs live is selected by the API key prefix
 * (test keys contain `_TEST`), not by the hostname.
 */
class FlutterwaveService
{
    public function baseUrl(): string
    {
        return rtrim((string) config('services.flutterwave.base_url'), '/');
    }

    /**
     * Create a hosted checkout link and return it.
     *
     * The create response contains no transaction id, so the caller must
     * persist tx_ref before calling this.
     *
     * @throws PaymentGatewayException
     */
    public function createPaymentLink(array $payload): string
    {
        $data = $this->request('post', '/payments', $payload);

        $link = $data['link'] ?? null;

        if (! is_string($link) || $link === '') {
            throw new PaymentGatewayException('Flutterwave did not return a checkout link.');
        }

        return $link;
    }

    /**
     * Verify a transaction by our own reference.
     *
     * Note: GET /v3/transactions/{tx_ref}/verify is NOT valid in v3 — it only
     * accepts the numeric transaction id. The by-reference route is separate.
     *
     * @throws PaymentGatewayException
     */
    public function verifyByReference(string $txRef): array
    {
        return $this->request('get', '/transactions/verify_by_reference', null, [
            'tx_ref' => $txRef,
        ]);
    }

    /**
     * Verify a transaction by its numeric Flutterwave id.
     *
     * @throws PaymentGatewayException
     */
    public function verifyById(string $transactionId): array
    {
        return $this->request('get', '/transactions/'.rawurlencode($transactionId).'/verify');
    }

    /**
     * Disable a previously created checkout link.
     */
    public function disablePaymentLink(string $checkoutUrl): bool
    {
        try {
            $this->request('post', '/payments/link/disable', ['link' => $checkoutUrl]);

            return true;
        } catch (PaymentGatewayException $e) {
            Log::warning('Could not disable Flutterwave payment link', ['message' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Validate an incoming webhook request.
     *
     * Flutterwave v3 sends the configured secret hash verbatim in the
     * `verif-hash` header — there is no HMAC over the payload body in v3.
     */
    public function isValidWebhookSignature(?string $header): bool
    {
        $expected = (string) config('services.flutterwave.secret_hash');

        if ($expected === '' || $header === null || $header === '') {
            return false;
        }

        return hash_equals($expected, $header);
    }

    public function isConfigured(): bool
    {
        return filled(config('services.flutterwave.secret_key'))
            && filled(config('services.flutterwave.secret_hash'));
    }

    /**
     * Perform an authenticated API call and return the decoded `data` block.
     *
     * @throws PaymentGatewayException
     */
    private function request(string $method, string $path, ?array $payload = null, array $query = []): array
    {
        $url = $this->baseUrl().$path;

        try {
            $client = Http::withToken((string) config('services.flutterwave.secret_key'))
                ->acceptJson()
                ->asJson();

            $response = $method === 'get'
                ? $client->get($url, $query)
                : $client->post($url, $payload ?? []);
        } catch (ConnectionException $e) {
            Log::error('Flutterwave connection failure', ['url' => $url, 'message' => $e->getMessage()]);

            throw new PaymentGatewayException('Could not reach Flutterwave. Please try again.', previous: $e);
        }

        return $this->decode($response, $url);
    }

    /**
     * @throws PaymentGatewayException
     */
    private function decode(Response $response, string $url): array
    {
        $body = $response->json() ?? [];

        // A "not found" from Flutterwave is an HTTP 400 with status=error, so
        // the status field — not the HTTP code — is what must be checked.
        if (($body['status'] ?? null) !== 'success') {
            $message = $body['message'] ?? 'Unknown error from Flutterwave.';

            Log::error('Flutterwave API error', [
                'url' => $url,
                'http_status' => $response->status(),
                'message' => $message,
            ]);

            throw new PaymentGatewayException($message);
        }

        return $body['data'] ?? [];
    }
}