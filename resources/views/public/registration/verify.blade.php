@extends('layouts.public')

@section('title', 'Verify your phone')

@section('content')
    <div class="mx-auto max-w-md px-4 py-16 sm:px-6">
        <div class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-800 dark:bg-gray-950">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/40">
                <svg class="h-7 w-7 text-amber-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/>
                </svg>
            </div>

            <h1 class="mt-4 text-xl font-bold">Verify your phone number</h1>

            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                We sent a {{ config('ticketing.otp.length') }}-digit code to
                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $registration->phone }}</span>.
            </p>

            @if ($errors->any())
                <div class="mt-5 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('registration.verify.store', $registration->reference) }}"
                  class="mt-6 space-y-4" x-data x-init="$nextTick(() => $refs.code.focus())">
                @csrf

                <input x-ref="code"
                       name="code"
                       type="text"
                       inputmode="numeric"
                       pattern="[0-9]*"
                       maxlength="{{ config('ticketing.otp.length') }}"
                       autocomplete="one-time-code"
                       required
                       placeholder="{{ str_repeat('0', config('ticketing.otp.length')) }}"
                       class="w-full rounded-lg border-gray-300 text-center font-mono text-2xl tracking-[0.5em] dark:bg-gray-900 dark:border-gray-600 dark:text-white">

                <button type="submit"
                        class="w-full rounded-lg bg-amber-500 px-4 py-3 font-medium text-white hover:bg-amber-600">
                    Verify
                </button>
            </form>

            <form method="POST" action="{{ route('registration.resend', $registration->reference) }}" class="mt-4">
                @csrf
                <button type="submit" class="text-sm text-gray-500 underline hover:text-amber-600">
                    Didn't get a code? Send again
                </button>
            </form>

            <p class="mt-6 border-t border-gray-200 pt-4 text-xs text-gray-500 dark:border-gray-800">
                Booking reference
                <span class="font-mono font-medium">{{ $registration->reference }}</span>
            </p>
        </div>
    </div>
@endsection