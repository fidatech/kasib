<?php

use App\Http\Controllers\AccountGroupController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BranchTransferController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\StatementLinkController;
use App\Http\Controllers\ConfirmationController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\CapitalController;
use App\Http\Controllers\CashCountController;
use App\Http\Controllers\Central\InvoiceController;
use App\Http\Controllers\Central\LoginController as CentralLoginController;
use App\Http\Controllers\Central\TenantController;
use App\Http\Controllers\ChartController;
use App\Http\Controllers\ClosingController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\DepreciationController;
use App\Http\Controllers\ExchangeController;
use App\Http\Controllers\HandoverController;
use App\Http\Controllers\HawalaController;
use App\Http\Controllers\InvoiceController as TradeInvoiceController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\TradeReportController;
use App\Http\Controllers\RateAlertController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FixedAssetController;
use App\Http\Controllers\Hr\DistributionController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\LiabilityController;
use App\Http\Controllers\MoneyInController;
use App\Http\Controllers\MoneyOutController;
use App\Http\Controllers\OpeningBalanceController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\PartyFileController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\RateController;
use App\Http\Controllers\RecurringExpenseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\ShareholderController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\ReceiptCheckController;
use App\Http\Controllers\TreasuryController;
use App\Http\Controllers\TwoStepController;
use App\Http\Controllers\WhatsappController;
use Illuminate\Support\Facades\Route;

// the address of an account category: /accounts/customers, /accounts/<any category the business added>
Route::pattern('group', '[a-z0-9]+(?:-[a-z0-9]+)*');

// The five routes of a paper that posts into the journal: list, form, record, cancel, receipt.
$paper = function (string $uri, string $controller, string $name): void {
    Route::get($uri, [$controller, 'index'])->name("$name.index");
    Route::get("$uri/create", [$controller, 'create'])->name("$name.create");
    Route::post($uri, [$controller, 'store'])->name("$name.store");
    Route::delete("$uri/{id}", [$controller, 'destroy'])->name("$name.destroy")->whereNumber('id');
    Route::get("$uri/{id}/receipt", [$controller, 'receipt'])->name("$name.receipt")->whereNumber('id');
};

// The six routes of a simple list-and-form screen.
$crud = function (string $uri, string $controller, string $name): void {
    Route::get($uri, [$controller, 'index'])->name("$name.index");
    Route::get("$uri/create", [$controller, 'create'])->name("$name.create");
    Route::post($uri, [$controller, 'store'])->name("$name.store");
    Route::get("$uri/{id}/edit", [$controller, 'edit'])->name("$name.edit")->whereNumber('id');
    Route::put("$uri/{id}", [$controller, 'update'])->name("$name.update")->whereNumber('id');
    Route::delete("$uri/{id}", [$controller, 'destroy'])->name("$name.destroy")->whereNumber('id');
};

// the language can be chosen before and after signing in, on every address
Route::post('language', [PreferenceController::class, 'locale'])->name('preferences.locale');

/*
|--------------------------------------------------------------------------
| System administrator panel (the central addresses only)
|--------------------------------------------------------------------------
*/
// what search engines may read: nothing, unless the system is set to be listed (APP_INDEXABLE)
Route::get('robots.txt', fn () => response("User-agent: *\n".(config('app.indexable') ? "Allow: /\n" : "Disallow: /\n"), 200, ['Content-Type' => 'text/plain']))->name('robots');

// the public website of the system, on the central addresses: what it is, what it costs, the request form
Route::middleware('central')->name('site.')->group(function () {
    Route::get('register', [\App\Http\Controllers\SiteController::class, 'register'])->name('register');
    Route::post('register', [\App\Http\Controllers\SiteController::class, 'store'])->name('register.store')->middleware('throttle:10,60');
    Route::get('register/{token}', [\App\Http\Controllers\SiteController::class, 'status'])->name('status')->where('token', '[a-z0-9]{32}');
    Route::get('open', [\App\Http\Controllers\SiteController::class, 'open'])->name('open');
});

Route::middleware('central')->prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [CentralLoginController::class, 'show'])->name('login');
    Route::post('login', [CentralLoginController::class, 'store'])->name('login.store');

    Route::middleware('auth:admin')->group(function () {
        $c = '\\App\\Http\\Controllers\\Central\\';
        Route::post('logout', [CentralLoginController::class, 'destroy'])->name('logout');
        Route::get('logo', [$c.'SettingsController', 'logo'])->name('logo');

        Route::get('/', [$c.'DashboardController', 'index'])->name('home');

        // clients
        Route::middleware('staff:clients.view')->group(function () use ($c) {
            Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
            Route::get('tenants/{id}', [$c.'ClientController', 'show'])->name('tenants.show')->whereNumber('id');
        });
        Route::middleware('staff:clients.manage')->group(function () use ($c) {
            Route::get('tenants/create', [TenantController::class, 'create'])->name('tenants.create');
            Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
            Route::get('tenants/{id}/edit', [TenantController::class, 'edit'])->name('tenants.edit')->whereNumber('id');
            Route::put('tenants/{id}', [TenantController::class, 'update'])->name('tenants.update')->whereNumber('id');
            Route::post('tenants/{id}/status', [TenantController::class, 'status'])->name('tenants.status')->whereNumber('id');
            Route::put('tenants/{id}/note', [$c.'ClientController', 'note'])->name('tenants.note')->whereNumber('id');
        });
        // requests made on the website
        Route::middleware('staff:clients.view')->get('signups', [$c.'SignupController', 'index'])->name('signups.index');
        Route::middleware('staff:clients.manage')->group(function () use ($c) {
            Route::post('signups/{id}/approve', [$c.'SignupController', 'approve'])->name('signups.approve')->whereNumber('id');
            Route::post('signups/{id}/reject', [$c.'SignupController', 'reject'])->name('signups.reject')->whereNumber('id');
        });
        Route::post('tenants/{id}/enter', [$c.'ClientController', 'enter'])->name('tenants.enter')->whereNumber('id')->middleware('staff:enter');

        // money
        Route::middleware('staff:billing.view')->group(function () use ($c) {
            Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
            Route::get('invoices/{id}', [InvoiceController::class, 'show'])->name('invoices.show')->whereNumber('id');
            Route::get('payments', [$c.'PaymentController', 'index'])->name('payments.index');
            Route::get('sellers', [$c.'SellerController', 'index'])->name('sellers.index');
            Route::get('payment-requests', [$c.'PaymentRequestController', 'index'])->name('payment-requests.index');
            Route::get('payment-requests/{id}/picture', [$c.'PaymentRequestController', 'picture'])->name('payment-requests.picture')->whereNumber('id');
            Route::get('payments/{id}/receipt', [$c.'PaymentController', 'receipt'])->name('payments.receipt')->whereNumber('id');
        });
        Route::middleware('staff:billing.manage')->group(function () use ($c) {
            Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
            Route::post('invoices/{id}/status', [InvoiceController::class, 'status'])->name('invoices.status')->whereNumber('id');
            Route::put('prices', [InvoiceController::class, 'prices'])->name('prices.update');
            Route::post('payments', [$c.'PaymentController', 'store'])->name('payments.store');
            Route::post('sellers', [$c.'SellerController', 'store'])->name('sellers.store');
            Route::put('sellers/{id}', [$c.'SellerController', 'update'])->name('sellers.update')->whereNumber('id');
            Route::post('sellers/reward', [$c.'SellerController', 'reward'])->name('sellers.reward');
            Route::post('commissions/{id}/paid', [$c.'SellerController', 'paid'])->name('sellers.paid')->whereNumber('id');
            Route::post('payment-requests/{id}/approve', [$c.'PaymentRequestController', 'approve'])->name('payment-requests.approve')->whereNumber('id');
            Route::post('payment-requests/{id}/reject', [$c.'PaymentRequestController', 'reject'])->name('payment-requests.reject')->whereNumber('id');
            Route::delete('payments/{id}', [$c.'PaymentController', 'destroy'])->name('payments.destroy')->whereNumber('id');
        });
        Route::get('income', [$c.'IncomeController', 'index'])->name('income.index')->middleware('staff:reports');

        // talking to the clients
        Route::middleware('staff:announcements')->group(function () use ($c) {
            Route::get('announcements', [$c.'AnnouncementController', 'index'])->name('announcements.index');
            Route::post('announcements', [$c.'AnnouncementController', 'store'])->name('announcements.store');
            Route::put('announcements/{id}', [$c.'AnnouncementController', 'update'])->name('announcements.update')->whereNumber('id');
            Route::delete('announcements/{id}', [$c.'AnnouncementController', 'destroy'])->name('announcements.destroy')->whereNumber('id');
        });
        Route::middleware('staff:tickets')->group(function () use ($c) {
            Route::get('tickets', [$c.'TicketController', 'index'])->name('tickets.index');
            Route::get('tickets/{id}', [$c.'TicketController', 'show'])->name('tickets.show')->whereNumber('id');
            Route::put('tickets/{id}', [$c.'TicketController', 'update'])->name('tickets.update')->whereNumber('id');
        });


        // the system
        Route::middleware('staff:backups')->group(function () use ($c) {
            Route::get('backups', [$c.'BackupController', 'index'])->name('backups.index');
            Route::post('backups', [$c.'BackupController', 'store'])->name('backups.store');
            Route::get('backups/{id}/download', [$c.'BackupController', 'download'])->name('backups.download')->whereNumber('id');
            Route::post('backups/{id}/restore', [$c.'BackupController', 'restore'])->name('backups.restore')->whereNumber('id');
            Route::delete('backups/{id}', [$c.'BackupController', 'destroy'])->name('backups.destroy')->whereNumber('id');
        });
        Route::middleware('staff:health')->group(function () use ($c) {
            Route::get('health', [$c.'HealthController', 'index'])->name('health.index');
            Route::post('health/migrate', [$c.'HealthController', 'migrate'])->name('health.migrate');
            Route::post('health/clear', [$c.'HealthController', 'clear'])->name('health.clear');
        });
        Route::get('logs', [$c.'LogController', 'index'])->name('logs.index')->middleware('staff:logs');
        Route::middleware('staff:staff')->group(function () use ($c) {
            Route::get('staff', [$c.'StaffController', 'index'])->name('staff.index');
            Route::post('staff', [$c.'StaffController', 'store'])->name('staff.store');
            Route::put('staff/{id}', [$c.'StaffController', 'update'])->name('staff.update')->whereNumber('id');
        });
        Route::middleware('staff:settings')->group(function () use ($c) {
            Route::get('settings', [$c.'SettingsController', 'edit'])->name('settings.edit');
            Route::put('settings', [$c.'SettingsController', 'update'])->name('settings.update');
        });
    });
});

/*
|--------------------------------------------------------------------------
| A business (every other address)
|--------------------------------------------------------------------------
*/
Route::middleware('tenant')->group(function () use ($crud, $paper) {
    // the support staff of Fida Technologies come in from the administration panel, with their reason
    Route::get('support-enter', [LoginController::class, 'support'])->name('support.enter');

    // the logo of the business is also the icon of its browser tab, so the sign-in page can ask for it too
    Route::get('company-logo', [SettingsController::class, 'logo'])->name('settings.logo');

    // anyone who holds a receipt can check it, without signing in
    Route::get('check/{number}/{code}', ReceiptCheckController::class)->name('receipts.verify')->where(['number' => '[A-Z]{2}-[0-9]{6,}', 'code' => '[a-f0-9]{12}'])->middleware('throttle:30,1');

    // a business whose licence ended sends its payment from the page that says so (answered by the tenancy middleware)
    Route::post('paid', fn () => redirect('/'))->name('service.paid')->middleware('throttle:6,60');

    // someone who came over from a linked business hands in the pass he was given there
    Route::get('enter/{token}', [\App\Http\Controllers\BusinessLinkController::class, 'enter'])->name('businesses.enter')->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:20,1');

    // a customer reads his own statement from the private link he was sent
    Route::get('s/{token}', [StatementLinkController::class, 'show'])->name('statement.shared')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:30,1');

    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->name('login.store');
        Route::get('two-step/code', [TwoStepController::class, 'challenge'])->name('two-step.challenge');
        Route::post('two-step/code', [TwoStepController::class, 'verify'])->name('two-step.verify')->middleware('throttle:10,1');
    });

    Route::middleware('auth')->group(function () use ($crud, $paper) {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

        // the service of Fida Technologies, seen from inside the business
        Route::get('service/licence', [\App\Http\Controllers\ServiceController::class, 'licence'])->name('service.licence');
        Route::get('service/invoices/{id}', [\App\Http\Controllers\ServiceController::class, 'invoice'])->name('service.invoice')->whereNumber('id');
        Route::post('service/pay', [\App\Http\Controllers\ServiceController::class, 'pay'])->name('service.pay')->middleware('throttle:10,60');
        Route::get('service/hesabpay-qr', [\App\Http\Controllers\ServiceController::class, 'qr'])->name('service.qr');
        Route::get('help', [\App\Http\Controllers\HelpController::class, 'index'])->name('help.index');
        Route::get('service/support', [\App\Http\Controllers\ServiceController::class, 'tickets'])->name('service.tickets');
        Route::post('service/support', [\App\Http\Controllers\ServiceController::class, 'open'])->name('service.tickets.open');
        Route::get('service/support/{id}', [\App\Http\Controllers\ServiceController::class, 'ticket'])->name('service.ticket')->whereNumber('id');
        Route::post('service/support/{id}', [\App\Http\Controllers\ServiceController::class, 'reply'])->name('service.ticket.reply')->whereNumber('id');

        Route::post('calendar', [PreferenceController::class, 'calendar'])->name('preferences.calendar');
        Route::get('profile', [PreferenceController::class, 'profile'])->name('profile.edit');
        Route::put('profile', [PreferenceController::class, 'updateProfile'])->name('profile.update');
        Route::get('password', [PreferenceController::class, 'password'])->name('password.edit');
        Route::put('password', [PreferenceController::class, 'updatePassword'])->name('password.update');
        Route::post('refresh', [PreferenceController::class, 'refresh'])->name('system.refresh');
        Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
        Route::post('sessions/others', [SessionController::class, 'others'])->name('sessions.others');
        Route::delete('sessions/{id}', [SessionController::class, 'destroy'])->name('sessions.destroy')->whereNumber('id');
        Route::get('two-step', [TwoStepController::class, 'show'])->name('two-step.show');
        Route::post('two-step', [TwoStepController::class, 'start'])->name('two-step.start');
        Route::post('two-step/confirm', [TwoStepController::class, 'confirm'])->name('two-step.confirm')->middleware('throttle:10,1');
        Route::delete('two-step', [TwoStepController::class, 'disable'])->name('two-step.disable');
        Route::get('messages', [PreferenceController::class, 'notices'])->name('notices.index');
        Route::get('messages/{id}', [PreferenceController::class, 'openNotice'])->name('notices.open')->whereNumber('id');
        Route::post('messages/read', [PreferenceController::class, 'readNotices'])->name('notices.read');

        Route::get('/', DashboardController::class)->name('dashboard');

        // accounts: one page for every account category, and the statement of one account
        $crud('accounts/{group}', PartyController::class, 'accounts');
        Route::get('accounts/{group}/{id}/statement', [PartyController::class, 'statement'])->name('accounts.statement')->whereNumber('id');
        Route::post('accounts/{group}/{id}/link', [StatementLinkController::class, 'store'])->name('accounts.link')->whereNumber('id');
        Route::delete('accounts/{group}/{id}/link', [StatementLinkController::class, 'destroy'])->name('accounts.unlink')->whereNumber('id');
        Route::get('accounts/{group}/{id}/notes', [PartyFileController::class, 'notes'])->name('accounts.notes')->whereNumber('id');
        Route::post('accounts/{group}/{id}/notes', [PartyFileController::class, 'storeNote'])->name('accounts.notes.store')->whereNumber('id');
        Route::post('notes/{note}/done', [PartyFileController::class, 'toggleNote'])->name('notes.toggle')->whereNumber('note');
        Route::get('reminders', [PartyFileController::class, 'reminders'])->name('reminders.index');

        // photos and PDFs kept with an account or a paper
        Route::get('files/{type}/{id}', [PartyFileController::class, 'attachments'])->name('attachments.index')->whereNumber('id')->whereIn('type', ['party', 'voucher']);
        Route::post('files/{type}/{id}', [PartyFileController::class, 'attach'])->name('attachments.store')->whereNumber('id')->whereIn('type', ['party', 'voucher']);
        Route::get('files/{type}/{id}/{file}', [PartyFileController::class, 'file'])->name('attachments.show')->whereNumber(['id', 'file'])->whereIn('type', ['party', 'voucher']);
        Route::delete('files/{type}/{id}/{file}', [PartyFileController::class, 'detach'])->name('attachments.destroy')->whereNumber(['id', 'file'])->whereIn('type', ['party', 'voucher']);

        // the first setup of a new business
        Route::get('setup', [SetupController::class, 'show'])->name('setup.show');
        Route::post('setup', [SetupController::class, 'store'])->name('setup.store');

        // transactions: the daily papers
        $paper('money-in', MoneyInController::class, 'money-in');
        $paper('money-out', MoneyOutController::class, 'money-out');
        $paper('transfers', TransferController::class, 'transfers');
        $paper('exchanges', ExchangeController::class, 'exchanges');
        $paper('income', IncomeController::class, 'income');
        $paper('expenses', ExpenseController::class, 'expenses');
        Route::post('recurring-expenses/run', [RecurringExpenseController::class, 'run'])->name('recurring-expenses.run');
        $crud('recurring-expenses', RecurringExpenseController::class, 'recurring-expenses');
        $crud('budgets', BudgetController::class, 'budgets');
        Route::post('hawalas/{id}/deliver', [HawalaController::class, 'deliver'])->name('hawalas.deliver')->whereNumber('id');
        Route::post('hawalas/{id}/cancel', [HawalaController::class, 'cancel'])->name('hawalas.cancel')->whereNumber('id');
        $crud('hawalas', HawalaController::class, 'hawalas');
        $crud('rate-alerts', RateAlertController::class, 'rate-alerts');
        // version 2, the Pro plan: items, sales, purchases and their returns
        Route::middleware('pro')->group(function () use ($crud) {
            Route::get('items/{id}/stock', [ItemController::class, 'stock'])->name('items.stock')->whereNumber('id');
            Route::post('items/{id}/stock', [ItemController::class, 'count'])->name('items.count')->whereNumber('id');
            $crud('items', ItemController::class, 'items');
            Route::get('reports/sales-by-item', [TradeReportController::class, 'items'])->name('trade.reports.items');
            Route::get('reports/stock', [TradeReportController::class, 'stock'])->name('trade.reports.stock');
            Route::get('reports/sales-by-customer', [TradeReportController::class, 'parties'])->name('trade.reports.parties');
            Route::get('businesses', [\App\Http\Controllers\BusinessLinkController::class, 'index'])->name('businesses.index');
            Route::post('businesses', [\App\Http\Controllers\BusinessLinkController::class, 'store'])->name('businesses.store')->middleware('throttle:6,10');
            Route::post('businesses/{id}/open', [\App\Http\Controllers\BusinessLinkController::class, 'open'])->name('businesses.open')->whereNumber('id');
            Route::delete('businesses/{id}', [\App\Http\Controllers\BusinessLinkController::class, 'destroy'])->name('businesses.destroy')->whereNumber('id');
            Route::get('connections', [\App\Http\Controllers\ConnectionController::class, 'index'])->name('connections.index');
            Route::post('connections', [\App\Http\Controllers\ConnectionController::class, 'store'])->name('connections.store');
            Route::delete('connections/{id}', [\App\Http\Controllers\ConnectionController::class, 'destroy'])->name('connections.destroy')->whereNumber('id');
            Route::get('assistant', [\App\Http\Controllers\AssistantController::class, 'index'])->name('assistant.index');
            Route::post('assistant', [\App\Http\Controllers\AssistantController::class, 'store'])->name('assistant.store')->middleware('throttle:10,1');
            Route::delete('assistant', [\App\Http\Controllers\AssistantController::class, 'destroy'])->name('assistant.destroy');
            Route::post('branches/switch', [BranchController::class, 'switch'])->name('branches.switch');
            $crud('branches', BranchController::class, 'branches');
            Route::post('branch-transfers/{id}/cancel', [BranchTransferController::class, 'cancel'])->name('branch-transfers.cancel')->whereNumber('id');
            $crud('branch-transfers', BranchTransferController::class, 'branch-transfers');
            $kinds = 'sales|purchases|sale-returns|purchase-returns';
            Route::get('trade/{kind}', [TradeInvoiceController::class, 'index'])->name('trade.index')->where('kind', $kinds);
            Route::get('trade/{kind}/create', [TradeInvoiceController::class, 'create'])->name('trade.create')->where('kind', $kinds);
            Route::post('trade/{kind}', [TradeInvoiceController::class, 'store'])->name('trade.store')->where('kind', $kinds);
            Route::get('trade/{kind}/{id}', [TradeInvoiceController::class, 'show'])->name('trade.show')->where('kind', $kinds)->whereNumber('id');
            Route::delete('trade/{kind}/{id}', [TradeInvoiceController::class, 'destroy'])->name('trade.destroy')->where('kind', $kinds)->whereNumber('id');
        });
        Route::get('reconciliation', [ReconciliationController::class, 'index'])->name('reconciliation.index');
        Route::put('reconciliation', [ReconciliationController::class, 'update'])->name('reconciliation.update');
        Route::get('handovers', [HandoverController::class, 'index'])->name('handovers.index');
        Route::post('handovers', [HandoverController::class, 'store'])->name('handovers.store');
        Route::put('handovers/boxes', [HandoverController::class, 'assign'])->name('handovers.assign');
        Route::post('handovers/{id}/accept', [HandoverController::class, 'accept'])->name('handovers.accept')->whereNumber('id');
        Route::get('journal', [JournalController::class, 'index'])->name('journal.index');
        Route::get('journal/entries', [JournalEntryController::class, 'create'])->name('journal.create');
        Route::post('journal/entries', [JournalEntryController::class, 'store'])->name('journal.store');
        Route::delete('journal/entries/{id}', [JournalEntryController::class, 'destroy'])->name('journal.destroy')->whereNumber('id');

        // deals: what was paid for something bought to be sold, what came in, and its profit or loss
        Route::get('deals', [DealController::class, 'index'])->name('deals.index');
        Route::post('deals', [DealController::class, 'store'])->name('deals.store');
        Route::get('deals/{id}', [DealController::class, 'show'])->name('deals.show')->whereNumber('id');
        Route::post('deals/{id}/moves', [DealController::class, 'move'])->name('deals.move')->whereNumber('id');
        Route::delete('deals/{id}/moves/{paper}', [DealController::class, 'cancel'])->name('deals.cancel')->whereNumber(['id', 'paper']);
        Route::post('deals/{id}/close', [DealController::class, 'close'])->name('deals.close')->whereNumber('id');

        // finance
        Route::get('chart-of-accounts', [ChartController::class, 'index'])->name('chart.index');
        Route::get('opening-balances', [OpeningBalanceController::class, 'index'])->name('opening.index');
        Route::get('opening-balances/template', [OpeningBalanceController::class, 'template'])->name('opening.template');
        Route::post('opening-balances', [OpeningBalanceController::class, 'store'])->name('opening.store');
        $crud('shareholders', ShareholderController::class, 'shareholders');
        $paper('capital', CapitalController::class, 'capital');
        $paper('distributions', DistributionController::class, 'distributions');
        $paper('assets', FixedAssetController::class, 'assets');
        $paper('depreciation', DepreciationController::class, 'depreciation');
        $paper('liabilities', LiabilityController::class, 'liabilities');
        $paper('cash-counts', CashCountController::class, 'cash-counts');
        Route::get('closing', [ClosingController::class, 'index'])->name('closing.index');
        Route::post('closing', [ClosingController::class, 'store'])->name('closing.store');

        // reports
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/trial-balance', [ReportController::class, 'trial'])->name('reports.trial');
        Route::get('reports/profit-and-loss', [ReportController::class, 'profit'])->name('reports.profit');
        Route::get('reports/balance-sheet', [ReportController::class, 'balance'])->name('reports.balance');
        Route::get('reports/balances', [ReportController::class, 'balances'])->name('reports.balances');
        Route::get('reports/due-payments', [ReportController::class, 'due'])->name('reports.due');
        Route::get('reports/unusual-activity', [ReportController::class, 'alerts'])->name('reports.alerts');
        Route::get('reports/cash-forecast', [ReportController::class, 'forecast'])->name('reports.forecast');
        Route::get('reports/rate-history', [ReportController::class, 'rates'])->name('reports.rates');
        Route::get('reports/depositors', [ReportController::class, 'depositors'])->name('reports.depositors');
        Route::get('reports/zakat', [ReportController::class, 'zakat'])->name('reports.zakat');
        Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
        Route::post('schedules/{id}/send', [ScheduleController::class, 'send'])->name('schedules.send')->whereNumber('id');
        $crud('schedules', ScheduleController::class, 'schedules');
        Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::get('documents/{id}', [DocumentController::class, 'show'])->name('documents.show')->whereNumber('id');
        Route::delete('documents/{id}', [DocumentController::class, 'destroy'])->name('documents.destroy')->whereNumber('id');
        Route::get('confirmations', [ConfirmationController::class, 'index'])->name('confirmations.index');
        Route::post('confirmations/send', [ConfirmationController::class, 'send'])->name('confirmations.send');
        Route::post('confirmations/{id}', [ConfirmationController::class, 'answer'])->name('confirmations.answer')->whereNumber('id');
        Route::get('currency-position', [TreasuryController::class, 'index'])->name('treasury.index');
        Route::get('export', [ExportController::class, 'index'])->name('export.index');
        Route::get('export/download', [ExportController::class, 'download'])->name('export.download');

        // administration
        $crud('users', UserController::class, 'users');
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{id}/edit', [RoleController::class, 'edit'])->name('roles.edit')->whereNumber('id');
        Route::put('roles/{id}', [RoleController::class, 'update'])->name('roles.update')->whereNumber('id');
        Route::delete('roles/{id}', [RoleController::class, 'destroy'])->name('roles.destroy')->whereNumber('id');
        Route::get('permissions', [RoleController::class, 'permissions'])->name('permissions.index');
        Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('backups', [BackupController::class, 'store'])->name('backups.store');
        Route::post('backups/upload', [BackupController::class, 'upload'])->name('backups.upload');
        Route::get('backups/{id}/download', [BackupController::class, 'download'])->name('backups.download')->whereNumber('id');
        Route::post('backups/{id}/restore', [BackupController::class, 'restore'])->name('backups.restore')->whereNumber('id');

        // configuration
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        $crud('account-categories', AccountGroupController::class, 'account-categories');
        $crud('currencies', CurrencyController::class, 'currencies');
        Route::get('rates', [RateController::class, 'index'])->name('rates.index');
        Route::put('rates', [RateController::class, 'update'])->name('rates.update');
        $crud('expense-categories', ExpenseCategoryController::class, 'expense-categories');
        Route::get('owner', [OwnerController::class, 'index'])->name('owner.index');
        Route::put('owner', [OwnerController::class, 'update'])->name('owner.update');
        Route::post('owner/summary', [OwnerController::class, 'summary'])->name('owner.summary');
        Route::get('whatsapp', [WhatsappController::class, 'index'])->name('whatsapp.index');
        Route::put('whatsapp', [WhatsappController::class, 'update'])->name('whatsapp.update');
        Route::post('whatsapp/test', [WhatsappController::class, 'test'])->name('whatsapp.test');
    });
});
