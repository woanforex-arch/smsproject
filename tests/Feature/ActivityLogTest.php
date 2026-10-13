<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Payment;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_creating_a_model_records_an_entry(): void
    {
        $event = Event::factory()->create(['title' => 'Nairobi Tech Summit']);

        $log = ActivityLog::where('event', 'created')
            ->where('subject_type', $event->getMorphClass())
            ->where('subject_id', $event->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Nairobi Tech Summit', $log->description);
    }

    public function test_updating_a_model_records_the_before_and_after_values(): void
    {
        $event = Event::factory()->create(['title' => 'Old title']);

        ActivityLog::query()->delete();

        $event->update(['title' => 'New title']);

        $log = ActivityLog::where('event', 'updated')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('Old title', $log->properties['before']['title']);
        $this->assertSame('New title', $log->properties['after']['title']);
    }

    public function test_deleting_a_model_is_recorded(): void
    {
        $event = Event::factory()->create();
        $id = $event->id;

        ActivityLog::query()->delete();

        $event->delete();

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'deleted',
            'subject_type' => $event->getMorphClass(),
            'subject_id' => $id,
        ]);
    }

    public function test_bulky_gateway_payloads_are_left_out_of_the_snapshot(): void
    {
        $payment = Payment::factory()->create(['raw_payload' => ['secret' => 'gateway']]);

        $log = ActivityLog::where('event', 'created')
            ->where('subject_type', $payment->getMorphClass())
            ->where('subject_id', $payment->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('raw_payload', $log->properties['attributes']);
        $this->assertArrayNotHasKey('meta', $log->properties['attributes']);
    }

    public function test_an_update_to_an_ignored_column_is_not_logged(): void
    {
        $payment = Payment::factory()->create();

        $before = ActivityLog::where('subject_type', $payment->getMorphClass())
            ->where('subject_id', $payment->id)
            ->count();

        $payment->update(['raw_payload' => ['a' => 1]]);

        $after = ActivityLog::where('subject_type', $payment->getMorphClass())
            ->where('subject_id', $payment->id)
            ->count();

        $this->assertSame($before, $after);
    }

    public function test_signing_in_is_recorded_and_stamps_the_last_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        ActivityLog::query()->delete();

        event(new Login('web', $user, false));

        $log = ActivityLog::where('event', 'login')->first();

        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->user_id);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_admins_can_open_the_activity_log(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(ActivityLogResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Activity log');
    }

    public function test_staff_cannot_open_the_activity_log(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->get(ActivityLogResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_an_admin_can_clear_the_activity_log(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Event::factory()->create();
        $this->assertGreaterThan(0, ActivityLog::count());

        Livewire::test(ListActivityLogs::class)->callAction('clear');

        $this->assertSame(0, ActivityLog::count());
    }

    public function test_the_settings_hub_exposes_its_sections(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(ManageSettings::class)
            ->call('setTab', 'exports')
            ->assertSet('activeTab', 'exports')
            ->assertSee('Export data')
            ->assertSee('Staff accounts')
            ->call('setTab', 'system')
            ->assertSee('Clear caches')
            ->call('setTab', 'accounts')
            ->assertSee('User accounts')
            ->call('setTab', 'not-a-real-tab')
            ->assertSet('activeTab', 'accounts');
    }

    public function test_the_settings_activity_tab_lists_recent_entries(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Event::factory()->create(['title' => 'Coast Innovation Forum']);

        Livewire::test(ManageSettings::class)
            ->call('setTab', 'activity')
            ->assertSee('Recent activity')
            ->assertSee('Coast Innovation Forum');
    }

    public function test_an_admin_can_clear_caches_from_the_system_tab(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(ManageSettings::class)
            ->call('setTab', 'system')
            ->call('clearCaches')
            ->assertOk();
    }

    public function test_downloading_an_export_is_recorded(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        ActivityLog::query()->delete();

        $this->get(route('exports.users'))->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'export',
            'user_id' => $admin->id,
        ]);
    }
}
