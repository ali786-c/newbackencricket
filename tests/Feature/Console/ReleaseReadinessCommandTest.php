<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReleaseReadinessCommandTest extends TestCase
{
    private string $evidencePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evidencePath = storage_path('framework/testing/release-evidence-'.uniqid().'.json');
        config()->set('stumps.release_evidence_path', $this->evidencePath);
        config()->set('stumps.rollout_stage', 'tournament_beta');
    }

    protected function tearDown(): void
    {
        File::delete($this->evidencePath);
        parent::tearDown();
    }

    public function test_readiness_fails_closed_when_evidence_is_missing(): void
    {
        $this->artisan('stumps:release-readiness')
            ->expectsOutputToContain('NOT READY')
            ->assertFailed();
    }

    public function test_readiness_passes_only_when_every_gate_has_traceable_evidence(): void
    {
        $keys = [
            'zero_known_scoring_data_loss_defects',
            'duplicate_sync_proven_harmless',
            'android_restart_recovery',
            'ios_restart_recovery',
            'offline_full_match',
            'conflict_recovery_preserves_events',
            'security_policy_suite',
            'backup_restore_drill',
            'deployment_rollback_drill',
        ];
        $gates = [];
        foreach ($keys as $key) {
            $gates[$key] = [
                'passed' => true,
                'evidence' => "ticket://release/{$key}",
                'verifiedAtUtc' => '2026-09-29T12:00:00Z',
            ];
        }
        File::ensureDirectoryExists(dirname($this->evidencePath));
        File::put($this->evidencePath, json_encode(['release' => '1.0.0', 'gates' => $gates], JSON_THROW_ON_ERROR));

        $this->artisan('stumps:release-readiness')
            ->expectsOutputToContain('READY:')
            ->assertSuccessful();
    }

    public function test_readiness_rejects_untraceable_or_non_utc_evidence(): void
    {
        File::ensureDirectoryExists(dirname($this->evidencePath));
        File::put($this->evidencePath, json_encode([
            'gates' => [
                'zero_known_scoring_data_loss_defects' => [
                    'passed' => true,
                    'evidence' => '',
                    'verifiedAtUtc' => '2026-09-29 12:00:00',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $this->artisan('stumps:release-readiness')->assertFailed();
    }
}
