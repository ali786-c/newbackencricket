<?php

namespace App\Domains\Teams\Actions;

use App\Domains\Identity\Exceptions\VersionConflictException;
use App\Models\Team;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateTeamAction
{
    /** @param array<string, mixed> $attributes */
    public function handle(Team $team, array $attributes): Team
    {
        return DB::transaction(function () use ($team, $attributes): Team {
            $locked = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            if ($locked->version !== $attributes['baseVersion']) {
                throw new VersionConflictException('team', $locked->id, $locked->version);
            }

            $map = ['name' => 'name', 'shortName' => 'short_name', 'city' => 'city', 'description' => 'description'];
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
