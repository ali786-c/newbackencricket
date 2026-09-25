<?php

namespace Tests\Feature;

use App\Domains\Identity\Actions\DecideEntityMergeAction;
use App\Domains\Identity\Exceptions\EntityMergeConflictException;
use App\Domains\Identity\Services\PublicEntityCodeGenerator;
use App\Domains\Players\Actions\CreatePlayerAction;
use App\Domains\Sync\Services\IdempotencyService;
use App\Domains\Teams\Actions\CreateTeamMembershipAction;
use App\Models\EntityMergeRequest;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Str;
use Tests\TestCase;
use Throwable;

class MySqlIdentityConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_simultaneous_player_code_collision_retries_with_a_new_code(): void
    {
        $this->requireMySql();

        $creators = User::factory()->count(2)->create();
        $barrier = storage_path('framework/testing/'.Str::ulid());

        try {
            $results = Concurrency::driver('process')->run([
                static fn (): array => self::createPlayerAtBarrier($creators[0]->id, $barrier, 'A'),
                static fn (): array => self::createPlayerAtBarrier($creators[1]->id, $barrier, 'B'),
            ], timeout: 20);
        } finally {
            @unlink($barrier.'-A');
            @unlink($barrier.'-B');
        }

        $this->assertSame(['created', 'created'], array_column($results, 'status'));
        $this->assertCount(2, array_unique(array_column($results, 'code')));
        $this->assertDatabaseCount('players', 2);
    }

    public function test_simultaneous_overlapping_memberships_create_only_one_interval(): void
    {
        $this->requireMySql();

        $team = Team::factory()->create();
        $player = Player::factory()->create();

        $results = Concurrency::driver('process')->run([
            static fn (): string => self::createMembership($team->id, $player->id),
            static fn (): string => self::createMembership($team->id, $player->id),
        ], timeout: 20);

        sort($results);
        $this->assertSame(['created', 'overlap'], $results);
        $this->assertDatabaseCount('team_memberships', 1);
    }

    public function test_simultaneous_merge_execution_runs_exactly_once(): void
    {
        $this->requireMySql();

        $actor = User::factory()->create();
        $retired = Player::factory()->for($actor, 'createdByUser')->create();
        $surviving = Player::factory()->for($actor, 'createdByUser')->create();
        $merge = EntityMergeRequest::factory()->create([
            'retired_id' => $retired->id,
            'surviving_id' => $surviving->id,
            'requested_by_user_id' => $actor->id,
        ]);

        $results = Concurrency::driver('process')->run([
            static fn (): string => self::executeMerge($merge->id, $actor->id),
            static fn (): string => self::executeMerge($merge->id, $actor->id),
        ], timeout: 20);

        sort($results);
        $this->assertSame(['executed', 'merge_not_pending'], $results);
        $this->assertDatabaseCount('entity_aliases', 1);
        $this->assertNotNull($retired->fresh()->archived_at);
        $this->assertSame(2, $surviving->fresh()->version);
    }

    /** @return array{status: string, code: string} */
    private static function createPlayerAtBarrier(string $creatorId, string $barrier, string $worker): array
    {
        $calls = 0;
        $generator = new PublicEntityCodeGenerator(function () use (&$calls, $barrier, $worker): string {
            $calls++;
            if ($calls > 1) {
                return 'ABCDEF1'.$worker;
            }

            touch($barrier.'-'.$worker);
            $otherWorker = $worker === 'A' ? 'B' : 'A';
            $deadline = microtime(true) + 10;
            while (! file_exists($barrier.'-'.$otherWorker)) {
                if (microtime(true) >= $deadline) {
                    throw new \RuntimeException('Public-code concurrency barrier timed out.');
                }
                usleep(10_000);
            }

            return 'ABCD1234';
        });

        try {
            $action = new CreatePlayerAction($generator, app(IdempotencyService::class));
            $player = $action->handle(User::query()->findOrFail($creatorId), [
                'id' => (string) Str::ulid(),
                'idempotencyKey' => (string) Str::ulid(),
                'name' => 'Concurrent Player '.$worker,
                'city' => 'Rawalpindi',
                'playingRole' => 'batter',
                'battingStyle' => 'right_hand',
                'bowlingStyle' => 'right_arm_medium',
            ]);

            return ['status' => 'created', 'code' => $player->player_code];
        } catch (Throwable $exception) {
            return ['status' => $exception::class, 'code' => $exception->getMessage()];
        }
    }

    private static function createMembership(string $teamId, string $playerId): string
    {
        try {
            app(CreateTeamMembershipAction::class)->handle(
                Team::query()->findOrFail($teamId),
                Player::query()->findOrFail($playerId),
                now('UTC'),
            );

            return 'created';
        } catch (Throwable $exception) {
            if (str_contains($exception->getMessage(), 'overlapping membership')) {
                return 'overlap';
            }

            throw $exception;
        }
    }

    private static function executeMerge(string $mergeId, string $actorId): string
    {
        try {
            app(DecideEntityMergeAction::class)->handle(
                EntityMergeRequest::query()->findOrFail($mergeId),
                User::query()->findOrFail($actorId),
                'execute',
            );

            return 'executed';
        } catch (EntityMergeConflictException $exception) {
            return $exception->reason;
        }
    }

    private function requireMySql(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('This concurrency suite requires the dedicated MySQL test configuration.');
        }
    }
}
