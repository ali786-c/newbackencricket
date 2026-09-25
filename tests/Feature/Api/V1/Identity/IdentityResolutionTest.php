<?php

namespace Tests\Feature\Api\V1\Identity;

use App\Models\EntityAlias;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class IdentityResolutionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_resolves_player_code_case_insensitively(): void
    {
        $player = Player::factory()->create(['player_code' => 'STP-P-7K4M9Q2D']);

        $this->getJson('/api/v1/identity/resolve/stp-p-7k4m9q2d')
            ->assertOk()->assertExactJson([
                'type' => 'player', 'canonicalId' => $player->id, 'publicCode' => 'STP-P-7K4M9Q2D',
            ]);
    }

    public function test_resolves_retired_team_code_to_surviving_identity(): void
    {
        $actor = User::factory()->create();
        $retired = Team::factory()->create(['team_code' => 'STP-T-9X2ABC6R']);
        $survivor = Team::factory()->create();
        EntityAlias::factory()->create([
            'entity_type' => 'team', 'retired_id' => $retired->id,
            'surviving_id' => $survivor->id, 'actor_user_id' => $actor->id,
        ]);

        $this->getJson('/api/v1/identity/resolve/STP-T-9X2ABC6R')
            ->assertOk()->assertJsonPath('canonicalId', $survivor->id)->assertJsonPath('type', 'team');
    }

    public function test_unknown_public_code_returns_404(): void
    {
        $this->getJson('/api/v1/identity/resolve/STP-P-00000000')->assertNotFound();
    }
}
