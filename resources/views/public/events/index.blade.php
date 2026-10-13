@extends('layouts.public')

@section('title', 'Upcoming events')

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-bold tracking-tight">Upcoming events</h1>
        <p class="mt-2 text-gray-600 dark:text-gray-400">
            Register online and get your e-ticket by email and SMS.
        </p>

        @if ($events->isEmpty())
            <div class="mt-10 rounded-xl border border-dashed border-gray-300 p-12 text-center dark:border-gray-700">
                <p class="text-gray-500 dark:text-gray-400">No events are open for registration right now.</p>
            </div>
        @else
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($events as $event)
                    <a href="{{ route('events.show', $event) }}"
                       class="group flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition hover:shadow-md dark:border-gray-800 dark:bg-gray-950">

                        @if ($event->banner_path)
                            <img src="{{ \Illuminate\Support\Str::startsWith($event->banner_path, 'http') ? $event->banner_path : asset('storage/'.$event->banner_path) }}"
                                 alt="{{ $event->title }}" class="h-40 w-full object-cover">
                        @else
                            <div class="flex h-40 items-center justify-center bg-gradient-to-br from-amber-400 to-amber-600 text-white">
                                <span class="text-4xl font-bold">{{ Str::substr($event->title, 0, 1) }}</span>
                            </div>
                        @endif

                        <div class="flex flex-1 flex-col p-5">
                            <h2 class="text-lg font-semibold group-hover:text-amber-600">
                                {{ $event->title }}
                            </h2>

                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ $event->starts_at->format('D, j M Y · g:i a') }}
                            </p>

                            @if ($event->venue)
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-500">{{ $event->venue }}</p>
                            @endif

                            <div class="mt-auto pt-4">
                                @if ($event->isOpenForRegistration())
                                    <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-800">
                                        Registration open
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                                        Registration closed
                                    </span>
                                @endif

                                <p class="mt-2 text-xs text-gray-500">
                                    From {{ \App\Support\Money::format($event->ticketTypes->min('price') ?? 0, 0) }}
                                </p>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-10">{{ $events->links() }}</div>
        @endif
    </div>
@endsection