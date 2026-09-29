# STUMPS Release Readiness

Production promotion is fail-closed. A release is not ready until every gate has
an evidence reference and a UTC verification time.

## Rollout stages

| Stage | Enabled scope |
|---|---|
| `internal_alpha` | Single-device simple matches, local recovery, foreground sync |
| `closed_beta` | Background retry, conflict handling, spectator polling, production teams/profiles |
| `tournament_beta` | Tournament creation, fixtures, result confirmation, standings and corrections |
| `production` | All validated capabilities; still subject to the release gate |

Set the same `STUMPS_ROLLOUT_STAGE` value in Laravel and the Flutter
`--dart-define`. Flutter rejects unknown stage names. Laravel publishes its
active stage in `/api/v1/health/ready`, allowing deployment verification.

## Evidence workflow

1. Copy `docs/release-evidence.example.json` to the private path configured by
   `STUMPS_RELEASE_EVIDENCE_PATH`. Never put credentials or user/match payloads
   in this file.
2. For each gate, set `passed` only after its test or drill succeeds. Put the CI
   run, issue, signed test report, or operations ticket in `evidence`.
3. Record `verifiedAtUtc` exactly as `YYYY-MM-DDTHH:MM:SSZ`.
4. Run `php artisan stumps:release-readiness`. Use `--json` in CI.
5. A non-zero exit code blocks promotion.

Android/iOS restart recovery needs physical or hosted-device evidence. Backup
restore and deployment rollback need a staging/production-like hosting drill.
Unit or mocked tests must not be used to mark those gates as passed.
