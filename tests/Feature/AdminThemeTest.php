<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminThemeTest extends TestCase
{
    use RefreshDatabase;

    private function panel(): Panel
    {
        return Filament::getPanel('admin');
    }

    public function test_brand_and_colours_are_configured(): void
    {
        $this->assertSame('Event Tickets', $this->panel()->getBrandName());

        $colors = $this->panel()->getColors();

        $this->assertArrayHasKey('primary', $colors);

        // Filament resolves a hex to a full shade palette (50..950).
        $primary = $colors['primary'];

        $this->assertIsArray($primary);
        $this->assertArrayHasKey(500, $primary);
        $this->assertArrayHasKey(600, $primary);
    }

    public function test_the_theme_stylesheet_is_injected(): void
    {
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('--ct-navy', $html);
        $this->assertStringContainsString('fi-sidebar', $html);
        $this->assertStringContainsString('fi-wi-stats-overview-stat', $html);
    }

    public function test_dark_mode_is_forced_off(): void
    {
        $this->assertTrue($this->panel()->hasDarkModeForced());
        $this->assertFalse($this->panel()->hasThemeSwitcher());
    }

    public function test_the_login_page_is_themed(): void
    {
        $html = $this->get('/admin/login')->assertOk()->getContent();

        $this->assertStringContainsString('--ct-navy', $html);
    }

    public function test_all_three_dashboard_widgets_render(): void
    {
        $event = Event::factory()->create();
        $event->ticketTypes()->create([
            'name' => 'Standard',
            'price' => 5000,
            'quantity_total' => 100,
            'requires_payment' => true,
        ]);

        Ticket::factory()->count(2)->for($event)->create();

        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)->get('/admin')->assertOk()->getContent();

        // Stats overview renders server-side.
        $this->assertStringContainsString('fi-wi-stats-overview-stat', $html);

        // Chart and table widgets lazy-load, so they appear as placeholders
        // with their Livewire component name rather than full markup.
        $this->assertStringContainsString('UpcomingEventsChart', $html);
        $this->assertStringContainsString('RecentCheckIns', $html);
    }

    public function test_theme_palette_is_defined(): void
    {
        $palette = config('admin_theme.palette');

        foreach (['navy', 'ink', 'surface', 'canvas', 'border', 'muted'] as $key) {
            $this->assertArrayHasKey($key, $palette);
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $palette[$key]);
        }
    }
}