# Kasib

A simple accounting system for any business, by Fida Technologies. The people of a business
work with plain forms (money in, money out, exchange); the system keeps a full double-entry
ledger behind them. Every business has its own address and its own database, and works in
Dari, Pashto and English.

It is built on the base of `fidatech/fida-sarafi`: the same tenancy, administration panel,
ledger, design and build tools.

## What is built so far (step 1)

- **One database per business**, chosen from the address of the request. On cPanel hosting the
  database and the subdomain are made through the cPanel API.
- **System administrator panel** at `/admin` on the central address: open a business, its
  licence and invoices, backups and restore, support tickets, announcements, health.
- **Accounts**: one page for every account category (Cash, Bank, Saraf, Customers, Suppliers).
  A category added under Configuration appears in the menu by itself. Every account keeps a
  separate balance in every currency it uses.
- **Transactions**: Money In (with a payback date), Money Out, Transfers (with a fee),
  Exchanges (the profit of every deal is kept), Income, Expenses, Deals (what was paid and
  what came in; the profit or loss is worked out when the deal is closed) and journal entries
  by hand. Each one posts one balanced journal entry; a paper is cancelled, never deleted.
- **Finance**: chart of accounts, shareholders, capital and drawings, profit distribution,
  assets with automatic depreciation, liabilities, the daily cash count, period closing.
- **Reports**: roznamcha, account statement, trial balance, profit and loss, balance sheet,
  currency position, customer and supplier balances, due payments. Every list and report
  downloads as Excel (`?export=xlsx`) and prints or saves as PDF (`?print=1`).
- **Exchange rates**: one rate per currency. Saving new rates values the foreign money of the
  business again; the gain or loss goes to the books.
- **Export Center**: one Excel workbook of a period, with a summary and a sheet per module.
- **Administration**: users, roles and permissions, audit log, settings, Simple mode, and
  backup and restore by the owner of the business (the password is asked, and what the
  business has now is saved first).
- **Pages open without a full load**: a click fetches the page in the background and puts only
  its content, the row of buttons and the menu in place (`resources/assets/js/app.js`, last
  section). Forms that save something still load the page the ordinary way.
- Dari, Pashto and English with right-to-left layout, and Shamsi or Gregorian dates.

Also built: opening balances from an Excel or CSV file, the first setup for the kind of
business, notes with reminders and attached files for accounts and papers, recurring expenses,
WhatsApp messages through the sender of the business, a permission per account category, the
plan of a business (users and accounts allowed), a public page that checks a receipt, the
sign-in history, and a limit on dating papers back.

Security and backups: two-step sign-in with an authenticator app, a QR picture on every
receipt, a backup file that was downloaded can be uploaded and restored (only a file the system
made itself, checked by its fingerprint), old backups are thinned (7 days, 4 weeks, 12 months),
and each nightly backup can be sent to an FTP place outside the server (`BACKUP_FTP_*` in `.env`).
The QR code and the two-step codes are written in the project itself: no package is needed.
Every user sees the browsers he is signed in from and can sign the others out; an exchange
without an amount received uses the average rate of the last five between the two currencies.

## Version 1.5: control and insight

Built on top of version 1, each part with its tests (`tests/Feature/InsightTest.php`, `SelfServiceTest.php`).

| Part | Where | What it does |
| --- | --- | --- |
| Unusual activity | Reports, and Configuration → Alerts & Summary | Large, dated-back, after-hours and cancelled papers; told under the bell and, when asked, on WhatsApp |
| Evening summary | Configuration → Alerts & Summary | One WhatsApp message to the owner at the hour he chose: cash per currency, the day, exchange profit, tomorrow's paybacks |
| Balance confirmation | Reports → Balance Confirmations | Every customer and supplier is sent his balance once a month; the answer is kept |
| Expense budgets | Transactions → Expense Budgets | A monthly limit per category (in the month of the business calendar), a warning at 80% and at 100% |
| Cash forecast | Reports → Cash Forecast | Money of now less paybacks and recurring expenses of the days ahead, per currency |
| Period comparison | Reports → Profit & Loss | Against the period before or the same period a year before |
| Hawala tracking | Transactions → Hawala Tracking | Number, receiver, city and status until delivered; the money out is recorded when it is sent for an account |
| Bank reconciliation | Finance → Bank Reconciliation | Lines of a bank or saraf account ticked against the statement |
| Cash boxes and handover | Transactions → Cash Boxes & Handover | A box per cashier, counted and handed over at the end of the shift |
| Rate alerts and history | Configuration → Rate Alerts, Reports → Rate History | "Tell me when the dollar reaches 71"; every rate written, with its change |
| Depositor insights | Reports → Depositor Insights | Who holds the most, what moved, who went quiet |
| Scheduled reports | Reports → Scheduled Reports | The Export Center workbook by email, daily, weekly or monthly (needs the mail settings in `.env`) |
| Calendar | Reports → Calendar | A month on a page, Shamsi or Gregorian: paybacks, reminders, recurring expenses, papers that expire |
| Zakat report | Reports → Zakat Report | A help for the calculation only; the rules are for a scholar to confirm |
| Statement link | The statement of an account | A private read-only address for the customer |
| Voice notes | Files of an account or a paper | Recorded in the browser or uploaded |
| Document vault | Administration → Document Vault | The papers of the business, with a warning thirty days before one expires |
| Free trial | Admin panel → Settings | When on, a business that registers on the website opens at once for the days of the trial |
| Renewal through HesabPay | Licence and Invoices; Admin panel → Payment requests | The business sends the transaction number and a picture; on approval the payment is recorded and the licence moves on. A locked business can send it from its locked page |
| Help and tour | Help | Written steps, the videos listed in the admin settings, and a tour of the screen at first sign-in |

After pulling this version on a server that already runs version 1:

```bash
php artisan migrate            # the central database (payment requests)
php artisan tenants:migrate    # the database of every business
```

The cron line is the same; `books:evening` now runs every hour for the evening summaries and scheduled reports.

## Stack

Laravel 13, PHP 8.3 or newer, MySQL 8, Blade with a little JavaScript,
spatie/laravel-permission. No Node is needed: `php tools/build-assets.php` builds the CSS,
JavaScript and icons.

## Set up

```
composer install
cp .env.example .env
php artisan key:generate
# create the central database named in .env (DB_DATABASE), then:
php artisan migrate
php artisan app:create-admin
```

Sign in at `http://localhost:8000/admin` and add a business, or use the command:

```
php artisan tenants:create acme --name="Acme Trading" --owner-name="Owner" --owner-email=owner@example.com
```

The business is then at `acme.kasib.test` (see `TENANT_BASE_DOMAIN` in `.env`). On a
development machine, point that name at 127.0.0.1 in the hosts file.

After every update of the system:

```
php artisan migrate            # the central database
php artisan tenants:migrate    # the database of every business
```

The server's cron runs `php artisan schedule:run` every minute (depreciation, licences, backups, evening summaries, scheduled reports).

## Tests

```
php artisan test
```

Tests run on SQLite. GitHub Actions also runs the migrations and opens a business on MySQL 8.

## Rules of the repository

- Work happens on `development`; nothing goes to `main` without approval.
- No client data, passwords or keys in the repository.
