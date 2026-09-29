<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_api_rejects_plain_http_and_accepts_forwarded_https(): void
    {
        $original = $this->app->environment();
        $this->app->detectEnvironment(fn (): string => 'production');

        $response = $this->getJson('/api/v1/health/live')
            ->assertStatus(426)
            ->assertJsonPath('error.code', 'https_required');
        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
        $this->assertSame($response->headers->get('X-Request-ID'), $response->json('error.requestId'));
        $this->withServerVariables(['HTTP_X_FORWARDED_PROTO' => 'https'])
            ->getJson('/api/v1/health/live')->assertOk();

        $this->app->detectEnvironment(fn (): string => $original);
    }

    public function test_login_token_has_server_side_expiry_and_can_be_revoked(): void
    {
        $user = User::factory()->create(['password' => 'Cricket123']);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Cricket123',
            'deviceName' => 'Secure phone',
        ])->assertOk()->json('data.token');

        $stored = $user->tokens()->firstOrFail();
        $this->assertNotNull($stored->expires_at);
        $this->assertTrue($stored->expires_at->isFuture());
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $stored->id]);
    }

    public function test_request_logging_never_contains_credentials_or_authorization_header(): void
    {
        Log::spy();
        $email = 'private@example.com';
        $password = 'NeverLogThis123';

        $this->withHeader('Authorization', 'Bearer secret-token')
            ->postJson('/api/v1/auth/login', [
                'email' => $email,
                'password' => $password,
                'deviceName' => 'Phone',
            ]);

        Log::shouldHaveReceived('info')->with('api_request_completed', Mockery::on(
            function (array $context) use ($email, $password): bool {
                $encoded = json_encode($context, JSON_THROW_ON_ERROR);

                return ! str_contains($encoded, $email)
                    && ! str_contains($encoded, $password)
                    && ! str_contains($encoded, 'secret-token');
            }
        ));
    }

    public function test_upload_purpose_must_match_an_authorized_owner_type(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/media-uploads', [
            'id' => (string) Str::ulid(),
            'purpose' => 'profile_photo',
            'ownerType' => 'team',
            'ownerId' => null,
            'file' => UploadedFile::fake()->create('avatar.jpg', 1, 'image/jpeg'),
        ])->assertUnprocessable();
    }

    public function test_hardening_indexes_and_utc_configuration_are_present(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $indexes = collect(DB::select("SELECT name FROM sqlite_master WHERE type='index'"))
            ->pluck('name');

        $this->assertTrue($indexes->contains('sync_user_status_time_idx'));
        $this->assertTrue($indexes->contains('match_events_match_created_idx'));
        $this->assertTrue($indexes->contains('media_status_updated_idx'));
    }

    public function test_sync_monitor_records_only_safe_operational_metadata(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/sync/outbox', [])->assertUnprocessable();

        $event = DB::table('sync_health_events')->first();
        $this->assertSame('rejected', $event->category);
        $this->assertSame(422, $event->http_status);
        $this->assertObjectNotHasProperty('payload', $event);
        $this->artisan('stumps:sync-health')->assertSuccessful();
    }

    public function test_authenticated_api_has_a_configurable_global_rate_limit(): void
    {
        config()->set('stumps.api_rate_limit', 2);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')->assertOk();
        $this->getJson('/api/v1/auth/me')->assertOk();
        $this->getJson('/api/v1/auth/me')
            ->assertTooManyRequests()
            ->assertJsonPath('error.code', 'rate_limited');
    }
}
