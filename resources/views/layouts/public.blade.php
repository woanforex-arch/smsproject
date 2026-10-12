<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @if (session('status'))
        <script>
            window.__flashStatus = @json(session('status'));
        </script>
    @endif
</head>
<body class="flex min-h-full flex-col bg-gray-50 text-gray-900 antialiased dark:bg-gray-900 dark:text-gray-100">
    <header class="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
            <a href="{{ route('home') }}" class="text-lg font-bold tracking-tight">
                {{ config('app.name') }}
            </a>

            <nav class="flex items-center gap-4 text-sm">
                <a href="{{ route('home') }}" class="hover:text-amber-600">Events</a>
                <a href="{{ route('tickets.lookup') }}" class="hover:text-amber-600">Find my ticket</a>
                <a href="/admin" class="rounded-lg bg-gray-900 px-3 py-1.5 font-medium text-white hover:bg-gray-700 dark:bg-white dark:text-gray-900">
                    Staff login
                </a>
            </nav>
        </div>
    </header>

    <main class="flex-1">
        @if (session('status'))
            <div class="mx-auto max-w-6xl px-4 pt-6 sm:px-6">
                <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">
                    {{ session('status') }}
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="border-t border-gray-200 bg-white py-8 text-center text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-950">
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </footer>
</body>
</html>