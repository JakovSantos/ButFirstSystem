# BUT FIRST, SYSTEM

Inventory, employee scheduling and payroll for **But First, Coffee — Floridablanca**, built as a PHP web
application on **But First Framework**, a small MVC framework we assembled from three IPT10 module packages:

| Package | Role in But First Framework |
|---|---|
| `bramus/router` | Routing + before-route middleware |
| `mustache/mustache` | Logic-less templates with a shared layout |
| `illuminate/database` | Eloquent ORM used standalone (Capsule) |

The interface is a responsive web UI in the But First Coffee brand style (black, warm paper, caramel accent,
Figtree): sidebar navigation, stat tiles, card forms, selectable tables, a day-off board, a pay-summary panel,
dialogs and toasts. It works on desktop, tablet and phone.

## Requirements

- PHP 8.1+ with `pdo_sqlite` (or `pdo_mysql`), `curl`, `fileinfo`, `mbstring` — XAMPP's PHP works.
- Composer.

## Quick start

```bash
composer install
composer db:seed      # creates database/database.sqlite with sample data (wipes existing data)
composer serve        # http://localhost:8000
```

Already have data? `composer db:migrate` upgrades the database in place (adds the per-employee
daily rate, the `users` table with login accounts and `users.must_change_password`) without deleting anything.

### Accounts

| Who | Username | Password | Access |
|---|---|---|---|
| Admin | `admin` | starts as `admin123`, then their own | Everything: products, payroll, daily rates, and adding, renaming and removing employees |
| Employee | their Employee ID, e.g. `F-007` | starts as `employee123`, then their own | Views inventory and the schedule, requests **their own** day off, sees **only their own payroll** |

Accounts are stored in the `users` table. Employee IDs are not case-sensitive at sign-in (`f-007` works).
Every account, admin included, that is still on its starting password must choose its own password
before using the system (the starting passwords can never be chosen again). Anyone can change their password later under **My account**. Defaults can be changed with
`ADMIN_PASSWORD` / `EMPLOYEE_DEFAULT_PASSWORD` before running `db:seed` or `db:migrate`.

### Managing employees (admin)

The **Employees** page lists every employee with their login status:

- **Add employee**: name, Employee ID (the next free ID is filled in, e.g. `F-007`) and daily rate.
  This creates the payroll record, a schedule row and the login in one step.
- **Rename**: changes the name everywhere (roster, day-off board, payroll and printouts).
- **Remove**: deletes the employee's payroll record, schedule row and login.
- **Reset password**: for an employee who forgot theirs. It goes back to the starting password, and they
  are asked to set a new one at their next sign-in.

### Employee names and pay

- **Rename an employee** (admin): Employees → *Rename*, or Employee schedule → select the employee →
  edit the name → *Save name*, or Payroll → *Edit* → change the name → *Update*.
- **Daily rate** (admin): each employee has their own daily rate (default ₱550). Payroll → *Edit* →
  *Daily rate* → *Update*. Basic pay, holiday pay and overtime are all calculated from it.

| URL | What |
|---|---|
| `/login` | Sign-in page |
| `/inventory`, `/schedule`, `/payroll` | The three modules |
| `/employees` | Add, rename, remove employees and reset passwords (admin) |
| `/account` | Change your own password |
| `/api/...` | JSON API |
| `/docs` | API reference page |

### Using MySQL (XAMPP)

Start MySQL in XAMPP, create the database (or import `database/schema.sql` in phpMyAdmin), then:

```bash
set DB_DRIVER=mysql
set DB_DATABASE=butfirstsystemdb
composer db:seed
composer serve
```

(PowerShell: `$env:DB_DRIVER="mysql"`.) Other settings: `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`,
`API_KEY`, `APP_URL` — see `config/config.php`.

## Project layout

```
public/index.php        front controller (boot, routes, 404/500, run)
config/config.php       configuration (env overrides)
src/Core/               But First Framework core: App, Router, View, Response, Request,
                        Database, Session, Logger, RateLimiter
src/Middleware/         SecurityHeaders, AuthMiddleware, AdminMiddleware, CsrfMiddleware,
                        RequestLogger
src/Controllers/        Auth, Inventory, Schedule, Payroll, Employee, Account, Docs (HTML)
src/Controllers/Api/    Product, Payroll, Shift, Holiday (JSON)
src/Models/             Product, Payroll, EmployeeShift, User (Eloquent)
src/Support/            ProductInput, PayrollInput, PayrollCalculator, ShiftScheduler,
                        HolidayApi, BarChart, Format, ImageUpload, MainWindow, Migrator,
                        EmployeeManager
routes/web.php, api.php route + middleware definitions
views/                  Mustache templates (layout, login, main, print/, errors/, partials/)
public/assets/          app.css, app.js, images and icons
database/               seed.php, migrate.php, schema.sql (MySQL), database.sqlite
tests/                  PHPUnit tests
requests/api.http       ready-to-send API requests (VS Code REST Client)
```

## Security

The framework and the app are written defensively. In short:

| Threat | Protection |
|---|---|
| SQL injection | Every query goes through Eloquent with bound parameters; no user input is pasted into SQL |
| Cross-site scripting (XSS) | Mustache escapes all output; a Content-Security-Policy only allows scripts from this site (no inline scripts) |
| Cross-site request forgery (CSRF) | Every form carries a secret session token; API writes must be same-site JSON |
| Password guessing | 5 failed sign-ins lock that username for 15 minutes; wrong API keys are rate-limited; failed logins take as long as real ones |
| Stolen or fixed sessions | HttpOnly + SameSite cookies, strict session mode, new session id on sign-in and password change, sign-out after 60 minutes idle |
| Weak or shared passwords | bcrypt hashes (`password_hash`), starting passwords must be replaced, minimum length |
| Broken access control | Admin-only routes are blocked by AdminMiddleware; employees only see their own payroll and only change their own day off |
| Clickjacking, MIME sniffing | `X-Frame-Options: DENY`, `frame-ancestors none`, `X-Content-Type-Options: nosniff` |
| Open redirects / header injection | Redirects only go to paths on this site; new lines are stripped |
| Malicious uploads | Extension and real image type must match (JPG/PNG/BMP), random file names, 5 MB limit, nothing in `uploads/` can run |
| Exposed files | `.htaccess` files make Apache serve only `public/`; the built-in server refuses `../` paths |
| Information leaks | Error details are logged, not shown (`APP_DEBUG` is off by default); log lines cannot be forged |
| Bad input | Every field is checked for type, length and range on the server, not only in the browser |

Before putting the system where other people can reach it: set `API_KEY` to a long random value,
keep `APP_DEBUG` off, and serve it over HTTPS (cookies then get the `Secure` flag automatically).

## External API

Philippine public holidays from **Nager.Date** (`https://date.nager.at/api/v3/PublicHolidays/{year}/PH`,
no key). Proxied and cached by `GET /api/holidays?start=&end=` / `?year=`; shown in the holiday banner on
the Payroll page and on the payroll printouts. Failures are logged and fall back to a stale cache or a 502 JSON error.

## JSON API

Authenticate with the session cookie or `X-API-Key: butfirst-dev-key`.

```bash
curl -H "X-API-Key: butfirst-dev-key" http://localhost:8000/api/products
curl -X POST http://localhost:8000/api/products -H "X-API-Key: butfirst-dev-key" -H "Content-Type: application/json" -d "{\"product_name\":\"Caramel Syrup\",\"category\":\"SYRUPS\",\"price\":420,\"quantity\":10}"
curl -X PUT http://localhost:8000/api/shifts/2/dayoff -H "X-API-Key: butfirst-dev-key" -H "Content-Type: application/json" -d "{\"day_off\":\"Sunday\"}"
curl "http://localhost:8000/api/holidays?start=2026-12-01&end=2026-12-31" -H "X-API-Key: butfirst-dev-key"
```

Status codes: 200, 201, 400 (invalid JSON), 401, 403 (employee), 404, 409 (day off taken), 422 (validation), 502 (external API down).

## Tests

```bash
composer test         # PHPUnit: 40 tests
```

## Design notes

- Browser features stand in for desktop ones: file upload for images, a print-preview page plus the
  browser print dialog for reports.
- The payroll table shows the key columns; deduction details appear in the form when a row is selected,
  and the pay summary updates live while typing.
- Success messages appear as toasts; warnings, errors and confirmations stay as dialogs.
- The shift randomizer keeps each employee's payroll link while shuffling.
