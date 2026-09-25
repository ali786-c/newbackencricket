<?php

namespace App\Domains\Teams\Actions;

use App\Domains\Identity\Exceptions\CanonicalIdentityConflictException;
use App\Domains\Identity\Services\PublicEntityCodeGenerator;
use App\Domains\Sync\Services\IdempotencyService;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTeamAction
{
    private const PUBLIC_CODE_SAVE_ATTEMPTS = 3;

    public function __construct(
        private readonly PublicEntityCodeGenerator $codeGenerator,
        private readonly IdempotencyService $idempotency,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $owner, array $attributes): Team
    {
        for ($attempt = 1; $attempt <= self::PUBLIC_CODE_SAVE_ATTEMPTS; $attempt++) {
            try {
                return $this->createWithinTransaction($owner, $attributes);
            } catch (QueryException $exception) {
                if (! $this->isPublicCodeCollision($exception) || $attempt === self::PUBLIC_CODE_SAVE_ATTEMPTS) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('The team creation retry loop terminated unexpectedly.');
    }

    /** @param array<string, mixed> $attributes */
    private function createWithinTransaction(User $owner, array $attributes): Team
    {
        return DB::transaction(function () use ($owner, $attributes): Team {
            $replay = $this->idempotency->findReplay(
                $owner, 'teams.create', $attributes['idempotencyKey'], $attributes,
            );
            if ($replay !== null) {
                return Team::query()->findOrFail($replay->resource_id);
            }

            $existing = Team::query()->whereKey($attributes['id'])->lockForUpdate()->first();
            $values = [
                'name' => $attributes['name'],
                'normalized_name' => Str::lower(Str::squish($attributes['name'])),
                'short_name' => $attributes['shortName'],
                'city' => $attributes['city'],
                'description' => $attributes['description'] ?? null,
            ];

            if ($existing !== null) {
                $expected = ['owner_user_id' => $owner->id, ...$values];

                if (Arr::only($existing->getAttributes(), array_keys($expected)) === $expected) {
                    $this->recordIdempotency($owner, $attributes, $existing);

                    return $existing;
                }

                throw new CanonicalIdentityConflictException('team', $attributes['id']);
            }

            $code = $this->codeGenerator->teamCode(
                fn (string $candidate): bool => Team::query()->where('team_code', $candidate)->exists(),
            );

            $team = new Team([
                'team_code' => $code->value,
                'owner_user_id' => $owner->id,
                'version' => 1,
                ...$values,
            ]);
            $team->id = $attributes['id'];
            $team->save();
            $this->recordIdempotency($owner, $attributes, $team);

            return $team;
        }, attempts: 3);
    }

    private function isPublicCodeCollision(QueryException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === 1062
            && str_contains($exception->getMessage(), 'teams_team_code_unique');
    }

    /** @param array<string, mixed> $attributes */
    private function recordIdempotency(User $user, array $attributes, Team $team): void
    {
        $this->idempotency->record(
            $user, 'teams.create', $attributes['idempotencyKey'], $attributes, 'team', $team->id, 201,
        );
    }
}
