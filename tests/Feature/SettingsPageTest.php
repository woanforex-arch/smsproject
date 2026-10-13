<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Filament\Pages\ManageSettings;
use App\Models\Event;
use App\Models\Setting;
use App\Models\TicketType;
use App\Models\User;
use App\Support\Money;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The settings page renders inside the admin panel; make sure the
        // Livewire component has one to resolve its layout against.
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admins_can_open_the_settings_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Choose a currency')
            ->assertSee('Currency sign')
            ->assertSee('Sign position')
            ->assertSee('Preview');
    }

    public function test_staff_cannot_open_the_settings_page(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get('/admin/settings')->assertForbidden();
    }

    public function test_currency_settings_can_be_saved(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(ManageSettings::class)
            ->set('currencyCode', 'ngn')
            ->set('currencySymbol', '₦')
            ->set('currencyPosition', 'before')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('NGN', Setting::get('currency.code'));
        $this->assertSame('₦', Setting::get('currency.symbol'));
        $this->assertSame('before', Setting::get('currency.position'));
    }

    public function test_the_currency_code_is_validated(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(ManageSettings::class)
            ->set('currencyCode', '12$')
            ->call('save')
            ->assertHasErrors('currencyCode');

        $this->assertNull(Setting::get('currency.code'));
    }

    public function test_the_symbol_defaults_to_the_code_and_leads_the_amount(): void
    {
        $this->assertSame('KES', Money::code());
        $this->assertSame('KES', Money::symbol());
        $this->assertSame('KES 2,500.00', Money::format(2500, 2));
        $this->assertSame('KES 2,500', Money::format(2500, 0));
    }

    public function test_the_sign_and_position_can_be_overridden(): void
    {
        Setting::set('currency.symbol', 'KSh');
        Setting::set('currency.position', 'after');

        $this->assertSame('2,500.00 KSh', Money::format(2500, 2));
    }

    public function test_a_blank_symbol_falls_back_to_the_configured_code(): void
    {
        Setting::set('currency.code', 'USD');
        Setting::set('currency.symbol', null);

        $this->assertSame('USD', Money::symbol());
        $this->assertSame('USD 1,000.00', Money::format(1000, 2));
    }

    public function test_new_ticket_types_prefill_the_configured_code(): void
    {
        Setting::set('currency.code', 'NGN');

        $this->assertSame('NGN', Money::code());
    }

    public function test_choosing_a_currency_prefills_the_code_sign_and_position(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(ManageSettings::class)
            ->set('currencyPreset', 'USD')
            ->assertSet('currencyCode', 'USD')
            ->assertSet('currencySymbol', '$')
            ->assertSet('currencyPosition', 'before')
            ->set('currencyPreset', 'EUR')
            ->assertSet('currencyCode', 'EUR')
            ->assertSet('currencySymbol', '€')
            ->assertSet('currencyPosition', 'after');
    }

    public function test_typing_a_known_code_syncs_the_picker(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(ManageSettings::class)
            ->set('currencyCode', 'gbp')
            ->assertSet('currencyPreset', 'GBP');
    }

    public function test_the_page_previews_the_sign_as_it_is_edited(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(ManageSettings::class)
            ->set('currencySymbol', 'KSh')
            ->set('currencyPosition', 'before')
            ->assertSee('KSh 2,500')
            ->assertSee('KSh 12,000')
            ->assertSee('KSh 10,000.00');
    }

    public function test_the_public_event_page_uses_the_configured_symbol(): void
    {
        Setting::set('currency.symbol', 'KSh');

        $event = Event::factory()->create(['status' => EventStatus::Published]);
        TicketType::factory()->create([
            'event_id' => $event->id,
            'price' => 2500,
            'currency' => 'KES',
        ]);

        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('2,500')
            ->assertSee('KSh');
    }
}
