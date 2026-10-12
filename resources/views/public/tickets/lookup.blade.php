@extends('layouts.public')

@section('title', 'Find my ticket')

@section('content')
    <div class="mx-auto max-w-md px-4 py-16 sm:px-6">
        <div class="rounded-xl border border-gray-200 bg-white p-8 shadow-sm dark:border-gray-800 dark:bg-gray-950">
            <h1 class="text-xl font-bold">Find my ticket</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Lost your link? Enter the reference from your confirmation SMS or email.
            </p>

            @if ($errors->any())
                <div class="mt-5 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('tickets.find') }}" class="mt-6 space-y-4">
                @csrf

                <div>
                    <label for="reference" class="block text-sm font-medium">Ticket reference</label>
                    <input id="reference" name="reference" type="text" required
                           placeholder="EVT-XXXXXXXX"
                           class="mt-1 w-full rounded-lg border-gray-300 font-mono uppercase tracking-wider dark:bg-gray-900 dark:border-gray-600 dark:text-white">
                </div>

                <button type="submit" class="w-full rounded-lg bg-amber-500 px-4 py-3 font-medium text-white hover:bg-amber-600">
                    Find my ticket
                </button>
            </form>
        </div>
    </div>
@endsection