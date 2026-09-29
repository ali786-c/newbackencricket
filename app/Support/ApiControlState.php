<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

final class ApiControlState
{
    /** @return array{registration_enabled: bool, api_read_only: bool, rollout_stage: string} */
    public static function current(): array
    {
        try {
            $values = DB::table('system_settings')->pluck('value', 'key');
        } catch (Throwable) {
            $values = collect();
        }

        return [
            'registration_enabled' => ($values['registration_enabled'] ?? '1') === '1',
            'api_read_only' => ($values['api_read_only'] ?? '0') === '1',
            'rollout_stage' => (string) ($values['rollout_stage'] ?? config('stumps.rollout_stage')),
        ];
    }
}
