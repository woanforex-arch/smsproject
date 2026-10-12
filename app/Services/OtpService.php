<?php

namespace App\Services;

use App\Models\OtpVerification;
use App\Models\SmsMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Issues, verifies and rate-limits SMS one-time passwords.
 */
class OtpService
{
    public function __construct(private readonly SmsService $sms) {}

    /**
     * Generate a new code for a phone number, persist its hash, and SMS it.
     * Enforces a resend cooldown to prevent SMS abuse.
     *
     * @return array{ok: bool, otp?: OtpVerification, code?: string, message?: string}
     */
    public function issue(
        string $phone,
        string $purpose = 'registration',
        ?string $context = null,
        ?int $registrationId = null
    ): array {
        if (! $this->cooldownPassed($phone)) {
            $wait = $this->cooldownRemaining($phone);

            return ['ok' => false, 'message' => "Please wait {$wait}s before requesting another code."];
        }

        // Invalidate any outstanding codes for this phone/purpose.
        OtpVerification::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->update(['verified_at' => now()]);

        $code = $this->generateCode();
        $ttl = (int) config('ticketing.otp.ttl_minutes');

        $otp = OtpVerification::create([
            'phone' => $phone,
            'code_hash' => OtpVerification::hashCode($code),
            'purpose' => $purpose,
            'context' => $context,
            'expires_at' => now()->addMinutes($ttl),
            'last_sent_at' => now(),
            'ip_address' => request()->ip(),
        ]);

        $this->rememberCooldown($phone);

        $this->sms->send(
            toPhone: $phone,
            body: $this->messageFor($code, $ttl),
            type: 'otp',
            registrationId: $registrationId,
        );

        Log::info('OTP issued', ['phone' => $phone, 'purpose' => $purpose, 'expires_at' => $otp->expires_at]);

        return ['ok' => true, 'otp' => $otp, 'code' => $code];
    }

    /**
     * Verify a submitted code against the latest outstanding OTP.
     *
     * @return array{ok: bool, otp?: OtpVerification, message?: string}
     */
    public function verify(string $phone, string $code, string $purpose = 'registration'): array
    {
        $otp = OtpVerification::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (! $otp) {
            return ['ok' => false, 'message' => 'No active verification code. Please request a new one.'];
        }

        if ($otp->isExpired()) {
            return ['ok' => false, 'message' => 'This code has expired. Please request a new one.'];
        }

        $maxAttempts = (int) config('ticketing.otp.max_attempts');

        if ($otp->attempts >= $maxAttempts) {
            return ['ok' => false, 'message' => 'Too many attempts. Please request a new code.'];
        }

        if (! $otp->matches($code)) {
            $otp->increment('attempts');
            $remaining = max(0, $maxAttempts - $otp->attempts);

            return ['ok' => false, 'message' => "Incorrect code. {$remaining} attempt(s) remaining."];
        }

        $otp->update(['verified_at' => now()]);

        return ['ok' => true, 'otp' => $otp];
    }

    private function generateCode(): string
    {
        $length = (int) config('ticketing.otp.length');

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    private function messageFor(string $code, int $ttl): string
    {
        return "Your verification code is {$code}. It expires in {$ttl} minutes. Do not share it with anyone.";
    }

    private function cooldownKey(string $phone): string
    {
        return 'otp:cooldown:'.$phone;
    }

    private function cooldownPassed(string $phone): bool
    {
        return ! Cache::has($this->cooldownKey($phone));
    }

    private function cooldownRemaining(string $phone): int
    {
        $seconds = (int) config('ticketing.otp.resend_cooldown_seconds');

        return (int) ceil($seconds - (Cache::get($this->cooldownKey($phone), time()) - time()));
    }

    private function rememberCooldown(string $phone): void
    {
        Cache::put(
            $this->cooldownKey($phone),
            time(),
            (int) config('ticketing.otp.resend_cooldown_seconds')
        );
    }
}