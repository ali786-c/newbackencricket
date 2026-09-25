<?php

namespace App\Domains\Identity\Actions;

use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FindDuplicateCandidatesAction
{
    /** @return Collection<int, array<string, mixed>> */
    public function handle(string $entityType, string $name, string $city, User $actor, ?string $excludeId): Collection
    {
        $normalizedName = Str::lower(Str::squish($name));

        if ($entityType === 'player') {
            return Player::query()
                ->select(['id', 'player_code', 'name', 'city'])
                ->whereNull('archived_at')
                ->where('normalized_name', $normalizedName)
                ->where('city', $city)
                ->when($excludeId, fn ($query) => $query->whereKeyNot($excludeId))
                ->orderBy('id')->limit(10)->get()
                ->map(fn (Player $player): array => [
                    'entityType' => 'player',
                    'canonicalId' => $player->id,
                    'publicCode' => $player->player_code,
                    'name' => $player->name,
                    'city' => $player->city,
                    'matchedSignals' => ['normalized_name', 'city'],
                ]);
        }

        return Team::query()
            ->select(['id', 'team_code', 'owner_user_id', 'name', 'city'])
            ->whereNull('archived_at')
            ->where('normalized_name', $normalizedName)
            ->where('city', $city)
            ->where('owner_user_id', $actor->id)
            ->when($excludeId, fn ($query) => $query->whereKeyNot($excludeId))
            ->orderBy('id')->limit(10)->get()
            ->map(fn (Team $team): array => [
                'entityType' => 'team',
                'canonicalId' => $team->id,
                'publicCode' => $team->team_code,
                'name' => $team->name,
                'city' => $team->city,
                'matchedSignals' => ['normalized_name', 'city', 'owner'],
            ]);
    }
}
