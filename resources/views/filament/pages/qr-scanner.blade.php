@php
    $summary = $this->summary;
    $events = $this->events;
@endphp

<style>
    #qr-reader { border: none !important; }
    #qr-reader video { border-radius: 0.5rem; width: 100% !important; }
    #qr-reader img[alt="Info icon"] { display: none; }
</style>

<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Scanner --}}
        <div class="lg:col-span-2 space-y-6">
            <x-filament::section>
                <x-slot name="heading">QR scanner</x-slot>
                <x-slot name="description">
                    Point the device camera at the QR code on the guest's e-ticket.
                </x-slot>

                <div class="space-y-4">
                    @if ($events->isNotEmpty())
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                Filter to event
                            </span>
                            <select
                                wire:model.live="eventId"
                                class="w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-600 dark:text-white"
                            >
                                <option value="">All events</option>
                                @foreach ($events as $event)
                                    <option value="{{ $event->id }}">{{ $event->title }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    <div
                        id="qr-reader"
                        class="mx-auto w-full max-w-md overflow-hidden rounded-lg border border-gray-200 bg-gray-900 dark:border-gray-700"
                    >
                        <div class="flex min-h-[16rem] items-center justify-center p-6 text-center text-sm text-gray-400">
                            Camera starting&hellip;
                        </div>
                    </div>

                    <div class="flex items-center justify-center gap-3">
                        <x-filament::button
                            color="gray"
                            size="sm"
                            icon="heroicon-m-camera"
                            id="qr-start"
                        >
                            Start camera
                        </x-filament::button>

                        <x-filament::button
                            color="gray"
                            size="sm"
                            icon="heroicon-m-pause"
                            id="qr-stop"
                        >
                            Stop
                        </x-filament::button>
                    </div>

                    {{-- Fallback: manual payload entry when no camera is available --}}
                    <details class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                        <summary class="cursor-pointer text-sm font-medium text-gray-600 dark:text-gray-400">
                            No camera? Enter the ticket reference manually
                        </summary>
                        <form wire:submit="scanManual" class="mt-3 flex gap-2">
                            <input
                                type="text"
                                wire:model="reference"
                                placeholder="EVT-XXXXXXXX"
                                class="w-full rounded-lg border-gray-300 font-mono dark:bg-gray-900 dark:border-gray-600 dark:text-white"
                            />
                            <x-filament::button type="submit" icon="heroicon-m-check-badge">Check in</x-filament::button>
                        </form>
                    </details>

                    @if ($resultMessage)
                        <div
                            @class([
                                'rounded-lg border p-4 text-sm font-medium',
                                'border-success-300 bg-success-50 text-success-700 dark:border-success-700 dark:bg-success-950 dark:text-success-400' => $resultState === 'success',
                                'border-warning-300 bg-warning-50 text-warning-700 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-400' => $resultState === 'warning',
                                'border-danger-300 bg-danger-50 text-danger-700 dark:border-danger-700 dark:bg-danger-950 dark:text-danger-400' => $resultState === 'danger',
                            ])
                            role="status"
                            x-data
                            x-init="navigator.vibrate && navigator.vibrate({{ $resultState === 'success' ? 120 : 400 }})"
                        >
                            <div class="flex items-start gap-3">
                                <x-filament::icon
                                    :icon="$resultState === 'success' ? 'heroicon-m-check-circle'
                                        : ($resultState === 'warning' ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-x-circle')"
                                    class="h-5 w-5 shrink-0"
                                />
                                <div class="flex-1">
                                    <p>{{ $resultMessage }}</p>

                                    @if ($resultTicket)
                                        <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                                            <div>
                                                <dt class="opacity-70">Reference</dt>
                                                <dd class="font-mono font-semibold">{{ $resultTicket['reference'] }}</dd>
                                            </div>
                                            <div>
                                                <dt class="opacity-70">Attendee</dt>
                                                <dd class="font-semibold">{{ $resultTicket['attendee'] }}</dd>
                                            </div>
                                            <div>
                                                <dt class="opacity-70">Ticket</dt>
                                                <dd class="font-semibold">{{ $resultTicket['type'] }}</dd>
                                            </div>
                                            <div>
                                                <dt class="opacity-70">Event</dt>
                                                <dd class="font-semibold">{{ $resultTicket['event'] }}</dd>
                                            </div>
                                        </dl>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </x-filament::section>
        </div>

        {{-- Feed + summary --}}
        <div class="space-y-6">
            <x-filament::section>
                <x-slot name="heading">Attendance</x-slot>

                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Checked in</dt>
                        <dd class="text-lg font-semibold">{{ number_format($summary['checked_in']) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Remaining</dt>
                        <dd class="text-lg font-semibold">{{ number_format($summary['remaining']) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Total</dt>
                        <dd class="text-lg font-semibold">{{ number_format($summary['total']) }}</dd>
                    </div>
                </dl>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Scan feed</x-slot>

                <ul wire:poll.3s class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    @forelse ($feed as $item)
                        <li class="flex items-start gap-2 py-2">
                            <x-filament::icon
                                :icon="$item['ok'] ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle'"
                                @class([
                                    'mt-0.5 h-4 w-4 shrink-0',
                                    'text-success-500' => $item['ok'],
                                    'text-danger-500' => ! $item['ok'],
                                ])
                            />
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">{{ $item['attendee'] }}</p>
                                <p class="truncate font-mono text-xs text-gray-500 dark:text-gray-400">{{ $item['reference'] }}</p>
                            </div>
                            <span class="shrink-0 text-xs text-gray-400">{{ $item['at'] }}</span>
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-gray-500">Scans will appear here.</li>
                    @endforelse
                </ul>

                @if ($feed)
                    <x-filament::button
                        color="warning"
                        size="sm"
                        class="mt-3"
                        icon="heroicon-m-arrow-uturn-left"
                        wire:click="undoLast"
                        wire:loading.attr="disabled"
                    >
                        Undo last scan
                    </x-filament::button>
                @endif
            </x-filament::section>
        </div>
    </div>

    @push('scripts')
        @once
            <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
            <script>
                (function () {
                    const readerEl = document.getElementById('qr-reader');
                    const startBtn = document.getElementById('qr-start');
                    const stopBtn = document.getElementById('qr-stop');

                    if (!readerEl || typeof Html5Qrcode === 'undefined') {
                        readerEl.innerHTML =
                            '<p class="p-6 text-center text-sm text-gray-300">Scanner library unavailable. Use manual entry below.</p>';
                        startBtn.disabled = true;
                        stopBtn.disabled = true;
                        return;
                    }

                    let scanner = null;
                    let lastValue = null;
                    let lastScanAt = 0;

                    const DEBOUNCE_MS = 2500;

                    async function start() {
                        if (scanner) return;

                        scanner = new Html5Qrcode('qr-reader');
                        startBtn.disabled = true;

                        try {
                            await scanner.start(
                                { facingMode: 'environment' },
                                { fps: 10, qrbox: { width: 260, height: 260 } },
                                onDecoded,
                                () => { /* ignore per-frame decode misses */ }
                            );
                        } catch (err) {
                            scanner = null;
                            startBtn.disabled = false;
                            readerEl.innerHTML =
                                '<p class="p-6 text-center text-sm text-red-300">Could not start the camera. Allow camera access or use manual entry.</p>';
                        }
                    }

                    function onDecoded(text) {
                        const now = Date.now();

                        // The same QR keeps firing many times per second; ignore
                        // repeats so one guest is not processed repeatedly.
                        if (text === lastValue && now - lastScanAt < DEBOUNCE_MS) return;

                        lastValue = text;
                        lastScanAt = now;

                        @this.dispatch('scan', { payload: text });
                    }

                    async function stop() {
                        if (!scanner) return;

                        try { await scanner.stop(); } catch (e) { /* already stopped */ }
                        try { await scanner.clear(); } catch (e) { /* nothing to clear */ }

                        scanner = null;
                        startBtn.disabled = false;
                        lastValue = null;
                    }

                    startBtn.addEventListener('click', start);
                    stopBtn.addEventListener('click', stop);

                    // Clean up when navigating away from the page.
                    window.addEventListener('beforeunload', stop);
                })();
            </script>
        @endonce
    @endpush
</x-filament-panels::page>