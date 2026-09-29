# STUMPS API deployment

Deploy only this `backend/` directory and point the web-server document root to `backend/public`.
Provision secrets through the host; never upload `.env`, SQLite files, test caches, or local runtime files.

Release command order: `composer install --no-dev --classmap-authoritative`, database backup, `php artisan migrate --force`, `php artisan optimize`, `php artisan queue:restart`, then request `/api/v1/health/ready`.

Application rollback restores the previous release. A destructive migration requires a tested forward-recovery plan and a verified database backup; reverting application files is not data recovery. `/api/v1` remains backward compatible for supported mobile releases.

Sanctum mobile sessions use long-lived opaque personal access tokens. The app verifies with `GET /api/v1/auth/me`; a 401 removes the secure token and pauses remote sync without deleting recoverable local match data. Login issues a new device-named token. Logout revokes the current token; logout-all revokes every token.
