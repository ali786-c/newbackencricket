<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Exceptions\InvalidEntityAliasException;
use App\Models\EntityAlias;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateEntityAliasAction
{
    public function handle(
        string $entityType,
        string $retiredId,
        string $survivingId,
        User $actor,
        string $reason,
    ): EntityAlias {
        return DB::transaction(function () use ($entityType, $retiredId, $survivingId, $actor, $reason): EntityAlias {
            $model = $this->modelFor($entityType);
            $entities = $model::query()
                ->whereKey([$retiredId, $survivingId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($entities->count() !== 2) {
                throw InvalidEntityAliasException::missingEntity($entityType);
            }

            $terminalSurvivorId = $this->resolveTerminalId($entityType, $survivingId);

            if ($retiredId === $terminalSurvivorId) {
                throw InvalidEntityAliasException::cycle($entityType, $retiredId);
            }

            return EntityAlias::query()->create([
                'entity_type' => $entityType,
                'retired_id' => $retiredId,
                'surviving_id' => $terminalSurvivorId,
                'actor_user_id' => $actor->id,
                'reason' => $reason,
            ]);
        });
    }

    /** @return class-string<Model> */
    private function modelFor(string $entityType): string
    {
        return match ($entityType) {
            'player' => Player::class,
            'team' => Team::class,
            default => throw InvalidEntityAliasException::unsupportedType($entityType),
        };
    }

    private function resolveTerminalId(string $entityType, string $startingId): string
    {
        $currentId = $startingId;
        $visited = [];

        while (true) {
            if (isset($visited[$currentId])) {
                throw InvalidEntityAliasException::cycle($entityType, $currentId);
            }

            $visited[$currentId] = true;
            $nextId = EntityAlias::query()
                ->where('entity_type', $entityType)
                ->where('retired_id', $currentId)
                ->lockForUpdate()
                ->value('surviving_id');

            if ($nextId === null) {
                return $currentId;
            }

            $currentId = $nextId;
        }
    }
}
