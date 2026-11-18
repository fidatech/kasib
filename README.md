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

## Version 2 (the Pro plan): sales and purchases

A business on the Starter plan keeps versions 1 and 1.5. The pages below open only for a business whose
plan is `pro` (set on the business in the admin panel, or chosen when it registers); others see what Pro adds.

| Part | Where | What it does |
| --- | --- | --- |
| Items | Sales & Purchases → Items | Name, code, unit, prices, and the stock of each with its average cost. A service is an item whose stock is not counted |
| Purchases | Sales & Purchases → Purchases | The supplier is owed the total; the goods enter the stock at what they cost, in any currency |
| Sales | Sales & Purchases → Sales | The customer owes the total; the goods leave at the average cost, which is the cost of goods sold. Over the counter to "Cash customer", or on credit |
| Returns | Sale Returns, Purchase Returns | The invoice turned around |
| Paid now | On every invoice | An ordinary Money In or Money Out paper, linked to the invoice |
| Stock count | The stock card of an item | The opening stock, and corrections after a count |
| Reports | Stock on Hand, Sales by Item, Sales by Customer | Profit and margin per item, the value of the stock, who buys the most |

### Branches (Pro)

Every branch keeps books of its own: its cash and bank accounts, what each customer and supplier has with it,
its papers, its stock and its profit. Customers, suppliers, items and the chart of accounts are one list for the
whole business.

- **Administration → Branches** adds a branch (with its first user). It starts with the rates of the main branch.
- A user works in his own branch. People of the main branch who manage the settings switch branch from the top bar.
- **Transactions → Money Between Branches** moves money from an account of one branch to an account of another;
  each branch writes its side on a settlement account, and the two cancel out.
- Profit & Loss, Balance Sheet and Customer & Supplier Balances can be read for one branch or for all together.
- What a customer is told on WhatsApp, and what he reads from his private link, is his balance with the whole business.
- Capital, drawings and profit distribution stay in the main branch.

In the code: `Branch::working()` (the helper `current_branch()`) is the branch whose books are open; papers use the
`BranchScoped` trait; `Branch::as($branch, fn)` runs something as a branch and `Branch::everywhere(fn)` reads them all.

### Several businesses, white label, sellers (Pro)

- **Administration → My Businesses**: a person who has a user in another business links it once (its address, email
  and password, and the two-step code if that user has one) and from then on opens it without signing in again. The
  jump uses a pass that is good once, for one minute (`login_tokens`). Nobody enters a business he has no user in.
- **White label**: on the page of a business in the admin panel, "its own name" replaces the name of the system for
  its users and the name of the company is left out (`tenants.brand_name`, helper `white_label()`).
- **Admin → Sellers and referrals**: a seller has a code and two percentages (first year, renewals). A business that
  registers through `/register?ref=<code>` or is given his code is his, and every payment it makes earns him his
  share. A client who brings another (`/register?ref=<its address>`) earns the reward of the settings once, when
  the new business first pays. Commissions are paid by hand and marked paid; a cancelled payment cancels them.

### The connection with SarafBook (Pro)

**Configuration → Connections** makes a key (`ksb_...`, shown once, only its fingerprint is kept) that works in the
books of one branch and may read only or also record. The other system calls `https://<business>/api/v1/...` with
`Authorization: Bearer <key>`: `business`, `rates`, `accounts` (list, one, new, statement), `money-in`, `money-out`,
`papers/{reference}` and its `cancel`. A `reference` sent twice is recorded once. The page lists every call.
Code: `routes/api.php`, `ApiAuth`, `Api\ConnectionApiController`.

### The assistant (Pro)

**Assistant** in the menu: the owner asks a question in Dari, Pashto or English and is answered from a summary of
his books (cash, rates, who owes and is owed, income and expenses of this month and the last, what is due). The
summary and the question go to the Claude API; the key, the model (default `claude-haiku-5-5`) and the number of
questions a business may ask in a day are on **Admin → Settings**. Without a key the page says it is switched off.
Code: `App\Services\Assistant`.

### Stock: units, warehouses, purchase costs (Pro)

- **Units** are a list of their own (Stock & Warehouses → Units); an item picks its unit from it.
- **Warehouses**: every branch has a main warehouse and may add more. Stock and average cost are kept per
  warehouse (`item_stocks.warehouse_id`); an invoice names the warehouse; **Stock Moves** takes goods from one to
  another at their cost (`Trade::move`). The stock of an item in a branch is the sum, its cost the weighted average.
- **Costs of a purchase** (customs, transport, rent ...): each is paid from a money account and all are shared
  over the goods by value, so the cost of one unit, and the average cost of the item, is counted after them
  (`invoice_costs`, `Trade::record(..., $costs, $warehouse)`).

### The demo

`php artisan demo:refresh` makes (or empties and refills) the business `demo`: about fifty accounts a section,
`DEMO_TRANSACTIONS` papers of two earlier years written in blocks by `DemoBulk` (default 100,000, under a minute)
and two months of a food wholesaler recorded through the services. It runs every midnight and on every deploy,
has no licence end, and its address, user and password are shown on the website.

Tests: `TradeTest`, `BranchTest`, `GrowthTest`, `ConnectionTest`, `DemoTest`. Voice POS is not started.


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
