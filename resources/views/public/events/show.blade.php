@extends('layouts.public')

@section('title', $event->title)

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
        <a href="{{ route('home') }}" class="text-sm text-gray-500 hover:text-amber-600">&larr; All events</a>

        <div class="mt-6 grid gap-8 lg:grid-cols-3">
            {{-- Details --}}
            <div class="lg:col-span-2">
                <h1 class="text-3xl font-bold tracking-tight">{{ $event->title }}</h1>

                <dl class="mt-6 space-y-2 text-sm">
                    <div class="flex gap-2">
                        <dt class="w-24 shrink-0 font-medium text-gray-500">When</dt>
                        <dd>{{ $event->starts_at->format('l, j F Y') }} at {{ $event->starts_at->format('g:i a') }}</dd>
                    </div>
                    @if ($event->venue)
                        <div class="flex gap-2">
                            <dt class="w-24 shrink-0 font-medium text-gray-500">Where</dt>
                            <dd>
                                {{ $event->venue }}
                                @if ($event->address), {{ $event->address }}@endif
                                @if ($event->city), {{ $event->city }}@endif
                            </dd>
                        </div>
                    @endif
                    @if ($event->registration_deadline)
                        <div class="flex gap-2">
                            <dt class="w-24 shrink-0 font-medium text-gray-500">Register by</dt>
                            <dd>{{ $event->registration_deadline->format('j F Y, g:i a') }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($event->description)
                    <div class="prose prose-sm mt-8 max-w-none dark:prose-invert">
                        {!! nl2br(e($event->description)) !!}
                    </div>
                @endif
            </div>

            {{-- Tickets --}}
            <div>
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-950">
                    <h2 class="text-lg font-semibold">Tickets</h2>

                    @if ($event->isOpenForRegistration() && $event->ticketTypes->filter(fn ($type) => $type->isOnSale())->isNotEmpty())
                        <div class="mt-4 space-y-3">
                            @foreach ($event->ticketTypes->filter(fn ($type) => $type->isOnSale()) as $type)
                                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                                    <div class="flex items-baseline justify-between">
                                        <span class="font-medium">{{ $type->name }}</span>
                                        <span class="font-semibold">
                                            {{ number_format((float) $type->price, 0) }} {{ $type->currency }}
                                        </span>
                                    </div>

                                    @if ($type->description)
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $type->description }}</p>
                                    @endif

                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        @if ($type->hasUnlimitedStock())
                                            Availability: unlimited
                                        @else
                                            {{ $type->quantityRemaining() }} left of {{ $type->quantity_total }}
                                        @endif
                                    </p>

                                    <a href="{{ route('events.register', ['event' => $event, 'ticketType' => $type->id]) }}"
                                       class="mt-3 block rounded-lg bg-amber-500 px-3 py-2 text-center text-sm font-medium text-white hover:bg-amber-600">
                                        Select
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-4 rounded-lg bg-gray-100 px-4 py-6 text-center text-sm text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                            Registration is closed for this event.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection