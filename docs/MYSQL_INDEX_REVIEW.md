# MySQL Index Review

Phase 17 indexes are derived from the production query shapes, not individual
columns in isolation.

| Query path | Required index |
|---|---|
| Pending/accepted work by user and time | `sync_user_status_time_idx` |
| Match event history/recovery | `match_events_match_created_idx` plus the unique match/sequence key |
| Media retry/cleanup scan | `media_status_updated_idx` |
| Conflict/rejection monitoring window | `sync_health_events(category, created_at)` |
| Per-operation monitoring drill-down | `sync_health_events(operation, created_at)` |

Before production rollout, restore an anonymized production-sized backup into
a staging MySQL database and run `EXPLAIN ANALYZE` for:

```sql
SELECT * FROM sync_operations
 WHERE user_id = ? AND status = ? ORDER BY accepted_at DESC LIMIT 100;

SELECT * FROM match_events
 WHERE match_id = ? ORDER BY created_at DESC LIMIT 100;

SELECT * FROM media_uploads
 WHERE status IN ('uploading', 'failed') ORDER BY updated_at LIMIT 100;

SELECT category, COUNT(*) FROM sync_health_events
 WHERE created_at >= UTC_TIMESTAMP() - INTERVAL 15 MINUTE GROUP BY category;
```

Acceptance: the selected key must match the documented index, examined rows
must remain bounded by the requested entity/window, and no full table scan is
allowed for these hot paths. Store the `EXPLAIN ANALYZE` output with the release
evidence; do not commit production data.
