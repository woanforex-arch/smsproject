@extends('layouts.public')

@section('title', 'Register — '.$event->title)

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6">
        <a href="{{ route('events.show', $event) }}" class="text-sm text-gray-500 hover:text-amber-600">
            &larr; Back to event
        </a>

        <h1 class="mt-4 text-2xl font-bold tracking-tight">Register for {{ $event->title }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ $event->starts_at->format('l, j F Y · g:i a') }}
            @if ($event->venue) — {{ $event->venue }}@endif
        </p>

        <div class="mt-8 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-950">
            <form method="POST" action="{{ route('events.register.store', $event) }}" class="space-y-5">
                @csrf

                @if ($errors->any())
                    <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <ul class="list-inside list-disc space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div>
                    <label for="ticket_type_id" class="block text-sm font-medium">Ticket type</label>
                    <select id="ticket_type_id" name="ticket_type_id" required
                            onchange="syncPrice(this.value)"
                            class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-600 dark:text-white">
                        @foreach ($ticketTypes as $type)
                            <option value="{{ $type->id }}"
                                data-price="{{ $type->price }}"
                                data-currency="{{ \App\Support\Money::symbol() }}"
                                data-max="{{ $type->quantity_per_order_max }}"
                                data-remaining="{{ $type->hasUnlimitedStock() ? 999 : $type->quantityRemaining() }}"
                                @selected($selected?->id === $type->id)>
                                {{ $type->name }} — {{ \App\Support\Money::format($type->price, 0) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="name" class="block text-sm font-medium">Full name</label>
                        <input id="name" name="name" type="text" required maxlength="120" autocomplete="name"
                               value="{{ old('name') }}"
                               class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-600 dark:text-white">
                    </div>

                    <div>
                        <label for="quantity" class="block text-sm font-medium">Number of tickets</label>
                        <input id="quantity" name="quantity" type="number" min="1" max="10" required
                               value="{{ old('quantity', 1) }}"
                               class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-600 dark:text-white">
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium">Email address</label>
                        <input id="email" name="email" type="email" required maxlength="190" autocomplete="email"
                               value="{{ old('email') }}"
                               class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-600 dark:text-white">
                        <p class="mt-1 text-xs text-gray-500">Your e-ticket will be sent here.</p>
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium">Phone number</label>
                        <input id="phone" name="phone" type="tel" required maxlength="32" autocomplete="tel"
                               placeholder="+254700000000"
                               value="{{ old('phone') }}"
                               class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-600 dark:text-white">
                        <p class="mt-1 text-xs text-gray-500">Used for verification and your ticket reference.</p>
                    </div>
                </div>

                <div id="total-row" class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3 text-sm dark:bg-gray-800">
                    <span class="text-gray-600 dark:text-gray-400">Total</span>
                    <span id="total" class="font-semibold">—</span>
                </div>

                <button type="submit"
                        class="w-full rounded-lg bg-amber-500 px-4 py-3 font-medium text-white hover:bg-amber-600">
                    Continue — verify your phone
                </button>

                <p class="text-center text-xs text-gray-500">
                    No payment is taken here. You will receive your ticket reference once verified.
                </p>
            </form>
        </div>
    </div>

    <script>
        const select = document.getElementById('ticket_type_id');
        const qty = document.getElementById('quantity');
        const total = document.getElementById('total');

        function syncPrice() {
            const opt = select.selectedOptions[0];
            if (!opt) return;

            const price = parseFloat(opt.dataset.price || '0');
            const currency = opt.dataset.currency || '';
            const max = parseInt(opt.dataset.max || '10', 10);
            const remaining = parseInt(opt.dataset.remaining || '999', 10);

            qty.max = Math.min(max, remaining);

            if (parseInt(qty.value, 10) > qty.max) {
                qty.value = Math.max(1, qty.max);
            }

            const n = Math.max(1, parseInt(qty.value, 10) || 1);
            total.textContent = (price * n).toLocaleString(undefined, {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2
            }) + ' ' + currency;
        }

        select.addEventListener('change', syncPrice);
        qty.addEventListener('input', syncPrice);
        syncPrice();
    </script>
@endsection