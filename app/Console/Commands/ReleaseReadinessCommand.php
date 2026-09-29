<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JsonException;

class ReleaseReadinessCommand extends Command
{
    protected $signature = 'stumps:release-readiness {--json : Emit machine-readable output}';

    protected $description = 'Verify that every production release gate has traceable evidence.';

    /** @var array<string, string> */
    private const GATES = [
        'zero_known_scoring_data_loss_defects' => 'Zero known scoring data-loss defects',
        'duplicate_sync_proven_harmless' => 'Duplicate sync proven harmless',
        'android_restart_recovery' => 'Android restart recovery verified',
        'ios_restart_recovery' => 'iOS restart recovery verified',
        'offline_full_match' => 'Offline full-match test passed',
        'conflict_recovery_preserves_events' => 'Conflict recovery preserves local events',
        'security_policy_suite' => 'Security and policy suite passed',
        'backup_restore_drill' => 'Database backup and restore drill passed',
        'deployment_rollback_drill' => 'Deployment rollback drill passed',
    ];

    public function handle(): int
    {
        $path = (string) config('stumps.release_evidence_path');
        $document = $this->readDocument($path);
        $results = [];

        foreach (self::GATES as $key => $label) {
            $gate = $document['gates'][$key] ?? null;
            $results[$key] = [
                'label' => $label,
                'passed' => is_array($gate) && ($gate['passed'] ?? false) === true
                    && is_string($gate['evidence'] ?? null) && trim($gate['evidence']) !== ''
                    && is_string($gate['verifiedAtUtc'] ?? null) && $this->isValidUtcTimestamp($gate['verifiedAtUtc']),
                'evidence' => is_array($gate) ? ($gate['evidence'] ?? null) : null,
                'verifiedAtUtc' => is_array($gate) ? ($gate['verifiedAtUtc'] ?? null) : null,
            ];
        }

        $ready = ! in_array(false, array_column($results, 'passed'), true);
        $payload = [
            'ready' => $ready,
            'rolloutStage' => config('stumps.rollout_stage'),
            'release' => $document['release'] ?? null,
            'evidencePath' => $path,
            'gates' => $results,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->info($ready ? 'READY: all release gates have valid evidence.' : 'NOT READY: release gates are incomplete.');
            foreach ($results as $result) {
                $this->line(sprintf('[%s] %s', $result['passed'] ? 'PASS' : 'FAIL', $result['label']));
            }
        }

        return $ready ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<string, mixed> */
    private function readDocument(string $path): array
    {
        if (! File::isFile($path)) {
            return [];
        }

        try {
            $decoded = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (JsonException) {
            return [];
        }
    }

    private function isValidUtcTimestamp(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new \DateTimeZone('UTC'));

        return $date !== false && $date->format('Y-m-d\TH:i:s\Z') === $value;
    }
}
