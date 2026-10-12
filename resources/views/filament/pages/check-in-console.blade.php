<x-filament-panels::page>
    @php
        $summary = $this->summary;
        $events = $this->events;
    @endphp

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Entry form --}}
        <div class="lg:col-span-2 space-y-6">
            <x-filament::section>
                <x-slot name="heading">Manual check-in</x-slot>
                <x-slot name="description">
                    Type or paste the ticket reference printed on the e-ticket.
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

                    <form wire:submit="checkIn" class="flex flex-col gap-3 sm:flex-row">
                        <div class="flex-1">
                            <label for="reference" class="sr-only">Ticket reference</label>
                            <input
                                id="reference"
                                type="text"
                                wire:model="reference"
                                autofocus
                                autocomplete="off"
                                spellcheck="false"
                                placeholder="EVT-XXXXXXXX"
                                class="w-full rounded-lg border-gray-300 font-mono text-lg tracking-wider uppercase dark:bg-gray-900 dark:border-gray-600 dark:text-white"
                            />
                        </div>

                        <x-filament::button type="submit" icon="heroicon-m-check-badge" class="sm:w-auto">
                            Check in
                        </x-filament::button>
                    </form>

                    @if ($resultMessage)
                        <div
                            @class([
                                'rounded-lg border p-4 text-sm font-medium',
                                'border-success-300 bg-success-50 text-success-700 dark:border-success-700 dark:bg-success-950 dark:text-success-400' => $resultState === 'success',
                                'border-warning-300 bg-warning-50 text-warning-700 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-400' => $resultState === 'warning',
                                'border-danger-300 bg-danger-50 text-danger-700 dark:border-danger-700 dark:bg-danger-950 dark:text-danger-400' => $resultState === 'danger',
                            ])
                            role="status"
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
                                        <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-xs sm:grid-cols-4">
                                            <div>
                                                <dt class="opacity-70">Reference</dt>
                                                <dd class="font-mono font-semibold">{{ $resultTicket->reference }}</dd>
                                            </div>
                                            <div>
                                                <dt class="opacity-70">Attendee</dt>
                                                <dd class="font-semibold">{{ $resultTicket->attendee_name }}</dd>
                                            </div>
                                            <div>
                                                <dt class="opacity-70">Ticket</dt>
                                                <dd class="font-semibold">{{ $resultTicket->ticketType?->name }}</dd>
                                            </div>
                                            <div>
                                                <dt class="opacity-70">Event</dt>
                                                <dd class="font-semibold">{{ $resultTicket->event?->title }}</dd>
                                            </div>
                                        </dl>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if ($resultTicket && $resultTicket->status === \App\Enums\TicketStatus::CheckedIn)
                            <x-filament::button
                                color="warning"
                                size="sm"
                                icon="heroicon-m-arrow-uturn-left"
                                wire:click="undo"
                                wire:loading.attr="disabled"
                            >
                                Undo this check-in
                            </x-filament::button>
                        @endif
                    @endif

                    @if (filled($recentReferences))
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="font-medium text-gray-500 dark:text-gray-400">Recent:</span>
                            @foreach ($recentReferences as $ref)
                                <span class="rounded bg-gray-100 px-2 py-1 font-mono dark:bg-gray-800">{{ $ref }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-filament::section>

            <x-filament::section collapsible collapsed>
                <x-slot name="heading">Recent activity</x-slot>

                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($this->recentCheckIns as $checkIn)
                        <li class="flex items-center justify-between gap-3 py-2 text-sm">
                            <div class="min-w-0">
                                <p class="truncate font-medium">{{ $checkIn->ticket?->attendee_name }}</p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                    <span class="font-mono">{{ $checkIn->ticket?->reference }}</span>
                                    · {{ $checkIn->ticket?->event?->title }}
                                </p>
                            </div>
                            <div class="shrink-0 text-right text-xs text-gray-500 dark:text-gray-400">
                                <p>{{ $checkIn->created_at->format('H:i:s') }}</p>
                                <p>{{ $checkIn->user?->name ?? 'System' }} · {{ strtoupper($checkIn->method) }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-gray-500">No check-ins yet.</li>
                    @endforelse
                </ul>
            </x-filament::section>
        </div>

        {{-- Summary --}}
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
                        <dt class="text-gray-500 dark:text-gray-400">Total tickets</dt>
                        <dd class="text-lg font-semibold">{{ number_format($summary['total']) }}</dd>
                    </div>
                </dl>

                <div class="mt-4">
                    <div class="mb-1 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                        <span>{{ $summary['percent'] }}% admitted</span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                        <div
                            class="h-full rounded-full bg-primary-500 transition-all"
                            style="width: {{ min(100, $summary['percent']) }}%"
                        ></div>
                    </div>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>