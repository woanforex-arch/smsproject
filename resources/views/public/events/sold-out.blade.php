@extends('layouts.public')

@section('title', 'Sold out — '.$event->title)

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-20 text-center sm:px-6">
        <h1 class="text-2xl font-bold">Tickets are sold out</h1>
        <p class="mt-3 text-gray-600 dark:text-gray-400">
            Every ticket for <span class="font-medium">{{ $event->title }}</span> has been taken.
            Check back later in case more are released.
        </p>

        <a href="{{ route('home') }}"
           class="mt-8 inline-block rounded-lg bg-amber-500 px-5 py-2.5 font-medium text-white hover:bg-amber-600">
            Browse other events
        </a>
    </div>
@endsection