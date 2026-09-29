<?php

return [
    'rollout_stage' => env('STUMPS_ROLLOUT_STAGE', 'internal_alpha'),
    'release_evidence_path' => env(
        'STUMPS_RELEASE_EVIDENCE_PATH',
        storage_path('app/private/release-evidence.json')
    ),
    'api_rate_limit' => (int) env('STUMPS_API_RATE_LIMIT', 180),
    'token_expiration_minutes' => (int) env('STUMPS_TOKEN_EXPIRATION_MINUTES', 43200),
    'sync_monitor_window_minutes' => (int) env('STUMPS_SYNC_MONITOR_WINDOW_MINUTES', 15),
    'sync_conflict_warning_threshold' => (int) env('STUMPS_SYNC_CONFLICT_WARNING_THRESHOLD', 10),
    'sync_rejection_warning_threshold' => (int) env('STUMPS_SYNC_REJECTION_WARNING_THRESHOLD', 10),
];
