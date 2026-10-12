<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_admin_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_an_active_admin_can_open_the_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_an_active_staff_member_can_open_the_dashboard(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $this->actingAs($staff)->get('/admin')->assertOk();
    }

    public function test_a_deactivated_account_cannot_open_the_panel(): void
    {
        $user = User::factory()->create(['role' => 'staff', 'is_active' => false]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_event_management_pages_load(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $event = Event::factory()->create(['status' => EventStatus::Published]);

        $this->actingAs($admin)->get('/admin/events')->assertOk();
        $this->actingAs($admin)->get('/admin/events/create')->assertOk();
        $this->actingAs($admin)->get("/admin/events/{$event->id}")->assertOk();
        $this->actingAs($admin)->get("/admin/events/{$event->id}/edit")->assertOk();
    }

    public function test_check_in_pages_load(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get('/admin/check-in-console')->assertOk();
        $this->actingAs($staff)->get('/admin/qr-scanner')->assertOk();
    }

    public function test_ticketing_and_administration_pages_load(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/tickets')->assertOk();
        $this->actingAs($admin)->get('/admin/registrations')->assertOk();
        $this->actingAs($admin)->get('/admin/payments')->assertOk();
        $this->actingAs($admin)->get('/admin/sms-messages')->assertOk();
        $this->actingAs($admin)->get('/admin/users')->assertOk();
    }

    public function test_payment_pages_load(): void
    {
        $admin = User::factory()->admin()->create();

        $payment = \App\Models\Payment::factory()->create();

        $this->actingAs($admin)->get('/admin/payments')->assertOk();
        $this->actingAs($admin)->get("/admin/payments/{$payment->id}")->assertOk();
    }

    public function test_csv_export_is_restricted_to_admins(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get('/admin/exports/registrations')->assertForbidden();
        $this->actingAs($staff)->get('/admin/exports/payments')->assertForbidden();
    }

    public function test_all_csv_exports_render(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['registrations', 'tickets', 'payments', 'summary'] as $report) {
            $this->actingAs($admin)->get("/admin/exports/{$report}")->assertOk();
        }
    }

    public function test_a_soft_deleted_event_can_still_be_opened_for_admin_cleanup(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $event = Event::factory()->create();
        $event->delete();

        $this->actingAs($admin)->get("/admin/events/{$event->id}/edit")->assertOk();
    }
}