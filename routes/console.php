<?php

use App\Models\Registration;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled maintenance
|--------------------------------------------------------------------------
*/

Schedule::command('auth:clear-resets')->everyFifteenMinutes();

/*
|--------------------------------------------------------------------------
| Event reminders
|--------------------------------------------------------------------------
|
| Send registered guests a reminder 24 hours before their event. Run this
| hourly so the 24-hour window is always covered exactly once.
|
*/

Schedule::command('events:send-reminders --hours=24')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

/*
|--------------------------------------------------------------------------
| Payment reconciliation
|--------------------------------------------------------------------------
|
| Safety net for webhooks that never arrived or failed to process. Anything
| still pending after two hours is re-checked against Flutterwave.
|
*/

Schedule::command('payments:reconcile --minutes=120')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

/*
|--------------------------------------------------------------------------
| Housekeeping
|--------------------------------------------------------------------------
*/

// Release inventory held by registrations abandoned mid-OTP-verification.
Schedule::call(function () {
    $expired = Registration::query()
        ->where('status', 'pending_otp')
        ->where('created_at', '<', now()->subHours(6))
        ->get();

    foreach ($expired as $registration) {
        $registration->update(['status' => 'cancelled']);
        $registration->ticketType?->decrement('quantity_sold', (int) $registration->quantity);
    }

    if ($expired->isNotEmpty()) {
        logger()->info('Released abandoned registrations', ['count' => $expired->count()]);
    }
})->hourly()->name('release-abandoned-registrations');

/*
| Release stock held by bookings that were verified but never paid for.
|
| Guests get a 24 hour window to complete payment after which the hold is
| returned to the pool so the tickets can be sold to someone else.
*/
Schedule::call(function () {
    $abandoned = Registration::query()
        ->where('status', 'verified')
        ->where('payment_status', 'pending')
        ->where('otp_verified_at', '<', now()->subHours(24))
        ->whereDoesntHave('tickets')
        ->get();

    foreach ($abandoned as $registration) {
        $registration->update([
            'status' => 'cancelled',
            'payment_status' => 'failed',
        ]);

        $registration->ticketType?->decrement('quantity_sold', (int) $registration->quantity);

        logger()->info('Released unpaid booking', ['reference' => $registration->reference]);
    }

    if ($abandoned->isNotEmpty()) {
        logger()->info('Released unpaid bookings', ['count' => $abandoned->count()]);
    }
})->hourly()->name('release-unpaid-bookings');

// Prune OTP rows that are verified and older than a week.
Schedule::command('model:prune', [
    '--model' => [App\Models\OtpVerification::class],
])->weekly();

/*
|--------------------------------------------------------------------------
| Misc
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');