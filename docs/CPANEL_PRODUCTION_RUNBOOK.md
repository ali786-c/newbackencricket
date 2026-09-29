# STUMPS cPanel Production Runbook

## Required topology

- Keep the project outside `public_html`, for example `/home/account/stumps-backend`.
- Point the API subdomain document root exactly to `/home/account/stumps-backend/public`.
- Copy `.env.production.example` to `.env`, replace every placeholder, keep
  `APP_ENV=production`, `APP_DEBUG=false`, `APP_TIMEZONE=UTC`, and enable SSL.
- Keep `.env`, `storage/`, database dumps and logs outside public web access.

## Atomic deployment

1. Run `php artisan down --retry=60`.
2. Create and verify the database backup below.
3. Record the current commit and migration batch.
4. Run `composer install --no-dev --optimize-autoloader`.
5. Run `php artisan migrate --force`.
6. Run `php artisan storage:link` and `php artisan optimize`.
7. Probe `/api/v1/health/live` and `/api/v1/health/ready` over HTTPS.
8. Run `php artisan up`.

Never run `migrate:fresh`, destructive queue clearing, or an unreviewed
migration rollback in production.

## Scheduler and bounded queue worker

Create these cPanel cron entries every minute:

```cron
* * * * * cd /home/account/stumps-backend && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/account/stumps-backend && /usr/local/bin/php artisan queue:work database --queue=default --stop-when-empty --max-time=50 --tries=3 --backoff=5 --memory=128 >> storage/logs/queue-cron.log 2>&1
```

Use a cron lock when the host does not prevent overlaps.

## Backup, restore and rollback proof

Before every schema deployment create a timestamped dump outside the web root:

```bash
mysqldump --single-transaction --routines --triggers --set-gtid-purged=OFF -h DB_HOST -u DB_USER -p DB_NAME | gzip > /home/account/backups/stumps-YYYYMMDD-HHMMSS.sql.gz
gzip -t /home/account/backups/stumps-YYYYMMDD-HHMMSS.sql.gz
```

Monthly, restore the newest dump into a disposable database and run
`php artisan migrate:status` plus the readiness probe. A backup is not valid
until this restore drill succeeds.

Rollback order: enter maintenance mode, preserve logs, restore the prior app
release, and only run `migrate:rollback --step=N --force` when the migration is
reviewed as reversible and no new data depends on it. Otherwise restore the
verified pre-deployment dump. Rebuild caches and run both health probes before
leaving maintenance mode.

## Monitoring and privacy

`stumps:sync-health` runs every five minutes and logs aggregate accepted,
conflict and rejection counts only. It never records delivery payloads or
tokens. Alert on `sync_health_window` warnings and use request IDs, hashed
device IDs, operation names and HTTP status for diagnosis. Verify jobs with
`php artisan schedule:list` after deployment.
