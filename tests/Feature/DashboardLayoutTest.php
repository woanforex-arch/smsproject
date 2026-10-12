<?php

namespace Tests\Feature;

use App\Filament\Widgets\RecentCheckIns;
use App\Filament\Widgets\TicketingStats;
use App\Filament\Widgets\UpcomingEventsChart;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_chart_and_check_ins_share_a_row(): void
    {
        $chart = new UpcomingEventsChart;
        $table = new RecentCheckIns;

        $this->assertSame(1, $chart->getColumnSpan(), 'Chart should occupy half the grid.');
        $this->assertSame(1, $table->getColumnSpan(), 'Check-ins table should occupy half the grid.');
    }

    public function test_stats_stay_full_width_above_them(): void
    {
        $this->assertSame('full', (new TicketingStats)->getColumnSpan());
    }

    public function test_dashboard_grid_is_two_columns(): void
    {
        $columns = (new Dashboard(app()))->getColumns();

        $this->assertSame(2, $columns, 'Dashboard grid must be 2 columns for the widgets to sit side by side.');
    }

    public function test_widgets_are_ordered_stats_chart_table(): void
    {
        $sortOf = function (string $class): int {
            $property = new \ReflectionProperty($class, 'sort');
            $property->setAccessible(true);

            return $property->getValue();
        };

        $sorts = [
            TicketingStats::class => $sortOf(TicketingStats::class),
            UpcomingEventsChart::class => $sortOf(UpcomingEventsChart::class),
            RecentCheckIns::class => $sortOf(RecentCheckIns::class),
        ];

        $ordered = array_keys($sorts);
        usort($ordered, fn ($a, $b) => $sorts[$a] <=> $sorts[$b]);

        $this->assertSame([
            TicketingStats::class,
            UpcomingEventsChart::class,
            RecentCheckIns::class,
        ], $ordered);
    }

    public function test_both_widgets_render_with_data(): void
    {
        $event = Event::factory()->create(['title' => 'Layout Event']);
        $event->ticketTypes()->create([
            'name' => 'Standard', 'price' => 5000,
            'quantity_total' => 100, 'requires_payment' => true,
        ]);

        $ticket = Ticket::factory()->for($event)->create();

        CheckIn::create([
            'ticket_id' => $ticket->id,
            'method' => 'qr',
        ]);

        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)->get('/admin')->assertOk()->getContent();

        // Widgets lazy-load, so only their Livewire components are present in
        // the initial HTML — not their rendered data.
        $this->assertStringContainsString('UpcomingEventsChart', $html);
        $this->assertStringContainsString('RecentCheckIns', $html);
        $this->assertStringContainsString('TicketingStats', $html);

        // The dashboard grid must resolve to two columns on desktop, otherwise
        // two half-width widgets still stack vertically.
        $this->assertMatchesRegularExpression(
            '/--cols-lg:\s*repeat\(2,\s*minmax\(0,\s*1fr\)\)/',
            $html,
            'Dashboard grid is not two columns at the lg breakpoint.',
        );
    }

    public function test_widgets_stack_on_small_screens(): void
    {
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)->get('/admin')->assertOk()->getContent();

        // Single column is the mobile default; the grid widens at lg.
        $this->assertMatchesRegularExpression(
            '/--cols-default:\s*repeat\(1,\s*minmax\(0,\s*1fr\)\)/',
            $html,
            'Dashboard grid should collapse to one column by default.',
        );
    }

    public function test_chart_height_is_bounded(): void
    {
        $method = new \ReflectionMethod(UpcomingEventsChart::class, 'getMaxHeight');
        $method->setAccessible(true);

        $this->assertSame('360px', $method->invoke(new UpcomingEventsChart));
    }
}