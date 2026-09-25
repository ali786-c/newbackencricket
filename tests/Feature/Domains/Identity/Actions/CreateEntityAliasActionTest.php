<?php

namespace Tests\Feature\Domains\Identity\Actions;

use App\Domains\Identity\Actions\CreateEntityAliasAction;
use App\Domains\Identity\Exceptions\InvalidEntityAliasException;
use App\Models\EntityAlias;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CreateEntityAliasActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_an_alias_between_existing_players(): void
    {
        $actor = User::factory()->create();
        $retired = Player::factory()->create();
        $survivor = Player::factory()->create();

        $alias = (new CreateEntityAliasAction)->handle(
            'player', $retired->id, $survivor->id, $actor, 'Duplicate player confirmed.',
        );

        $this->assertModelExists($alias);
        $this->assertSame($retired->id, $alias->retired_id);
        $this->assertSame($survivor->id, $alias->surviving_id);
        $this->assertSame($actor->id, $alias->actor_user_id);
    }

    public function test_flattens_an_existing_alias_chain_to_its_terminal_identity(): void
    {
        $actor = User::factory()->create();
        $first = Team::factory()->create();
        $second = Team::factory()->create();
        $terminal = Team::factory()->create();
        EntityAlias::factory()->create([
            'entity_type' => 'team', 'retired_id' => $second->id,
            'surviving_id' => $terminal->id, 'actor_user_id' => $actor->id,
        ]);

        $alias = (new CreateEntityAliasAction)->handle(
            'team', $first->id, $second->id, $actor, 'Flatten duplicate chain.',
        );

        $this->assertSame($terminal->id, $alias->surviving_id);
    }

    public function test_rejects_an_alias_that_would_create_a_cycle_without_writing(): void
    {
        $actor = User::factory()->create();
        $first = Player::factory()->create();
        $second = Player::factory()->create();
        EntityAlias::factory()->create([
            'entity_type' => 'player', 'retired_id' => $first->id,
            'surviving_id' => $second->id, 'actor_user_id' => $actor->id,
        ]);

        try {
            (new CreateEntityAliasAction)->handle(
                'player', $second->id, $first->id, $actor, 'Invalid reverse merge.',
            );
            $this->fail('Expected the alias cycle to be rejected.');
        } catch (InvalidEntityAliasException $exception) {
            $this->assertStringContainsString('cycle', $exception->getMessage());
        }

        $this->assertDatabaseCount('entity_aliases', 1);
    }

    public function test_rejects_an_alias_when_an_identity_does_not_exist(): void
    {
        $actor = User::factory()->create();
        $retired = Team::factory()->create();
        $this->expectException(InvalidEntityAliasException::class);

        try {
            (new CreateEntityAliasAction)->handle(
                'team', $retired->id, '01K5MISSINGIDENTITY00000000', $actor, 'Missing survivor.',
            );
        } finally {
            $this->assertDatabaseCount('entity_aliases', 0);
        }
    }
}
