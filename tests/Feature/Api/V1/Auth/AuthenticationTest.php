<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_issues_token_and_returns_stable_user_resource(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ali Player', 'email' => 'ALI@example.com',
            'password' => 'Cricket123', 'password_confirmation' => 'Cricket123',
            'deviceName' => 'Ali Android', 'futureField' => 'ignored',
        ]);

        $response->assertCreated()->assertJsonPath('data.user.email', 'ali@example.com')
            ->assertJsonPath('data.tokenType', 'Bearer')->assertJsonPath('meta.apiVersion', 'v1');
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('users', ['email' => 'ali@example.com']);
    }

    public function test_login_me_and_logout_current_device(): void
    {
        $user = User::factory()->create(['password' => 'Cricket123']);
        $login = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Cricket123', 'deviceName' => 'Phone'])
            ->assertOk();
        $token = $login->json('data.token');

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.id', $user->id);
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_logout_all_revokes_every_device_token(): void
    {
        $user = User::factory()->create();
        $first = $user->createToken('first')->plainTextToken;
        $second = $user->createToken('second')->plainTextToken;
        $this->withToken($first)->postJson('/api/v1/auth/logout-all')->assertOk();
        $this->assertNotEmpty($second);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_forgot_password_is_enumeration_safe(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email]);
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'missing@example.com']);
        $known->assertOk();
        $unknown->assertOk()->assertExactJson($known->json());
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_token_is_one_time_and_revokes_existing_tokens(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $user->createToken('old');
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email]);
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'Updated123', 'password_confirmation' => 'Updated123'];
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertOk();
        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertStatus(422)->assertJsonPath('error.code', 'invalid_reset_token');
    }

    public function test_errors_include_machine_code_request_id_and_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);
        $response->assertStatus(422)->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['message', 'requestId', 'fieldErrors', 'conflict']]);
        $this->assertSame($response->headers->get('X-Request-ID'), $response->json('error.requestId'));
    }

    public function test_login_rate_limit_returns_stable_api_error(): void
    {
        $payload = ['email' => 'limited@example.com', 'password' => 'wrong-password', 'deviceName' => 'Test Phone'];

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/v1/auth/login', $payload)->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', $payload)
            ->assertTooManyRequests()
            ->assertJsonPath('error.code', 'rate_limited')
            ->assertJsonStructure(['error' => ['requestId']]);
    }

    public function test_health_endpoints_report_liveness_and_readiness(): void
    {
        $this->getJson('/api/v1/health/live')->assertOk()->assertJsonPath('status', 'ok');
        $this->getJson('/api/v1/health/ready')
            ->assertOk()
            ->assertJsonPath('dependencies.database', 'ok')
            ->assertJsonPath('rolloutStage', 'internal_alpha');
    }

    public function test_authenticated_user_can_complete_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/v1/auth/profile', [
            'city' => 'Rawalpindi', 'playingRole' => 'All-rounder',
            'battingStyle' => 'Right hand', 'bowlingStyle' => 'Right arm fast', 'bio' => 'Club cricketer',
        ])->assertOk()->assertJsonPath('data.city', 'Rawalpindi')->assertJsonPath('data.playingRole', 'All-rounder');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'city' => 'Rawalpindi', 'playing_role' => 'All-rounder']);
    }
}
