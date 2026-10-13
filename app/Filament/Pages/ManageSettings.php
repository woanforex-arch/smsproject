<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\Money;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Url;
use UnitEnum;

class ManageSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Settings';

    protected static ?string $slug = 'settings';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.settings';

    /** The settings section currently in view. Kept in the URL so it is shareable. */
    #[Url(as: 'tab')]
    public string $activeTab = 'display';

    /** Default currency code applied to newly created ticket types. */
    public string $currencyCode = '';

    /** The sign rendered next to amounts. Blank means "use the code". */
    public string $currencySymbol = '';

    /** Where the sign sits relative to the number: before | after. */
    public string $currencyPosition = 'before';

    /**
     * Selected entry in the currency picker. Purely a convenience — choosing
     * one pre-fills the code/sign/position below; it is not persisted itself.
     */
    public string $currencyPreset = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->currencyCode = (string) Money::code();
        $this->currencySymbol = (string) Setting::get('currency.symbol', '');
        $this->currencyPosition = Money::position();
        $this->currencyPreset = static::presetFor($this->currencyCode);
    }

    public function save(): void
    {
        $data = $this->validate([
            'currencyCode' => ['required', 'string', 'min:2', 'max:10', 'regex:/^[A-Za-z]+$/'],
            'currencySymbol' => ['nullable', 'string', 'max:10'],
            'currencyPosition' => ['required', 'in:before,after'],
        ]);

        Setting::set('currency.code', strtoupper($data['currencyCode']));
        Setting::set('currency.symbol', trim((string) $data['currencySymbol']) ?: null);
        Setting::set('currency.position', $data['currencyPosition']);

        $this->currencyCode = (string) Money::code();
        $this->currencySymbol = (string) Setting::get('currency.symbol', '');
        $this->currencyPosition = Money::position();
        $this->currencyPreset = static::presetFor($this->currencyCode);

        Notification::make()
            ->title('Currency settings saved')
            ->body('Ticket prices and totals now use the updated sign.')
            ->success()
            ->send();
    }

    /** Choosing a currency fills the code, sign and sign position. */
    public function updatedCurrencyPreset(string $value): void
    {
        $currency = static::currencies()[$value] ?? null;

        if ($currency === null) {
            return;
        }

        $this->currencyCode = $value;
        $this->currencySymbol = $currency['symbol'];
        $this->currencyPosition = $currency['position'];

        $this->resetValidation('currencyCode');
        $this->resetValidation('currencySymbol');
    }

    /** Keep the picker in step when the code is typed out by hand. */
    public function updatedCurrencyCode(string $value): void
    {
        $this->currencyPreset = static::presetFor($value);
    }

    /** The picker's option list, keyed by ISO code. */
    public static function currencies(): array
    {
        return [
            'KES' => ['name' => 'Kenyan Shilling', 'symbol' => 'KSh', 'position' => 'before'],
            'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'position' => 'before'],
            'EUR' => ['name' => 'Euro', 'symbol' => '€', 'position' => 'after'],
            'GBP' => ['name' => 'British Pound', 'symbol' => '£', 'position' => 'before'],
            'NGN' => ['name' => 'Nigerian Naira', 'symbol' => '₦', 'position' => 'before'],
            'GHS' => ['name' => 'Ghanaian Cedi', 'symbol' => 'GH₵', 'position' => 'before'],
            'ZAR' => ['name' => 'South African Rand', 'symbol' => 'R', 'position' => 'before'],
            'TZS' => ['name' => 'Tanzanian Shilling', 'symbol' => 'TSh', 'position' => 'before'],
            'UGX' => ['name' => 'Ugandan Shilling', 'symbol' => 'USh', 'position' => 'before'],
            'ZMW' => ['name' => 'Zambian Kwacha', 'symbol' => 'ZK', 'position' => 'before'],
            'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹', 'position' => 'before'],
            'AED' => ['name' => 'UAE Dirham', 'symbol' => 'AED', 'position' => 'before'],
            'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'C$', 'position' => 'before'],
            'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$', 'position' => 'before'],
            'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥', 'position' => 'before'],
            'CNY' => ['name' => 'Chinese Yuan', 'symbol' => 'CN¥', 'position' => 'before'],
        ];
    }

    /** The known preset matching a code, or an empty string when custom. */
    protected static function presetFor(string $code): string
    {
        $code = strtoupper(trim($code));

        return array_key_exists($code, static::currencies()) ? $code : '';
    }

    /** Live preview of the sign as it will render, using unsaved input. */
    public function preview(float $amount = 2500, int $decimals = 2): string
    {
        $symbol = trim($this->currencySymbol) ?: ($this->currencyCode ?: 'KES');
        $value = number_format($amount, $decimals);

        return $this->currencyPosition === 'before'
            ? trim("{$symbol} {$value}")
            : trim("{$value} {$symbol}");
    }

    /* ---------------------------------------------------------------------
     | Sections
     | --------------------------------------------------------------------- */

    /** @return array<string, string> */
    public static function tabs(): array
    {
        return [
            'display' => 'Display',
            'exports' => 'Export data',
            'activity' => 'Activity log',
            'accounts' => 'Accounts',
            'system' => 'System',
        ];
    }

    public function setTab(string $tab): void
    {
        if (array_key_exists($tab, static::tabs())) {
            $this->activeTab = $tab;
        }
    }

    /** The download links surfaced on the "Export data" tab. */
    public function exports(): array
    {
        return [
            [
                'title' => 'Events',
                'description' => 'Every event with its schedule, venue and sales totals.',
                'icon' => 'heroicon-o-calendar-days',
                'url' => route('exports.events'),
            ],
            [
                'title' => 'Registrations',
                'description' => 'Attendees with their ticket type and payment status.',
                'icon' => 'heroicon-o-clipboard-document-list',
                'url' => route('exports.registrations'),
            ],
            [
                'title' => 'Tickets',
                'description' => 'Issued tickets — an attendance sheet ready for check-in.',
                'icon' => 'heroicon-o-ticket',
                'url' => route('exports.tickets'),
            ],
            [
                'title' => 'Payments',
                'description' => 'The full payments ledger with gateway references.',
                'icon' => 'heroicon-o-credit-card',
                'url' => route('exports.payments'),
            ],
            [
                'title' => 'Event summary',
                'description' => 'Per-event snapshot of sales, attendance and revenue.',
                'icon' => 'heroicon-o-chart-bar',
                'url' => route('exports.summary'),
            ],
            [
                'title' => 'Staff accounts',
                'description' => 'Admin and staff accounts with roles and last sign-in.',
                'icon' => 'heroicon-o-users',
                'url' => route('exports.users'),
            ],
        ];
    }

    /** The latest activity entries, newest first. */
    public function recentActivity(int $limit = 12)
    {
        return ActivityLog::with('user')->latest('id')->limit($limit)->get();
    }

    /** @return array<int, array{label: string, value: string|int, hint?: string}> */
    public function accountStats(): array
    {
        return [
            ['label' => 'Total accounts', 'value' => User::count()],
            ['label' => 'Administrators', 'value' => User::where('role', 'admin')->count()],
            ['label' => 'Staff', 'value' => User::where('role', 'staff')->count()],
            ['label' => 'Active', 'value' => User::where('is_active', true)->count()],
        ];
    }

    /** @return array<int, array{label: string, value: string}> */
    public function systemInfo(): array
    {
        return [
            ['label' => 'Application', 'value' => (string) config('app.name')],
            ['label' => 'Environment', 'value' => app()->environment()],
            ['label' => 'Laravel', 'value' => app()->version()],
            ['label' => 'PHP', 'value' => PHP_VERSION],
            ['label' => 'Timezone', 'value' => (string) config('app.timezone')],
            ['label' => 'Database', 'value' => (string) config('database.default')],
            ['label' => 'Cache', 'value' => (string) config('cache.default')],
            ['label' => 'Queue', 'value' => (string) config('queue.default')],
            ['label' => 'Session driver', 'value' => (string) config('session.driver')],
        ];
    }

    public function clearCaches(): void
    {
        Artisan::call('cache:clear');
        Artisan::call('view:clear');

        Notification::make()
            ->title('Caches cleared')
            ->body('The application cache and compiled views were refreshed.')
            ->success()
            ->send();
    }

    public function activityLogUrl(): string
    {
        return ActivityLogResource::getUrl('index');
    }

    public function accountsUrl(): string
    {
        return UserResource::getUrl('index');
    }
}
