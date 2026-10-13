<x-filament-panels::page>
    <div class="ct-settings">

        {{-- Section tabs -------------------------------------------------------- --}}
        <nav class="ct-tabs" role="tablist" aria-label="Settings sections">
            @foreach (\App\Filament\Pages\ManageSettings::tabs() as $key => $label)
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $activeTab === $key ? 'true' : 'false' }}"
                    wire:click="setTab('{{ $key }}')"
                    @class(['ct-tab', 'is-active' => $activeTab === $key])
                >
                    {{ $label }}
                </button>
            @endforeach
        </nav>

        @if ($activeTab === 'display')

        {{-- At-a-glance summary -------------------------------------------------- --}}
        <div class="ct-settings__summary">
            <div class="ct-chip">
                <span class="ct-chip__label">Code</span>
                <span class="ct-chip__value">{{ $currencyCode ?: 'KES' }}</span>
            </div>
            <div class="ct-chip">
                <span class="ct-chip__label">Sign</span>
                <span class="ct-chip__value">{{ trim($currencySymbol) !== '' ? $currencySymbol : ($currencyCode ?: 'KES') }}</span>
            </div>
            <div class="ct-chip">
                <span class="ct-chip__label">Position</span>
                <span class="ct-chip__value">{{ $currencyPosition === 'before' ? 'Before' : 'After' }}</span>
            </div>
        </div>

        <form wire:submit="save" class="ct-settings__grid">

            {{-- Currency settings ------------------------------------------------- --}}
            <section class="ct-card">
                <header class="ct-card__header">
                    <span class="ct-card__icon">
                        <x-filament::icon icon="heroicon-o-currency-dollar" class="h-5 w-5" />
                    </span>
                    <div class="ct-card__heading">
                        <h2>Currency</h2>
                        <p>The sign shown next to every amount, and the code new ticket types start with.</p>
                    </div>
                </header>

                <div class="ct-card__body ct-fieldset">
                    <div
                        class="ct-field"
                        x-data="{ open: false }"
                        x-on:click.outside="open = false"
                        x-on:keydown.escape="open = false"
                    >
                        @php
                            $currencies = \App\Filament\Pages\ManageSettings::currencies();
                            $selectedCurrency = $currencies[$currencyPreset] ?? null;
                        @endphp

                        <span class="ct-field__label" id="currencyPresetLabel">Choose a currency</span>

                        <div class="ct-select2">
                            <button
                                type="button"
                                class="ct-select2__trigger"
                                x-bind:class="{ 'is-open': open }"
                                x-on:click="open = !open"
                                aria-haspopup="listbox"
                                x-bind:aria-expanded="open.toString()"
                                aria-labelledby="currencyPresetLabel"
                            >
                                <span class="ct-select2__value">
                                    @if ($selectedCurrency)
                                        <span class="ct-select2__code">{{ $currencyPreset }}</span>
                                        <span class="ct-select2__name">{{ $selectedCurrency['name'] }}</span>
                                        <span class="ct-select2__sym">{{ $selectedCurrency['symbol'] }}</span>
                                    @else
                                        <span class="ct-select2__placeholder">Custom / other</span>
                                    @endif
                                </span>

                                <svg class="ct-select2__chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </button>

                            <ul
                                class="ct-select2__panel"
                                x-cloak
                                x-show="open"
                                x-transition
                                role="listbox"
                                aria-labelledby="currencyPresetLabel"
                            >
                                <li>
                                    <button
                                        type="button"
                                        role="option"
                                        aria-selected="{{ $currencyPreset === '' ? 'true' : 'false' }}"
                                        @class(['ct-select2__opt', 'is-selected' => $currencyPreset === ''])
                                        wire:click="$set('currencyPreset', '')"
                                        x-on:click="open = false"
                                    >
                                        <span class="ct-select2__code">—</span>
                                        <span class="ct-select2__name">Custom / other</span>
                                    </button>
                                </li>

                                @foreach ($currencies as $code => $meta)
                                    <li>
                                        <button
                                            type="button"
                                            role="option"
                                            aria-selected="{{ $currencyPreset === $code ? 'true' : 'false' }}"
                                            @class(['ct-select2__opt', 'is-selected' => $currencyPreset === $code])
                                            wire:click="$set('currencyPreset', '{{ $code }}')"
                                            x-on:click="open = false"
                                        >
                                            <span class="ct-select2__code">{{ $code }}</span>
                                            <span class="ct-select2__name">{{ $meta['name'] }}</span>
                                            <span class="ct-select2__sym">{{ $meta['symbol'] }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <p class="ct-field__hint">
                            Selecting a currency fills in the code, sign and position — you can still fine-tune them.
                        </p>
                    </div>

                    <div class="ct-field">
                        <span class="ct-field__label">Sign position</span>
                        <div class="ct-segment" role="radiogroup" aria-label="Sign position">
                            <label @class(['ct-segment__opt', 'is-active' => $currencyPosition === 'before'])>
                                <input type="radio" wire:model.live="currencyPosition" value="before" class="sr-only" />
                                <span>Before the amount</span>
                            </label>
                            <label @class(['ct-segment__opt', 'is-active' => $currencyPosition === 'after'])>
                                <input type="radio" wire:model.live="currencyPosition" value="after" class="sr-only" />
                                <span>After the amount</span>
                            </label>
                        </div>
                        @error('currencyPosition')
                            <p class="ct-field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="ct-field">
                        <label for="currencyCode" class="ct-field__label">Default currency code</label>
                        <input
                            id="currencyCode"
                            type="text"
                            wire:model.live="currencyCode"
                            maxlength="10"
                            autocomplete="off"
                            spellcheck="false"
                            placeholder="KES"
                            class="ct-input ct-input--code"
                        />
                        <p class="ct-field__hint">Prefilled on new ticket types — e.g. KES, USD, NGN.</p>
                        @error('currencyCode')
                            <p class="ct-field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="ct-field">
                        <label for="currencySymbol" class="ct-field__label">Currency sign</label>
                        <input
                            id="currencySymbol"
                            type="text"
                            wire:model.live="currencySymbol"
                            maxlength="10"
                            autocomplete="off"
                            spellcheck="false"
                            placeholder="e.g. KSh, $, €"
                            class="ct-input"
                        />
                        <div class="ct-presets" role="group" aria-label="Common currency signs">
                            @foreach (['$', '€', '£', '₦', 'KSh', '₹'] as $preset)
                                <button type="button" wire:click="$set('currencySymbol', '{{ $preset }}')" class="ct-preset">
                                    {{ $preset }}
                                </button>
                            @endforeach
                        </div>
                        <p class="ct-field__hint">Leave blank to show the code itself.</p>
                        @error('currencySymbol')
                            <p class="ct-field__error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <footer class="ct-card__footer">
                    <x-filament::button type="submit" icon="heroicon-m-check" wire:loading.attr="disabled" wire:target="save">
                        Save settings
                    </x-filament::button>
                    <span class="ct-card__footer-hint">Changes apply immediately across the panel and public site.</span>
                </footer>
            </section>

            {{-- Live preview ------------------------------------------------------ --}}
            <aside class="ct-card ct-card--preview">
                <header class="ct-card__header">
                    <span class="ct-card__icon ct-card__icon--muted">
                        <x-filament::icon icon="heroicon-o-eye" class="h-5 w-5" />
                    </span>
                    <div class="ct-card__heading">
                        <h2>Preview</h2>
                        <p>How amounts will read across the site.</p>
                    </div>
                </header>

                <div class="ct-card__body">
                    <div class="ct-preview">
                        <div class="ct-preview__row">
                            <span>Standard ticket</span>
                            <span>{{ $this->preview(2500, 0) }}</span>
                        </div>
                        <div class="ct-preview__row">
                            <span>Early bird · VIP</span>
                            <span>{{ $this->preview(12000, 0) }}</span>
                        </div>
                        <div class="ct-preview__row">
                            <span>Registration total</span>
                            <span>{{ $this->preview(10000, 2) }}</span>
                        </div>

                        <div class="ct-preview__total">
                            <span>Amount due</span>
                            <strong>{{ $this->preview(10000, 2) }}</strong>
                        </div>
                    </div>
                </div>
            </aside>
        </form>

        @elseif ($activeTab === 'exports')

            {{-- Export data ------------------------------------------------------- --}}
            <section class="ct-card">
                <header class="ct-card__header">
                    <span class="ct-card__icon">
                        <x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-5 w-5" />
                    </span>
                    <div class="ct-card__heading">
                        <h2>Export data</h2>
                        <p>Download spreadsheet-ready CSV files. Exports keep ISO currency codes for compatibility.</p>
                    </div>
                </header>

                <div class="ct-card__body">
                    <div class="ct-export-grid">
                        @foreach ($this->exports() as $export)
                            <a href="{{ $export['url'] }}" class="ct-export">
                                <span class="ct-export__icon">
                                    <x-filament::icon :icon="$export['icon']" class="h-5 w-5" />
                                </span>
                                <span class="ct-export__text">
                                    <span class="ct-export__title">{{ $export['title'] }}</span>
                                    <span class="ct-export__desc">{{ $export['description'] }}</span>
                                </span>
                                <x-filament::icon icon="heroicon-m-arrow-down-tray" class="ct-export__dl h-4 w-4" />
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>

        @elseif ($activeTab === 'activity')

            {{-- Activity log ------------------------------------------------------ --}}
            <section class="ct-card">
                <header class="ct-card__header">
                    <span class="ct-card__icon ct-card__icon--muted">
                        <x-filament::icon icon="heroicon-o-clock" class="h-5 w-5" />
                    </span>
                    <div class="ct-card__heading">
                        <h2>Recent activity</h2>
                        <p>The latest changes across events, registrations, payments and settings.</p>
                    </div>
                </header>

                <div class="ct-card__body">
                    @php($activity = $this->recentActivity())

                    @if ($activity->isEmpty())
                        <p class="ct-empty">No activity has been recorded yet.</p>
                    @else
                        <ul class="ct-activity">
                            @foreach ($activity as $entry)
                                <li class="ct-activity__item">
                                    <span @class(['ct-activity__badge', 'is-'.$entry->color()])>{{ ucfirst($entry->event) }}</span>
                                    <span class="ct-activity__body">
                                        <span class="ct-activity__desc">{{ $entry->description }}</span>
                                        <span class="ct-activity__meta">
                                            {{ $entry->user?->name ?? 'System' }} &middot; {{ $entry->created_at?->diffForHumans() }}
                                        </span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <footer class="ct-card__footer">
                    <x-filament::button tag="a" :href="$this->activityLogUrl()" color="gray" icon="heroicon-m-arrow-right" icon-position="after">
                        Open the full log
                    </x-filament::button>
                    <span class="ct-card__footer-hint">Filter the complete history by user, action or date.</span>
                </footer>
            </section>

        @elseif ($activeTab === 'accounts')

            {{-- Accounts ---------------------------------------------------------- --}}
            <section class="ct-card">
                <header class="ct-card__header">
                    <span class="ct-card__icon ct-card__icon--muted">
                        <x-filament::icon icon="heroicon-o-users" class="h-5 w-5" />
                    </span>
                    <div class="ct-card__heading">
                        <h2>User accounts</h2>
                        <p>Who can sign in to the panel, and with what level of access.</p>
                    </div>
                </header>

                <div class="ct-card__body">
                    <div class="ct-stat-grid">
                        @foreach ($this->accountStats() as $stat)
                            <div class="ct-stat">
                                <span class="ct-stat__value">{{ $stat['value'] }}</span>
                                <span class="ct-stat__label">{{ $stat['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <footer class="ct-card__footer">
                    <x-filament::button tag="a" :href="$this->accountsUrl()" icon="heroicon-m-users">
                        Manage staff accounts
                    </x-filament::button>
                    <span class="ct-card__footer-hint">Create accounts, reset passwords and deactivate access.</span>
                </footer>
            </section>

        @elseif ($activeTab === 'system')

            {{-- System ------------------------------------------------------------ --}}
            <section class="ct-card">
                <header class="ct-card__header">
                    <span class="ct-card__icon ct-card__icon--muted">
                        <x-filament::icon icon="heroicon-o-cog-6-tooth" class="h-5 w-5" />
                    </span>
                    <div class="ct-card__heading">
                        <h2>System</h2>
                        <p>Runtime details for this installation, and maintenance shortcuts.</p>
                    </div>
                </header>

                <div class="ct-card__body">
                    <dl class="ct-info">
                        @foreach ($this->systemInfo() as $row)
                            <div class="ct-info__row">
                                <dt class="ct-info__label">{{ $row['label'] }}</dt>
                                <dd class="ct-info__value">{{ $row['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                <footer class="ct-card__footer">
                    <x-filament::button
                        wire:click="clearCaches"
                        wire:loading.attr="disabled"
                        wire:target="clearCaches"
                        icon="heroicon-m-arrow-path"
                        color="gray"
                    >
                        Clear caches
                    </x-filament::button>
                    <span class="ct-card__footer-hint">Clears the application cache and compiled Blade views.</span>
                </footer>
            </section>

        @endif
    </div>

    <style>
        .ct-settings__summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .ct-chip {
            display: flex;
            flex-direction: column;
            gap: 0.125rem;
            padding: 0.75rem 1rem;
            border: 1px solid var(--ct-border);
            border-radius: 0.75rem;
            background-color: var(--ct-surface);
            box-shadow: 0 1px 2px 0 rgb(15 23 42 / 0.04);
        }

        .ct-chip__label {
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--ct-muted);
        }

        .ct-chip__value {
            font-size: 1.0625rem;
            font-weight: 600;
            color: var(--ct-ink);
            font-variant-numeric: tabular-nums;
        }

        .ct-settings__grid {
            display: grid;
            gap: 1.5rem;
        }

        @media (min-width: 1024px) {
            .ct-settings__grid {
                grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
                align-items: start;
            }
            .ct-card--preview {
                position: sticky;
                top: 1.5rem;
            }
        }

        .ct-card {
            border: 1px solid var(--ct-border);
            border-radius: 0.875rem;
            background-color: var(--ct-surface);
            box-shadow: 0 1px 2px 0 rgb(15 23 42 / 0.04);
        }

        .ct-card__header {
            display: flex;
            gap: 0.875rem;
            align-items: flex-start;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--ct-border);
        }

        .ct-card__icon {
            display: grid;
            place-items: center;
            flex-shrink: 0;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 0.625rem;
            background-color: color-mix(in oklab, var(--ct-navy) 10%, var(--ct-surface));
            color: var(--ct-navy);
        }

        .ct-card__icon--muted {
            background-color: var(--ct-canvas);
            color: var(--ct-muted);
        }

        .ct-card__heading h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: -0.011em;
            color: var(--ct-ink);
        }

        .ct-card__heading p {
            margin: 0.1875rem 0 0;
            font-size: 0.8125rem;
            line-height: 1.45;
            color: var(--ct-muted);
        }

        .ct-card__body {
            padding: 1.5rem;
        }

        .ct-fieldset {
            display: grid;
            gap: 1.5rem;
        }

        @media (min-width: 640px) {
            .ct-fieldset {
                grid-template-columns: 1fr 1fr;
            }
            .ct-field--full {
                grid-column: 1 / -1;
            }
        }

        .ct-field__label {
            display: block;
            margin-bottom: 0.375rem;
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--ct-body);
        }

        .ct-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            color: var(--ct-ink);
            background-color: var(--ct-surface);
            border: 1px solid var(--ct-border-strong);
            border-radius: 0.5rem;
            transition: border-color 150ms, box-shadow 150ms;
        }

        .ct-input--code {
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }

        .ct-input:focus {
            outline: none;
            border-color: var(--ct-navy-light);
            box-shadow: 0 0 0 3px color-mix(in oklab, var(--ct-navy) 14%, transparent);
        }

        .ct-field__hint {
            margin: 0.375rem 0 0;
            font-size: 0.75rem;
            color: var(--ct-muted);
        }

        .ct-field__error {
            margin: 0.375rem 0 0;
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--danger-600, #b91c1c);
        }

        .ct-presets {
            display: flex;
            flex-wrap: wrap;
            gap: 0.375rem;
            margin-top: 0.5rem;
        }

        .ct-preset {
            padding: 0.1875rem 0.5rem;
            font-size: 0.8125rem;
            color: var(--ct-body);
            background-color: var(--ct-canvas);
            border: 1px solid var(--ct-border);
            border-radius: 0.375rem;
            cursor: pointer;
            transition: color 150ms, border-color 150ms, background-color 150ms;
        }

        .ct-preset:hover {
            color: var(--ct-navy);
            border-color: var(--ct-navy-light);
            background-color: var(--ct-surface);
        }

        .ct-segment {
            display: flex;
            width: 100%;
            gap: 0.1875rem;
            padding: 0.1875rem;
            background-color: var(--ct-canvas);
            border: 1px solid var(--ct-border-strong);
            border-radius: 0.5rem;
        }

        .ct-segment__opt {
            flex: 1 1 0;
            padding: 0.4375rem 0.75rem;
            font-size: 0.8125rem;
            font-weight: 500;
            text-align: center;
            color: var(--ct-muted);
            border-radius: 0.375rem;
            cursor: pointer;
            transition: color 150ms, background-color 150ms, box-shadow 150ms;
        }

        .ct-segment__opt:hover {
            color: var(--ct-navy);
        }

        .ct-segment__opt.is-active {
            color: var(--ct-navy);
            font-weight: 600;
            background-color: var(--ct-surface);
            box-shadow: 0 1px 2px 0 rgb(15 23 42 / 0.08);
        }

        .ct-card__footer {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem 1rem;
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--ct-border);
            background-color: color-mix(in oklab, var(--ct-canvas) 70%, var(--ct-surface));
            border-bottom-left-radius: 0.875rem;
            border-bottom-right-radius: 0.875rem;
        }

        .ct-card__footer-hint {
            font-size: 0.75rem;
            color: var(--ct-muted);
        }

        .ct-settings [x-cloak] {
            display: none !important;
        }

        /* Currency picker — custom listbox ------------------------------------- */

        .ct-select2 {
            position: relative;
        }

        .ct-select2__trigger {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            width: 100%;
            min-height: 2.75rem;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            text-align: left;
            color: var(--ct-ink);
            background-color: var(--ct-surface);
            border: 1px solid var(--ct-border-strong);
            border-radius: 0.5rem;
            cursor: pointer;
            transition: border-color 150ms, box-shadow 150ms;
        }

        .ct-select2__trigger:hover {
            border-color: var(--ct-navy-light);
        }

        .ct-select2__trigger.is-open,
        .ct-select2__trigger:focus-visible {
            outline: none;
            border-color: var(--ct-navy-light);
            box-shadow: 0 0 0 3px color-mix(in oklab, var(--ct-navy) 14%, transparent);
        }

        .ct-select2__value {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 0;
        }

        .ct-select2__code {
            flex-shrink: 0;
            min-width: 2.75rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            color: var(--ct-ink);
        }

        .ct-select2__name {
            min-width: 0;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            color: var(--ct-muted);
        }

        .ct-select2__sym {
            color: var(--ct-navy);
            font-weight: 600;
        }

        .ct-select2__placeholder {
            color: var(--ct-muted);
        }

        .ct-select2__chevron {
            flex-shrink: 0;
            width: 1rem;
            height: 1rem;
            color: var(--ct-muted);
            transition: transform 150ms;
        }

        .ct-select2__trigger.is-open .ct-select2__chevron {
            transform: rotate(180deg);
        }

        .ct-select2__panel {
            position: absolute;
            z-index: 30;
            top: calc(100% + 0.375rem);
            left: 0;
            right: 0;
            max-height: 18rem;
            margin: 0;
            padding: 0.375rem;
            overflow-y: auto;
            list-style: none;
            background-color: var(--ct-surface);
            border: 1px solid var(--ct-border);
            border-radius: 0.625rem;
            box-shadow:
                0 12px 28px -8px rgb(15 23 42 / 0.25),
                0 4px 10px -4px rgb(15 23 42 / 0.12);
        }

        .ct-select2__opt {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.5rem 0.625rem;
            font-size: 0.8125rem;
            text-align: left;
            color: var(--ct-body);
            background-color: transparent;
            border: 0;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: color 120ms, background-color 120ms;
        }

        .ct-select2__opt:hover {
            color: var(--ct-ink);
            background-color: var(--ct-canvas);
        }

        .ct-select2__opt.is-selected {
            color: var(--ct-navy);
            font-weight: 600;
            background-color: color-mix(in oklab, var(--ct-navy) 8%, var(--ct-surface));
        }

        .ct-select2__opt .ct-select2__name {
            flex: 1 1 auto;
        }

        .ct-select2__opt .ct-select2__sym {
            margin-left: auto;
        }

        /* Section tabs --------------------------------------------------------- */

        .ct-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
            margin-bottom: 1.5rem;
            padding: 0.3125rem;
            background-color: var(--ct-surface);
            border: 1px solid var(--ct-border);
            border-radius: 0.75rem;
            box-shadow: 0 1px 2px 0 rgb(15 23 42 / 0.04);
        }

        .ct-tab {
            padding: 0.5rem 1rem;
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--ct-muted);
            background-color: transparent;
            border: 0;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: color 150ms, background-color 150ms;
        }

        .ct-tab:hover {
            color: var(--ct-navy);
        }

        .ct-tab.is-active {
            color: var(--ct-navy);
            font-weight: 600;
            background-color: color-mix(in oklab, var(--ct-navy) 8%, var(--ct-surface));
        }

        /* Export data ---------------------------------------------------------- */

        .ct-export-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr));
            gap: 0.75rem;
        }

        .ct-export {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 1rem;
            color: inherit;
            text-decoration: none;
            background-color: var(--ct-surface);
            border: 1px solid var(--ct-border);
            border-radius: 0.75rem;
            transition: border-color 150ms, box-shadow 150ms;
        }

        .ct-export:hover {
            border-color: var(--ct-navy-light);
            box-shadow: 0 8px 20px -12px rgb(15 23 42 / 0.35);
        }

        .ct-export__icon {
            display: grid;
            place-items: center;
            flex-shrink: 0;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 0.625rem;
            background-color: color-mix(in oklab, var(--ct-navy) 10%, var(--ct-surface));
            color: var(--ct-navy);
        }

        .ct-export__text {
            display: flex;
            flex-direction: column;
            gap: 0.125rem;
            min-width: 0;
        }

        .ct-export__title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--ct-ink);
        }

        .ct-export__desc {
            font-size: 0.75rem;
            line-height: 1.45;
            color: var(--ct-muted);
        }

        .ct-export__dl {
            flex-shrink: 0;
            margin-left: auto;
            color: var(--ct-muted);
            transition: color 150ms;
        }

        .ct-export:hover .ct-export__dl {
            color: var(--ct-navy);
        }

        /* Activity feed -------------------------------------------------------- */

        .ct-activity {
            display: grid;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .ct-activity__item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.75rem 0;
            border-bottom: 1px dashed var(--ct-border);
        }

        .ct-activity__item:first-child {
            padding-top: 0;
        }

        .ct-activity__item:last-child {
            padding-bottom: 0;
            border-bottom: 0;
        }

        .ct-activity__badge {
            flex-shrink: 0;
            padding: 0.125rem 0.5rem;
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            border-radius: 999px;
            background-color: var(--ct-canvas);
            color: var(--ct-body);
        }

        .ct-activity__badge.is-success { background-color: #dcfce7; color: #166534; }
        .ct-activity__badge.is-warning { background-color: #fef3c7; color: #92400e; }
        .ct-activity__badge.is-danger  { background-color: #fee2e2; color: #991b1b; }
        .ct-activity__badge.is-info    { background-color: #cffafe; color: #155e75; }
        .ct-activity__badge.is-primary { background-color: color-mix(in oklab, var(--ct-navy) 12%, var(--ct-surface)); color: var(--ct-navy); }

        .ct-activity__body {
            display: flex;
            flex-direction: column;
            gap: 0.125rem;
            min-width: 0;
        }

        .ct-activity__desc {
            font-size: 0.8125rem;
            color: var(--ct-body);
        }

        .ct-activity__meta {
            font-size: 0.6875rem;
            color: var(--ct-muted);
        }

        .ct-empty {
            margin: 0;
            padding: 1.5rem 0;
            font-size: 0.8125rem;
            text-align: center;
            color: var(--ct-muted);
        }

        /* Account stats & system info ----------------------------------------- */

        .ct-stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(8rem, 1fr));
            gap: 0.75rem;
        }

        .ct-stat {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            padding: 1rem;
            text-align: center;
            border: 1px solid var(--ct-border);
            border-radius: 0.75rem;
            background-color: var(--ct-canvas);
        }

        .ct-stat__value {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--ct-ink);
            font-variant-numeric: tabular-nums;
        }

        .ct-stat__label {
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--ct-muted);
        }

        .ct-info {
            margin: 0;
        }

        .ct-info__row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.625rem 0;
            border-bottom: 1px dashed var(--ct-border);
        }

        .ct-info__row:first-child { padding-top: 0; }
        .ct-info__row:last-child { padding-bottom: 0; border-bottom: 0; }

        .ct-info__label {
            font-size: 0.8125rem;
            color: var(--ct-muted);
        }

        .ct-info__value {
            margin: 0;
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--ct-body);
            text-align: right;
        }

        .ct-preview {
            display: grid;
            gap: 0.125rem;
        }

        .ct-preview__row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.625rem 0;
            font-size: 0.8125rem;
            color: var(--ct-muted);
            border-bottom: 1px dashed var(--ct-border);
        }

        .ct-preview__row span:last-child {
            color: var(--ct-body);
            font-weight: 500;
            font-variant-numeric: tabular-nums;
        }

        .ct-preview__total {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 0.875rem;
            padding: 0.875rem 1rem;
            border-radius: 0.625rem;
            background-color: color-mix(in oklab, var(--ct-navy) 6%, var(--ct-surface));
        }

        .ct-preview__total span {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--ct-body);
        }

        .ct-preview__total strong {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--ct-ink);
            font-variant-numeric: tabular-nums;
        }
    </style>
</x-filament-panels::page>
