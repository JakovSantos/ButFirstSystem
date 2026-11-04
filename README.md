# BUT FIRST, SYSTEM — PHP version

The C# Windows Forms **But First, System** (Inventory, Employee Schedule and Payroll for But First Coffee
Floridablanca) rebuilt as a PHP web application on a DIY framework made from the three IPT10 module packages:

| Package | Role |
|---|---|
| `bramus/router` | Routing + before-route middleware |
| `mustache/mustache` | Logic-less templates with a shared layout |
| `illuminate/database` | Eloquent ORM used standalone (Capsule) |

The screens keep the exact layout of `Form1.Designer.cs` and `Form2.Designer.cs` (same coordinates, sizes,
fonts, colors, button styles, DataGridView look and MessageBox texts).

## Requirements

- PHP 8.1+ with `pdo_sqlite` (or `pdo_mysql`), `curl`, `fileinfo`, `mbstring` — XAMPP's PHP works.
- Composer.

## Quick start

```bash
composer install
composer db:seed      # creates database/database.sqlite with sample data
composer serve        # http://localhost:8000
```

Log in with **admin / admin123** (full access) or **employee / employee123** (Edit/Remove product and
Add | Calculate payroll are disabled, like the C# login).

| URL | What |
|---|---|
| `/login` | Login form (C# Form1) |
| `/inventory`, `/schedule`, `/payroll` | Main window tabs (C# Form2) |
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
src/Core/               App, Router, View, Response, Request, Database, Session, Logger
src/Middleware/         AuthMiddleware, AdminMiddleware, CsrfMiddleware, RequestLogger
src/Controllers/        Auth, Inventory, Schedule, Payroll, Docs (HTML)
src/Controllers/Api/    Product, Payroll, Shift, Holiday (JSON)
src/Models/             Product, Payroll, EmployeeShift (Eloquent)
src/Support/            ProductInput, PayrollInput, PayrollCalculator, ShiftScheduler,
                        HolidayApi, BarChart, Format, ImageUpload, MainWindow
routes/web.php, api.php route + middleware definitions
views/                  Mustache templates (layout, login, main, print/, errors/, partials/)
public/assets/          winforms.css (WinForms look), app.js (C# event handlers), images
database/               seed.php, schema.sql (MySQL), database.sqlite
tests/                  PHPUnit tests
requests/api.http       ready-to-send API requests (VS Code REST Client)
docs/                   documentation (HTML + PDF), presentation script, screenshots
```

## External API

Philippine public holidays from **Nager.Date** (`https://date.nager.at/api/v3/PublicHolidays/{year}/PH`,
no key). Proxied and cached by `GET /api/holidays?start=&end=` / `?year=`; shown in the status strip of the
main window and on the payroll printouts. Failures are logged and fall back to a stale cache or a 502 JSON error.

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
composer test         # PHPUnit: 19 tests
```

## Differences from the C# version (on purpose)

- Browser equivalents replace desktop-only features: `OpenFileDialog` → file upload, `PrintDialog` → print
  preview page + browser print dialog, `Application.Exit` → close/logout.
- A status strip was added at the bottom of the main window to show the external holiday API result.
- The payroll grid column that stores net pay is labelled **Net Pay** (the C# grid labelled it "Basic Pay").
- Payroll fields are editable for a new entry after **Clear** (in C# they stayed read-only until a row was selected).
- The shift randomizer keeps each employee's payroll link while shuffling (the C# version deleted it).
- Date pickers display in the browser's locale format.
