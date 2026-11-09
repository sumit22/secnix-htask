# apps/api (Laravel 13, PHP 8.5)

Follow the root `CLAUDE.md`. API-specific:
- Run tools in the container: `docker compose exec api composer lint|fix|analyse|test`, `php artisan ...`.
- Before using a Laravel API you are unsure about, check the installed version in `vendor/laravel/framework` rather than memory.
- Layering: routes → thin controller + FormRequest → action/service class → Eloquent/DB. Quota-critical writes inside `DB::transaction` with row locks or conditional updates.
- Every migration must be reversible. Tests use PostgreSQL (concurrency semantics differ from SQLite).
