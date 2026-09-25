<?php

namespace Tests\Feature\Domains\Teams\Actions;

use App\Domains\Teams\Actions\CreateTeamMembershipAction;
use App\Domains\Teams\Exceptions\OverlappingTeamMembershipException;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamMembership;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CreateTeamMembershipActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_a_membership_with_derived_active_status(): void
    {
        $team = Team::factory()->create();
        $player = Player::factory()->create();

        $membership = (new CreateTeamMembershipAction)->handle(
            $team, $player, now()->parse('2026-01-01 10:00:00'), teamRole: 'captain',
        );

        $this->assertModelExists($membership);
        $this->assertSame('active', $membership->status);
        $this->assertSame('captain', $membership->team_role);
        $this->assertSame($team->id, $membership->team_id);
        $this->assertSame($player->id, $membership->player_id);
    }

    public function test_rejects_an_overlapping_membership_for_the_same_team_and_player(): void
    {
        $team = Team::factory()->create();
        $player = Player::factory()->create();
        TeamMembership::factory()->for($team)->for($player)->create([
            'joined_at' => '2026-01-01 00:00:00',
            'left_at' => '2026-06-30 23:59:59',
            'status' => 'past',
        ]);

        try {
            (new CreateTeamMembershipAction)->handle($team, $player, now()->parse('2026-06-01 00:00:00'));
            $this->fail('Expected the overlapping interval to be rejected.');
        } catch (OverlappingTeamMembershipException $exception) {
            $this->assertSame($team->id, $exception->teamId);
            $this->assertSame($player->id, $exception->playerId);
        }

        $this->assertDatabaseCount('team_memberships', 1);
    }

    public function test_allows_non_overlapping_membership_history(): void
    {
        $team = Team::factory()->create();
        $player = Player::factory()->create();
        TeamMembership::factory()->for($team)->for($player)->create([
            'joined_at' => '2025-01-01 00:00:00',
            'left_at' => '2025-12-31 23:59:59',
            'status' => 'past',
        ]);

        $membership = (new CreateTeamMembershipAction)->handle($team, $player, now()->parse('2026-01-01 00:00:00'));

        $this->assertModelExists($membership);
        $this->assertDatabaseCount('team_memberships', 2);
    }

    public function test_rejects_an_end_before_the_start_without_writing(): void
    {
        $team = Team::factory()->create();
        $player = Player::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        try {
            (new CreateTeamMembershipAction)->handle(
                $team, $player, now()->parse('2026-02-01'), now()->parse('2026-01-01'),
            );
        } finally {
            $this->assertDatabaseCount('team_memberships', 0);
        }
    }
}
