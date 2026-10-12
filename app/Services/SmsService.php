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

        // When Twilio is not configured, short-circuit to a logged "sent"
        // so the app remains fully testable without credentials.
        if (! $this->isConfigured()) {
            $record->update([
                'status' => SmsStatus::Sent,
                'sent_at' => now(),
                'error_message' => 'Twilio not configured; message logged only.',
            ]);

            Log::info('SMS (not sent, Twilio unconfigured)', [
                'to' => $toPhone,
                'type' => $type,
                'body' => $body,
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
                    'to' => $toPhone,
                    'body' => $body,
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
                'to' => $toPhone,
                'type' => $type,
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

        return $this->send(
            $record->to_phone,
            $record->body,
            $record->type,
            $record->registration_id,
            $record->ticket_id
        )->refresh();
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

        $record->update(array_filter([
            'status' => $mapped,
            'sent_at' => $record->sent_at ?? now(),
            'delivered_at' => $mapped === SmsStatus::Delivered ? now() : $record->delivered_at,
        ], fn ($v) => $v !== null));

        return $record->refresh();
    }

    public function isConfigured(): bool
    {
        return filled(config('services.twilio.account_sid'))
            && filled(config('services.twilio.auth_token'))
            && (filled(config('services.twilio.from_number')) || filled(config('services.twilio.messaging_service_sid')));
    }
}