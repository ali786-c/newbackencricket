<?php

namespace App\Domains\Teams\Actions;

use App\Domains\Identity\Exceptions\CanonicalIdentityConflictException;
use App\Domains\Identity\Services\PublicEntityCodeGenerator;
use App\Domains\Sync\Services\IdempotencyService;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTeamAction
{
    public function __construct(
        private readonly PublicEntityCodeGenerator $codeGenerator,
        private readonly IdempotencyService $idempotency,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $owner, array $attributes): Team
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
        });
    }

    /** @param array<string, mixed> $attributes */
    private function recordIdempotency(User $user, array $attributes, Team $team): void
    {
        $this->idempotency->record(
            $user, 'teams.create', $attributes['idempotencyKey'], $attributes, 'team', $team->id, 201,
        );
    }
}
