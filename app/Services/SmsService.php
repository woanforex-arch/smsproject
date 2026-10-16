<?php

namespace App\Services;

use App\Enums\SmsStatus;
use App\Models\SmsMessage;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client as TwilioClient;

/**
 * Sends SMS via Twilio and records every attempt for audit/resend.
 */
class SmsService
{
    /**
     * Send an SMS, persist an audit row, and return that row.
     */
    public function send(
        string $toPhone,
        string $body,
        string $type = 'ticket_reference',
        ?int $registrationId = null,
        ?int $ticketId = null
    ): SmsMessage {
        $record = SmsMessage::create([
            'registration_id' => $registrationId,
            'ticket_id' => $ticketId,
            'type' => $type,
            'to_phone' => $toPhone,
            'body' => $body,
            'status' => SmsStatus::Queued,
            'attempts' => 1,
        ]);

        return $this->deliver($record);
    }

    /**
     * Re-send an existing message, incrementing its attempt counter and
     * clearing any prior error state.
     */
    public function resend(SmsMessage $record): SmsMessage
    {
        $record->update([
            'attempts' => $record->attempts + 1,
            'status' => SmsStatus::Queued,
            'error_code' => null,
            'error_message' => null,
        ]);

        return $this->deliver($record);
    }

    /**
     * Attempt delivery of an already-persisted message and record the outcome.
     */
    private function deliver(SmsMessage $record): SmsMessage
    {
        // When Twilio is not configured, short-circuit to a logged "sent"
        // so the app remains fully testable without credentials.
        if (! $this->isConfigured()) {
            $record->update([
                'status' => SmsStatus::Sent,
                'sent_at' => now(),
                'error_message' => 'Twilio not configured; message logged only.',
            ]);

            Log::info('SMS (not sent, Twilio unconfigured)', [
                'to' => $record->to_phone,
                'type' => $record->type,
                'body' => $record->body,
            ]);

            return $record->refresh();
        }

        try {
            $client = new TwilioClient(
                config('services.twilio.account_sid'),
                config('services.twilio.auth_token')
            );

            $messagingServiceSid = config('services.twilio.messaging_service_sid');
            $from = config('services.twilio.from_number');

            $message = $client->messages->create(
                array_filter([
                    'to' => $record->to_phone,
                    'body' => $record->body,
                    'messagingServiceSid' => $messagingServiceSid ?: null,
                    'from' => $messagingServiceSid ? null : $from,
                ])
            );

            $record->update([
                'provider_sid' => $message->sid,
                'status' => SmsStatus::Sent,
                'sent_at' => now(),
            ]);

            return $record->refresh();
        } catch (TwilioException $e) {
            Log::error('Twilio send failed', [
                'to' => $record->to_phone,
                'type' => $record->type,
                'message' => $e->getMessage(),
            ]);

            $record->update([
                'status' => SmsStatus::Failed,
                'error_code' => (string) $e->getCode(),
                'error_message' => $e->getMessage(),
            ]);

            return $record->refresh();
        }
    }

    /**
     * Update delivery status from a Twilio status callback webhook.
     */
    public function handleDeliveryStatusCallback(string $sid, string $status): ?SmsMessage
    {
        $record = SmsMessage::where('provider_sid', $sid)->first();

        if (! $record) {
            Log::warning('Twilio status callback for unknown SID', ['sid' => $sid]);

            return null;
        }

        $mapped = match ($status) {
            'queued' => SmsStatus::Queued,
            'sent' => SmsStatus::Sent,
            'delivered' => SmsStatus::Delivered,
            'undelivered' => SmsStatus::Undelivered,
            'failed' => SmsStatus::Failed,
            default => $record->status,
        };

        // Twilio can deliver callbacks out of order (or retry an old one); a
        // stale `queued` must never overwrite a later `delivered`.
        if ($this->statusRank($mapped) <= $this->statusRank($record->status)) {
            return $record;
        }

        $record->update(array_filter([
            'status' => $mapped,
            'sent_at' => $record->sent_at ?? now(),
            'delivered_at' => $mapped === SmsStatus::Delivered ? now() : $record->delivered_at,
        ], fn ($v) => $v !== null));

        return $record->refresh();
    }

    /**
     * Monotonic ordering of delivery states so late callbacks cannot regress
     * the row. Terminal states all share rank 2.
     */
    private function statusRank(SmsStatus $status): int
    {
        return match ($status) {
            SmsStatus::Queued => 0,
            SmsStatus::Sent => 1,
            SmsStatus::Delivered, SmsStatus::Undelivered, SmsStatus::Failed => 2,
        };
    }

    public function isConfigured(): bool
    {
        return filled(config('services.twilio.account_sid'))
            && filled(config('services.twilio.auth_token'))
            && (filled(config('services.twilio.from_number')) || filled(config('services.twilio.messaging_service_sid')));
    }
}