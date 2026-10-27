# Driver OTP auth + onboarding (Laravel 11/12 + Sanctum)

Overlay files for a fresh Laravel app. API prefix `/api/v1`.

## Setup
1. `composer create-project laravel/laravel suzuki-api && cd suzuki-api`
2. `php artisan install:api` (installs Sanctum, publishes its migration)
3. Copy this folder over the project (overwrites `bootstrap/app.php`, `bootstrap/providers.php`, `routes/api.php`, `app/Http/Controllers/Controller.php`).
4. Delete `database/migrations/0001_01_01_000000_create_users_table.php`, `database/factories/UserFactory.php`, and the `User::factory()` call in `DatabaseSeeder`.
5. `.env`: `SESSION_DRIVER=array`, `CACHE_STORE=redis` (OTP locks need an atomic-lock store; `database` also works). Use MySQL/Postgres, not SQLite, in production.
6. `php artisan migrate && php artisan test`
7. If behind a proxy/CDN, configure `trustProxies` in `bootstrap/app.php` or per-IP OTP limits will see the proxy IP.

## Decisions on ambiguous/conflicting spec points
| Topic | Decision |
|---|---|
| Paths | Canonical: `/drivers/onboarding/step1..3`, `/drivers/onboarding/submit`, `/drivers/me/status`. Legacy `driver/onboarding/step-1`, `driver/verification/status` kept as aliases in `routes/api.php`. |
| Status values | Only `needs_profile / pending_review / approved / rejected / suspended` (+ `active` for passengers). Stored once in `users.status`; the driver status endpoint reads the same column. Legacy `draft/pending/under_review` are never returned. |
| Who moves to `pending_review` | The **submit** endpoint (it validates completeness). Saving steps alone does not change status. To auto-submit after step 3, call `submit()` at the end of `saveStep3`. |
| Steps editable when | Only `needs_profile`; otherwise `409 APPLICATION_LOCKED`. Re-applying after rejection is not specified; add a reset transition in `DriverReviewService` if wanted. |
| `gender` | Required (requirements list says required; table was blank). |
| `district_id` | Optional/nullable (design does not collect it). |
| Rear photo type | Canonical `vehicle_rear`; `vehicle_back` accepted and normalized. |
| Plate letters | Stored without spaces (`قنص`) so `ق ن ص` and `قنص` collide correctly. Digits normalized from Arabic-Indic. |
| 5th wrong OTP | Returns `OTP_INVALID` with `attempts_left: 0`; any further attempt returns `429 OTP_TOO_MANY_ATTEMPTS`. |
| Suspension | Blocked by `account.active` on protected routes; `/users/me`, `/auth/*` stay open so the app can show the blocked screen. `status_before_suspension` allows restoring. |
| Errors | `{"error":{"code","message",...}}`; validation: `VALIDATION_ERROR` with `details`. |
| Uploads | Minimal `POST /uploads` returns `file_key`; `OwnedUploadKey` ensures a driver can only reference their own files. Replace with your existing mechanism but keep the ownership check. |

## Security notes
- OTPs stored as HMAC-SHA256(phone|code); compared with `hash_equals`; attempts persisted under a row lock.
- Refresh tokens are opaque, hashed, rotated; reuse revokes the device's access and refresh tokens.
- Fixed dev OTP `123456` requires `!app()->isProduction()` **and** `OTP_DEV_CODE_ENABLED`.
- Documents/ID photos are sensitive: set `UPLOADS_DISK` to a private disk and serve via `temporaryUrl()`.

## Not included
Passenger profile registration, real SMS provider, admin UI (use `DriverReviewService` from Filament/Nova), districts table.