# HesabBook

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

Not built: two-step sign-in, a QR picture on receipts (the address to check a receipt is
printed as text), restoring from an uploaded file (a copy is restored from the list only), and
a copy of the backups outside the server. See the specification.

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

The business is then at `acme.hesabbook.test` (see `TENANT_BASE_DOMAIN` in `.env`). On a
development machine, point that name at 127.0.0.1 in the hosts file.

After every update of the system:

```
php artisan migrate            # the central database
php artisan tenants:migrate    # the database of every business
```

The server's cron runs `php artisan schedule:run` every minute (depreciation, licences, backups).

## Tests

```
php artisan test
```

Tests run on SQLite. GitHub Actions also runs the migrations and opens a business on MySQL 8.

## Rules of the repository

- Work happens on `development`; nothing goes to `main` without approval.
- No client data, passwords or keys in the repository.
