<?php

namespace App\Domains\Players\Actions;

use App\Domains\Identity\Exceptions\VersionConflictException;
use App\Models\Player;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdatePlayerAction
{
    /** @param array<string, mixed> $attributes */
    public function handle(Player $player, array $attributes): Player
    {
        return DB::transaction(function () use ($player, $attributes): Player {
            $locked = Player::query()->whereKey($player->id)->lockForUpdate()->firstOrFail();
            if ($locked->version !== $attributes['baseVersion']) {
                throw new VersionConflictException('player', $locked->id, $locked->version);
            }

            $map = [
                'name' => 'name', 'city' => 'city', 'playingRole' => 'playing_role',
                'battingStyle' => 'batting_style', 'bowlingStyle' => 'bowling_style', 'bio' => 'bio',
            ];
            foreach ($map as $input => $column) {
                if (Arr::has($attributes, $input)) {
                    $locked->{$column} = $attributes[$input];
                }
            }
            if (Arr::has($attributes, 'name')) {
                $locked->normalized_name = Str::lower(Str::squish($attributes['name']));
            }
            $locked->version++;
            $locked->save();

            return $locked;
        });
    }
}
