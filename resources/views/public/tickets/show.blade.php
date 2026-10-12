@extends('layouts.public')

@section('title', 'Ticket '.$ticket->reference)

@section('content')
    <div class="mx-auto max-w-lg px-4 py-10 sm:px-6">
        @if (session('status'))
            <div class="mb-6 rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950">
            {{-- Header --}}
            <div class="bg-gradient-to-r from-amber-500 to-amber-600 px-6 py-5 text-white">
                <p class="text-xs font-medium uppercase tracking-wider opacity-90">E-Ticket</p>
                <h1 class="mt-1 text-xl font-bold leading-tight">{{ $ticket->event->title }}</h1>
            </div>

            {{-- Details --}}
            <div class="px-6 py-6">
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-500">Attendee</dt>
                        <dd class="mt-0.5 text-base font-semibold">{{ $ticket->attendee_name }}</dd>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Date</dt>
                            <dd class="mt-0.5 font-medium">{{ $ticket->event->starts_at->format('j M Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Time</dt>
                            <dd class="mt-0.5 font-medium">{{ $ticket->event->starts_at->format('g:i a') }}</dd>
                        </div>
                    </div>

                    @if ($ticket->event->venue)
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Venue</dt>
                            <dd class="mt-0.5 font-medium">{{ $ticket->event->venue }}</dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-500">Ticket type</dt>
                        <dd class="mt-0.5 font-medium">{{ $ticket->ticketType->name }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-500">Reference</dt>
                        <dd class="mt-0.5 font-mono text-lg font-bold tracking-wider">{{ $ticket->reference }}</dd>
                    </div>
                </dl>

                {{-- QR --}}
                <div class="mt-8 flex flex-col items-center">
                    <div class="rounded-xl border-2 border-gray-200 p-4 dark:border-gray-800">
                        {!! QrCode::format('svg')
                            ->size(220)
                            ->margin(0)
                            ->generate($ticket->qrPayload()) !!}
                    </div>

                    <p class="mt-3 text-center text-xs text-gray-500 dark:text-gray-400">
                        Show this code at the entrance.
                        @if ($ticket->isCheckedIn())
                            <span class="mt-1 block font-medium text-green-600">
                                Checked in {{ $ticket->checked_in_at->diffForHumans() }}
                            </span>
                        @endif
                    </p>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex gap-3 border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                <a href="{{ route('tickets.print', $ticket->reference) }}"
                   class="flex-1 rounded-lg bg-gray-100 px-3 py-2.5 text-center text-sm font-medium hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700">
                    Print
                </a>
            </div>
        </div>

        {{-- Sibling tickets --}}
        @if ($siblings->count() > 1)
            <div class="mt-6 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-950">
                <h2 class="text-sm font-semibold">Tickets in this booking ({{ $siblings->count() }})</h2>
                <ul class="mt-3 space-y-2">
                    @foreach ($siblings as $sibling)
                        <li>
                            <a href="{{ route('tickets.show', $sibling->reference) }}"
                               @class([
                                   'flex items-center justify-between rounded-lg px-3 py-2 text-sm',
                                   'bg-amber-50 font-medium text-amber-900 ring-1 ring-amber-300' => $sibling->id === $ticket->id,
                                   'hover:bg-gray-50 dark:hover:bg-gray-800' => $sibling->id !== $ticket->id,
                               ])>
                                <span class="font-mono">{{ $sibling->reference }}</span>
                                <span class="text-xs text-gray-500">{{ $sibling->ticketType->name }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endsection