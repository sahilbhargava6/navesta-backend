# Navesta API (Laravel)

The Navesta backend is a Laravel 12 app. It provides:

- the REST API used by the Next.js frontend, at `/api/v1`;
- the admin panel, at `/admin`;
- background jobs for reminders and statement virus scans;
- scheduled tasks for snapshots, reminders and backups.

It replaced the original Node/Fastify API (now removed) and kept the same URLs, response format and PostgreSQL database.

## Requirements

- **PHP 8.2+** with the `pdo_pgsql`, `intl`, `bcmath`, `zip` and `mbstring` extensions. The Docker image uses PHP 8.3.
- **Composer**
- **PostgreSQL 17**, plus `pg_dump` and `psql` for backups.
- **Production only:** Redis, and ClamAV (`clamd`) for scanning uploaded statements.

## Running locally

```bash
composer install
cp .env.example .env              # set DB_PASSWORD (and DB_DUMP_BINARY_PATH on Windows)
php artisan key:generate
php artisan migrate               # safe on the existing database (see below)
php artisan db:seed               # lenders + demo user rahul.mehta@example.com / password123
php artisan storage:link          # serves blog images
php artisan navesta:make-admin you@company.com
php artisan serve --port=3001     # the frontend expects http://localhost:3001/api/v1
```

Locally, queued jobs run immediately (`QUEUE_CONNECTION=sync`), reminders are written to the log, and statement scanning is off. To run the scheduler, use `php artisan schedule:work`.

| URL | What |
|---|---|
| `/api/v1/...` | REST API |
| `/admin` | Admin panel. Admins only, and every admin must set up an authenticator app at first sign-in. |
| `/docs/api` | Interactive API docs. Open to anyone locally, admins only in other environments. A copy is in `docs/openapi.json`. |
| `/health` | Database and cache check. Returns 503 when either is down. |

## Running with Docker

The `docker-compose.yml` in the folder above runs every service: Postgres, Redis, ClamAV, the API (Laravel Octane on FrankenPHP), the queue workers, the scheduler and the frontend. See the comments at the top of that file. Copy `../.env.example` to `../.env` and fill in the secrets first.

Deploys, scaling, monitoring, backups and data protection are covered in [`../docs/OPERATIONS.md`](../docs/OPERATIONS.md).

## Quality checks

```bash
php artisan test                                           # in-memory SQLite
DB_CONNECTION=pgsql DB_DATABASE=navesta_test php artisan test   # real Postgres
vendor/bin/phpstan analyse                                 # Larastan, level 5
vendor/bin/pint --test                                     # code style
composer audit                                             # known vulnerabilities
```

There are 118 tests. They refuse to run against any database whose name doesn't end in `_test`, so the real data can't be wiped by accident. CI (`../.github/workflows/backend.yml`) runs all of the checks above against Postgres 17 on every push.

## Project layout

```
app/
  Console/Commands/     navesta:capture-snapshots, :send-due-reminders, :make-admin, :verify-backup
  Contracts/            MessageGateway (WhatsApp/SMS), VirusScanner, SendsMessages
  Enums/                every status/type value (also enforced by database CHECK constraints)
  Filament/             admin panel: users, lenders, balance transfers, blog posts, stats
  Http/Controllers/     one per API area
  Http/Requests/        request validation
  Http/Resources/       JSON shapes
  Jobs/                 ScanStatement (virus scan of uploads)
  Models/               Eloquent models
  Notifications/        PaymentReminder + the WhatsApp/SMS channel
  Services/             business logic (balance transfer, dashboard, debt solutions, lender
                        matching, credit scores, statement vault, messaging and scanning drivers)
  Support/              Money (paise/bps), Financial (EMI), PayoffSimulator
config/navesta.php      every business setting (timezone, payoff rules, reminders, storage, scanner)
routes/api.php          API endpoints
routes/console.php      schedule
```

## How the main features work

### Credit scores

- **Bureau scores win.** A score from CIBIL or CRIF on file always takes priority.
- **Self-reported scores are recorded but flagged.** A score sent from the browser is only used when no bureau score exists. It's saved as `user_input` and shown with that source.
- **No score, no pre-approval.** Eligibility for a user with no score returns `tier: "unscored"`, and nobody is pre-approved without a score.
- **Lender minimums apply.** Each lender has a minimum credit score, and applications below it are refused.

### Payoff plans

`/solutions/avalanche` and `/solutions/snowball` simulate repayment month by month:

- **Interest:** each debt accrues interest monthly.
- **Minimum payments:** every debt gets its minimum. A card with no minimum on file is assumed to need 5% of its balance, with a ₹200 floor.
- **Rollover:** money freed up when a debt is cleared moves to the next debt in the plan's order.
- **Extra payments:** an optional `?extraMonthlyRupees=` amount goes to the current target debt each month.

Each plan returns:

- months until debt-free, and the month it happens;
- total interest;
- interest saved compared with paying only the minimums;
- when each debt is paid off.

### Lender matching

Each lender gets a score out of 100 for the customer:

| Factor | Points |
|---|---|
| Credit-score headroom above the lender's minimum | 50 |
| Lender can take the whole balance | 20 |
| Lender's rate compared with the others | 20 |
| Employment type | 10 |

A lender is marked ineligible if the customer's score is below its minimum or the balance is above its limit.

### Loan analysis

This compares the loan with the cheapest active lender that would accept it. The estimated saving is net of that lender's processing fee.

### Debt history

A scheduled job records each customer's totals daily for the current month, using month boundaries in India time. Opening the dashboard never writes data.

### Reminders

- **Delivery:** reminders are queued and sent by WhatsApp or SMS through `MessageGateway`. The driver is `log` in development and `twilio` in production. Indian SMS also requires DLT registration.
- **Recipient:** a reminder only ever goes to the account's registered phone. A phone number in the request is ignored.
- **Rate limit:** 5 reminders per hour per customer.
- **Daily job:** each morning, a job reminds customers about card and loan payments due in 3 days.
- **Opt-out:** customers can turn reminders off with `PATCH /users/me {"remindersEnabled": false}`.

### Statements

- **Uploads:** `POST /statements` accepts PDF or CSV files up to 10 MB.
- **Encryption:** files are encrypted with the app key before they're written. The S3 disk adds provider-side encryption on top.
- **Virus scan:** each upload is queued for a ClamAV scan. Infected files are deleted at once, and a file can only be downloaded after it passes. If the scanner is unreachable after retries, the file is locked.
- **Admin access:** admins see file names and scan status only. They can't open statements.
- **Storage:** the S3-compatible disk works with AWS S3, OCI Object Storage and Google Cloud Storage. Choose an India region.

### Blog

- **Writing:** admins write posts in Markdown in `/admin`, with a cover image, a publish date for scheduling, and an SEO description.
- **Public API:** `/api/v1/posts` lists published posts and `/api/v1/posts/{slug}` returns one.
- **Safety:** HTML in a post is escaped and `javascript:` links are removed.

### Admin panel

Admins can do the following in `/admin`:

- **Users:** view customers with their cards, loans, applications, scores and statements; record a bureau score; change a user's role or verification status.
- **Lenders:** edit rates, limits, fees and minimum scores, or deactivate a lender.
- **Balance transfers:** review applications. The only allowed moves are submitted → approved or rejected, and approved → disbursed or rejected.
- **Blog:** write and publish posts.

Other safeguards:

- Admins can't delete customers.
- Admins can't remove their own admin role.
- `role` can't be set through the API.

### Operations

- **Logging:** `LOG_STACK=json` writes structured logs to stderr.
- **Error tracking:** Sentry reports errors once `SENTRY_LARAVEL_DSN` is set.
- **Backups:**
  - The database is backed up nightly, gzipped and AES-256 encrypted with `BACKUP_ARCHIVE_PASSWORD`.
  - Old backups are cleaned up nightly.
  - You're alerted if the latest backup is more than a day old.
  - `navesta:verify-backup` restores the latest backup into a scratch database every week and compares row counts with the live data.
- **Rate limits:** 100 requests per minute per IP, 5 reminders per hour per customer and 20 uploads per hour per customer. These use Redis in Docker.

## Security and privacy

- **Website sign-in:** the website (`SANCTUM_STATEFUL_DOMAINS`) signs in with an **HTTP-only session cookie**, protected by CSRF. No token ever reaches the browser's JavaScript. Other clients, such as a future mobile app, receive bearer tokens.
- **Login protection:**
  - 10 sign-in attempts per minute per IP address.
  - 10 wrong passwords lock that email for 15 minutes.
  - Every success, failure and lockout is written to the audit log.
- **Passwords:** at least 8 characters, with both letters and numbers. In production they are also checked against known breached passwords.
- **SMS codes:**
  - Customers can verify their phone with `POST /users/me/phone/send-code` and then `/verify`.
  - Once verified, they can turn on SMS sign-in codes with `PATCH /users/me {"twoFactorEnabled": true}`.
  - Codes are 6 digits, stored only as a keyed hash, expire after 10 minutes, and allow 5 guesses.
- **Encryption:** phone numbers are encrypted in the database with `APP_KEY`.
- **Audit log:** sign-ins, data changes (field names only, never personal values), admin decisions, downloads, exports and deletions are recorded in an append-only log. Admins can view it in the admin panel under **Security**.
- **Consent (DPDP Act):**
  - Customers must actively agree to the terms at sign-up; reminders are opt-in.
  - Every grant or withdrawal is stored with the notice version shown.
  - `GET /users/me/export` downloads all of a customer's data, and `DELETE /users/me` erases the account.
  - Audit entries and statements are deleted automatically after their retention period.

## Scalability

- **Server:** Octane on FrankenPHP keeps the app in memory between requests. The app is stateless: sessions, cache, queues, rate limits and scheduler locks live in Redis, and files in object storage.
- **Database:**
  - Indexes on every per-customer query.
  - Optional read replicas (`DB_READ_HOST`).
  - Ready for PgBouncer.
- **Caching:** the lender catalogue is cached, and so are each customer's dashboard, payoff plans and lender matches. Each cache is cleared automatically when the data behind it changes.
- **Background work:** nightly jobs are split into batches of 500 customers. Queues run in priority order: `notifications`, `scans`, `default`.
- **Safe retries:** POST requests accept an `Idempotency-Key` header, so a retried request returns the stored response instead of running again.
- **Rate limits:** applied per customer rather than per IP (`API_RATE_LIMIT`, default 100 requests a minute).

## Conventions

- **Units:** money is stored as integer **paise** and rates as integer **basis points**. The API takes and returns rupees and percentages. Convert with `App\Support\Money`.
- **Responses:** success looks like `{ success: true, data, message?, meta? }`. Lists are paginated with `?page=` and `?perPage=` (maximum 100), and `meta` holds the paging details.
- **Errors:** errors look like `{ success: false, error }`. Validation errors return status 400 with the first error message.
- **Amounts:** amounts accept at most 2 decimal places. Status and type values are PHP enums and are also enforced with Postgres `CHECK` constraints.
- **Auth:** the API uses Sanctum bearer tokens only, which expire after `SANCTUM_TOKEN_EXPIRATION` minutes (default 1440). The admin panel uses its own session plus two-factor authentication.

## Database notes

- **Existing database:** the original tables were created by Prisma. The early migrations skip tables that already exist, so `php artisan migrate` adopts the current database. On an empty database, the migrations build the full schema.
- **Don't run `prisma db push`** against this database. Prisma would offer to drop Laravel's tables.
- **Old passwords:** the Node backend's `$2b$` bcrypt hashes are accepted because `HASH_VERIFY=false` is set.

## Known gaps

- **Reminders aren't live yet.** Sending them for real needs a Twilio account with an approved WhatsApp sender, plus DLT registration for SMS in India.
- **Bureau scores are manual for now.** They're entered by admins until a CIBIL/CRIF API integration exists.
- **Statements aren't parsed.** Uploaded files are stored and scanned, but not read into transactions.
