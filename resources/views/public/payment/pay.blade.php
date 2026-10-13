@extends('layouts.public')

@section('title', 'Complete payment')

@section('content')
    @php
        $payment = $registration->latestPayment;
    @endphp

    <div class="mx-auto max-w-lg px-4 py-10 sm:px-6">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <h1 class="text-xl font-bold">Complete your payment</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Your booking is held while you pay. Tickets are released the moment payment clears.
                </p>
            </div>

            <div class="space-y-5 px-6 py-6">
                @if ($errors->any())
                    <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
                        {{ $errors->first() }}
                    </div>
                @endif

                {{-- Summary --}}
                <dl class="space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">Event</dt>
                        <dd class="text-right font-medium">{{ $registration->event->title }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">When</dt>
                        <dd class="text-right font-medium">
                            {{ $registration->event->starts_at->format('D, j M Y · g:i a') }}
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">Ticket</dt>
                        <dd class="text-right font-medium">
                            {{ $registration->quantity }} × {{ $registration->ticketType->name }}
                        </dd>
                    </div>

                    <div class="flex items-baseline justify-between gap-4 border-t border-gray-200 pt-4 dark:border-gray-800">
                        <dt class="text-base font-semibold">Total due</dt>
                        <dd class="text-2xl font-bold">
                            @if (\App\Support\Money::position() === 'before')
                                <span class="text-base font-semibold">{{ \App\Support\Money::symbol() }}</span>
                            @endif
                            {{ number_format((float) $registration->amount, 2) }}
                            @if (\App\Support\Money::position() === 'after')
                                <span class="text-base font-semibold">{{ \App\Support\Money::symbol() }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                @if ($payment)
                    <p class="rounded-lg bg-gray-50 px-4 py-3 text-xs text-gray-600 dark:bg-gray-900 dark:text-gray-400">
                        Reference <span class="font-mono font-semibold">{{ $payment->tx_ref }}</span>
                        @if ($payment->attempts > 0)
                            · attempt {{ $payment->attempts }}
                        @endif
                    </p>
                @endif

                <form method="POST" action="{{ route('registration.pay.checkout', $registration->reference) }}">
                    @csrf
                    <button type="submit" class="w-full rounded-lg bg-amber-500 px-4 py-3 font-medium text-white hover:bg-amber-600">
                        Pay {{ \App\Support\Money::format($registration->amount, 2) }}
                    </button>
                </form>

                <p class="text-center text-xs text-gray-500">
                    You'll be redirected to Flutterwave to pay by card or mobile money.
                    Booking reference <span class="font-mono">{{ $registration->reference }}</span>.
                </p>
            </div>
        </div>
    </div>
@endsection