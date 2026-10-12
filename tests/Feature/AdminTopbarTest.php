<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTopbarTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_has_no_welcome_card(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Test Admin']);

        $response = $this->actingAs($admin)->get('/admin')->assertOk();

        // The AccountWidget's heading must be gone.
        $response->assertDontSee('fi-account-widget', escape: false);
        $response->assertDontSee('Welcome back', escape: false);

        // The avatar user menu must still be present so users can sign out.
        $response->assertSee('fi-user-menu', escape: false);
        $response->assertSee('Test Admin', escape: false);
    }

    public function test_the_logout_route_is_still_reachable(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/logout')->assertRedirect();

        $this->assertGuest();
    }

    public function test_admin_pages_have_no_welcome_card(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['/admin', '/admin/events', '/admin/tickets', '/admin/registrations'] as $url) {
            $this->actingAs($admin)->get($url)
                ->assertOk()
                ->assertDontSee('fi-account-widget', escape: false);
        }
    }
}