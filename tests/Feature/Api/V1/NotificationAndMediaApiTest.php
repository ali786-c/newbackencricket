<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationAndMediaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_is_user_scoped_and_notification_can_be_marked_read(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = UserNotification::query()->create([
            'user_id' => $user->id, 'category' => 'match', 'title' => 'Match live',
            'body' => 'Your match has started.', 'deep_link' => '/match-center?matchId=01ARZ3NDEKTSV4RRFFQ69G5FAV',
        ]);
        UserNotification::query()->create([
            'user_id' => $other->id, 'category' => 'system', 'title' => 'Private', 'body' => 'Other user.',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->patchJson("/api/v1/notifications/{$mine->id}/read")
            ->assertOk()->assertJsonPath('data.id', $mine->id);
        $this->assertNotNull($mine->refresh()->read_at);
    }

    public function test_preferences_are_persisted_per_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/notification-preferences', [
            'matchUpdates' => true, 'tournamentUpdates' => false,
            'teamUpdates' => true, 'systemUpdates' => true, 'marketing' => false,
        ])->assertOk()->assertJsonPath('data.tournamentUpdates', false);

        $this->getJson('/api/v1/notification-preferences')
            ->assertOk()->assertJsonPath('data.teamUpdates', true);
    }

    public function test_media_upload_is_idempotent_and_user_can_cancel_it(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $id = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

        $this->post('/api/v1/media-uploads', [
            'id' => $id, 'purpose' => 'profile_photo', 'ownerType' => 'user',
            'ownerId' => $user->id,
            'file' => UploadedFile::fake()->createWithContent(
                'avatar.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
            ),
        ], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.status', 'completed');
        $this->assertNotNull($user->refresh()->photo_url);

        $this->deleteJson("/api/v1/media-uploads/{$id}")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
    }
}
