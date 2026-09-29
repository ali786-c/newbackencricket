<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminConsoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_open_dashboard(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin')->assertOk()->assertSee('STUMPS Control Center');
    }

    public function test_non_admin_credentials_cannot_start_admin_session(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_controls_are_persisted_audited_and_enforced(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->put('/admin/controls', [
            'registration_enabled' => '0', 'api_read_only' => '1', 'rollout_stage' => 'closed_beta',
        ])->assertRedirect();

        $this->assertDatabaseHas('system_settings', ['key' => 'api_read_only', 'value' => '1']);
        $this->assertDatabaseHas('admin_audit_logs', ['admin_user_id' => $admin->id, 'action' => 'api_controls_updated']);
        $this->postJson('/api/v1/auth/register', [])->assertServiceUnavailable()->assertJsonPath('error.code', 'registration_disabled');
        $this->postJson('/api/v1/auth/forgot-password', [])->assertServiceUnavailable()->assertJsonPath('error.code', 'api_read_only');
        $this->getJson('/api/v1/health/live')->assertOk();
    }

    public function test_admin_promotion_command_requires_an_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $this->artisan('stumps:admin', ['email' => 'owner@example.com'])->assertSuccessful();
        $this->assertTrue($user->refresh()->is_admin);
        $this->artisan('stumps:admin', ['email' => 'missing@example.com'])->assertFailed();
    }

    public function test_admin_create_command_uses_hidden_confirmed_password(): void
    {
        $this->artisan('stumps:admin-create', ['email' => 'ADMIN@example.com', '--name' => 'System Admin'])
            ->expectsQuestion('Password (minimum 12 characters)', 'strong-password-2026')
            ->expectsQuestion('Confirm password', 'strong-password-2026')
            ->expectsOutputToContain('created successfully')
            ->assertSuccessful();

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertTrue($admin->is_admin);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(password_verify('strong-password-2026', $admin->password));
    }

    public function test_admin_create_rejects_duplicate_email_and_weak_password(): void
    {
        User::factory()->create(['email' => 'admin@example.com']);

        $this->artisan('stumps:admin-create', ['email' => 'admin@example.com', '--name' => 'Admin'])
            ->expectsQuestion('Password (minimum 12 characters)', 'short')
            ->expectsQuestion('Confirm password', 'different')
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
    }
}
