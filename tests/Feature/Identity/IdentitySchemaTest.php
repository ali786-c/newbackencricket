<?php

namespace Tests\Feature\Identity;

use App\Models\EntityAlias;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentitySchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_identity_records_use_ulids_and_preserve_relationships(): void
    {
        $owner = User::factory()->create();
        $player = Player::factory()->for($owner, 'createdByUser')->create();
        $team = Team::factory()->for($owner, 'owner')->create();
        $membership = TeamMembership::factory()->for($team)->for($player)->create();

        $this->assertTrue(Str::isUlid($owner->id));
        $this->assertTrue(Str::isUlid($player->id));
        $this->assertTrue(Str::isUlid($team->id));
        $this->assertTrue(Str::isUlid($membership->id));

        $this->assertTrue($membership->team->is($team));
        $this->assertTrue($membership->player->is($player));
        $this->assertTrue($player->createdByUser->is($owner));
        $this->assertTrue($team->owner->is($owner));
    }

    public function test_player_public_code_is_unique(): void
    {
        $player = Player::factory()->create();

        $this->expectException(QueryException::class);

        Player::factory()->create(['player_code' => $player->player_code]);
    }

    public function test_one_player_can_have_memberships_in_multiple_teams(): void
    {
        $player = Player::factory()->create();
        $firstMembership = TeamMembership::factory()->for($player)->create();
        $secondMembership = TeamMembership::factory()->for($player)->create();

        $this->assertSame($player->id, $firstMembership->player_id);
        $this->assertSame($player->id, $secondMembership->player_id);
        $this->assertNotSame($firstMembership->team_id, $secondMembership->team_id);
    }

    public function test_alias_retired_identity_is_unique_within_an_entity_type(): void
    {
        $alias = EntityAlias::factory()->create();

        $this->expectException(QueryException::class);

        EntityAlias::factory()->create([
            'entity_type' => $alias->entity_type,
            'retired_id' => $alias->retired_id,
        ]);
    }
}
