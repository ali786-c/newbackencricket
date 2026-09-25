<?php

namespace App\Domains\Players\Actions;

use App\Domains\Identity\Exceptions\CanonicalIdentityConflictException;
use App\Domains\Identity\Services\PublicEntityCodeGenerator;
use App\Domains\Sync\Services\IdempotencyService;
use App\Models\Player;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreatePlayerAction
{
    private const PUBLIC_CODE_SAVE_ATTEMPTS = 3;

    public function __construct(
        private readonly PublicEntityCodeGenerator $codeGenerator,
        private readonly IdempotencyService $idempotency,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $creator, array $attributes): Player
    {
        for ($attempt = 1; $attempt <= self::PUBLIC_CODE_SAVE_ATTEMPTS; $attempt++) {
            try {
                return $this->createWithinTransaction($creator, $attributes);
            } catch (QueryException $exception) {
                if (! $this->isPublicCodeCollision($exception) || $attempt === self::PUBLIC_CODE_SAVE_ATTEMPTS) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('The player creation retry loop terminated unexpectedly.');
    }

    /** @param array<string, mixed> $attributes */
    private function createWithinTransaction(User $creator, array $attributes): Player
    {
        return DB::transaction(function () use ($creator, $attributes): Player {
            $replay = $this->idempotency->findReplay(
                $creator, 'players.create', $attributes['idempotencyKey'], $attributes,
            );
            if ($replay !== null) {
                return Player::query()->findOrFail($replay->resource_id);
            }

            $existing = Player::query()->whereKey($attributes['id'])->lockForUpdate()->first();
            $values = [
                'name' => $attributes['name'],
                'normalized_name' => Str::lower(Str::squish($attributes['name'])),
                'city' => $attributes['city'] ?? null,
                'playing_role' => $attributes['playingRole'],
                'batting_style' => $attributes['battingStyle'],
                'bowling_style' => $attributes['bowlingStyle'],
                'bio' => $attributes['bio'] ?? null,
            ];

            if ($existing !== null) {
                $expected = ['created_by_user_id' => $creator->id, ...$values];

                if (Arr::only($existing->getAttributes(), array_keys($expected)) === $expected) {
                    $this->recordIdempotency($creator, $attributes, $existing);

                    return $existing;
                }

                throw new CanonicalIdentityConflictException('player', $attributes['id']);
            }

            $code = $this->codeGenerator->playerCode(
                fn (string $candidate): bool => Player::query()->where('player_code', $candidate)->exists(),
            );

            $player = new Player([
                'player_code' => $code->value,
                'created_by_user_id' => $creator->id,
                'claim_status' => 'unclaimed',
                'version' => 1,
                ...$values,
            ]);
            $player->id = $attributes['id'];
            $player->save();
            $this->recordIdempotency($creator, $attributes, $player);

            return $player;
        }, attempts: 3);
    }

    private function isPublicCodeCollision(QueryException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === 1062
            && str_contains($exception->getMessage(), 'players_player_code_unique');
    }

    /** @param array<string, mixed> $attributes */
    private function recordIdempotency(User $user, array $attributes, Player $player): void
    {
        $this->idempotency->record(
            $user, 'players.create', $attributes['idempotencyKey'], $attributes, 'player', $player->id, 201,
        );
    }
}
