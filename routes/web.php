<?php

use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Site\EventListingController;
use App\Http\Controllers\Site\PaymentController;
use App\Http\Controllers\Site\RegistrationController;
use App\Http\Controllers\Site\TicketController;
use App\Http\Controllers\Webhooks\FlutterwaveController;
use App\Http\Controllers\Webhooks\TwilioStatusController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site
|--------------------------------------------------------------------------
*/

Route::get('/', [EventListingController::class, 'index'])->name('home');

Route::get('/events/{event:slug}', [EventListingController::class, 'show'])
    ->name('events.show');

/*
|--------------------------------------------------------------------------
| Registration + OTP verification
|--------------------------------------------------------------------------
*/

Route::prefix('events/{event:slug}')
    ->name('events.')
    ->group(function () {
        Route::get('/register', [RegistrationController::class, 'create'])
            ->name('register');

        Route::post('/register', [RegistrationController::class, 'store'])
            ->name('register.store');
    });

Route::prefix('register/{registration:reference}')
    ->name('registration.')
    ->group(function () {
        Route::get('/verify', [RegistrationController::class, 'verifyForm'])->name('verify');
        Route::post('/verify', [RegistrationController::class, 'verify'])->name('verify.store');
        Route::post('/resend', [RegistrationController::class, 'resend'])->name('resend');

        Route::get('/pay', [PaymentController::class, 'show'])->name('pay');
        Route::post('/pay', [PaymentController::class, 'checkout'])->name('pay.checkout');
    });

/*
|--------------------------------------------------------------------------
| Payment return (Flutterwave redirects the guest back here)
|--------------------------------------------------------------------------
*/

Route::get('/register/{registration:reference}/payment/return', [PaymentController::class, 'return'])
    ->name('registration.payment.return');

Route::get('/register/{registration:reference}/payment/cancelled', [PaymentController::class, 'cancelled'])
    ->name('registration.payment.cancelled');

/*
|--------------------------------------------------------------------------
| Tickets
|--------------------------------------------------------------------------
*/

Route::get('/find-ticket', [TicketController::class, 'lookup'])->name('tickets.lookup');
Route::post('/find-ticket', [TicketController::class, 'find'])->name('tickets.find');

Route::get('/tickets/{ticket:reference}', [TicketController::class, 'show'])
    ->name('tickets.show')
    ->where('ticket', '[A-Z0-9\-]+');

Route::get('/tickets/{reference}/print', [TicketController::class, 'print'])
    ->name('tickets.print')
    ->where('reference', '[A-Z0-9\-]+');

/*
|--------------------------------------------------------------------------
| CSV exports (admin/staff authenticated)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->prefix('admin/exports')->name('exports.')->group(function () {
    Route::get('registrations', [ExportController::class, 'registrations'])->name('registrations');
    Route::get('tickets', [ExportController::class, 'tickets'])->name('tickets');
    Route::get('payments', [ExportController::class, 'payments'])->name('payments');
    Route::get('summary', [ExportController::class, 'summary'])->name('summary');
});

/*
|--------------------------------------------------------------------------
| Webhooks
|--------------------------------------------------------------------------
*/

Route::post('/webhooks/twilio/status', [TwilioStatusController::class, 'status'])
    ->name('webhooks.twilio.status');

Route::post('/webhooks/flutterwave', [FlutterwaveController::class, 'handle'])
    ->name('webhooks.flutterwave');