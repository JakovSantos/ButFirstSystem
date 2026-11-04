# Recorded presentation script (10–15 min, MP4/WebM, ≥ 720p)

Before recording: `composer db:seed`, `composer serve`, open http://localhost:8000, have VS Code with the
project and a terminal ready. Record the screen at 1280×720 or higher.

## 1. Project overview — 2–3 min
- Introduce the team and the client: But First Coffee Floridablanca.
- Show the original C# app briefly (or screenshots): Login, Inventory, Employee Schedule, Payroll tabs.
- Goal: same system, same look, now a PHP web app on our own framework, with a database and an external API.

## 2. Framework design — 3–4 min
- Folder tree: `public/`, `config/`, `src/Core`, `src/Middleware`, `src/Controllers`, `src/Models`, `src/Support`, `routes/`, `views/`.
- Open `public/index.php`: front controller → `App::boot()` → routes → `Router::run()`.
- Open `src/Core/Router.php`: `Controller@method` resolution and `Router::middleware()`.
- Open `routes/web.php`: routes + middleware order (logger → CSRF → auth → admin).
- Open `src/Core/View.php`: Mustache layout composition (`{{{content}}}`).
- Open `config/config.php`: env overrides, payroll constants, SQLite/MySQL switch.

## 3. Application features and API integration — 4–5 min
- Log in as **employee** → show disabled Edit / Remove / Add | Calculate; log out (window X).
- Log in as **admin**.
- Inventory: search "croissant", click a row (fields lock), Edit → change price → Update, Browse Image,
  Remove (confirm dialog), Print Summary.
- Employee Schedule: click a row, request a day off that is taken (error), request a free day, Randomize, Print.
- Payroll: click a row (labels update), Edit → change OT hours → Update, Clear → add a new employee
  with Add | Calculate, Print Employee Pay Summary, Print Payroll.
- External API: point at the status strip (Nager.Date holidays); change the payroll period to December and
  show the holidays; open `src/Support/HolidayApi.php` (cache, timeout, fallback).
- JSON API: open `/docs`; run 3–4 lines from `requests/api.http` (list, create 201, invalid 422, unknown 404).

## 4. Database design and challenges — 1–2 min
- Show the ERD (documentation Figure 1) and `database/seed.php`.
- Challenges: C# SQL scripts didn't match the code; disabled inputs aren't submitted; recreating
  DataGridView behaviour; keeping exact WinForms coordinates.
- Run `composer test` (19 passing tests).

## 5. Conclusion and future work — 1 min
- What we learned about frameworks vs. Laravel.
- Next: database-backed users, migrations, holiday pay rates, PDF/Excel export, pagination.
