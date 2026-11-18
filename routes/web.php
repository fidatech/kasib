<?php

use App\Http\Controllers\AccountGroupController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CapitalController;
use App\Http\Controllers\Central\InvoiceController;
use App\Http\Controllers\Central\LoginController as CentralLoginController;
use App\Http\Controllers\Central\TenantController;
use App\Http\Controllers\ChartController;
use App\Http\Controllers\ClosingController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepreciationController;
use App\Http\Controllers\ExchangeController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FixedAssetController;
use App\Http\Controllers\Hr\DistributionController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\LiabilityController;
use App\Http\Controllers\MoneyInController;
use App\Http\Controllers\MoneyOutController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\RateController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShareholderController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\TreasuryController;
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
            Route::get('payments/{id}/receipt', [$c.'PaymentController', 'receipt'])->name('payments.receipt')->whereNumber('id');
        });
        Route::middleware('staff:billing.manage')->group(function () use ($c) {
            Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
            Route::post('invoices/{id}/status', [InvoiceController::class, 'status'])->name('invoices.status')->whereNumber('id');
            Route::put('prices', [InvoiceController::class, 'prices'])->name('prices.update');
            Route::post('payments', [$c.'PaymentController', 'store'])->name('payments.store');
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

    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->name('login.store');
    });

    Route::middleware('auth')->group(function () use ($crud, $paper) {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

        // the service of Fida Technologies, seen from inside the business
        Route::get('service/licence', [\App\Http\Controllers\ServiceController::class, 'licence'])->name('service.licence');
        Route::get('service/invoices/{id}', [\App\Http\Controllers\ServiceController::class, 'invoice'])->name('service.invoice')->whereNumber('id');
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
        Route::get('messages', [PreferenceController::class, 'notices'])->name('notices.index');
        Route::get('messages/{id}', [PreferenceController::class, 'openNotice'])->name('notices.open')->whereNumber('id');
        Route::post('messages/read', [PreferenceController::class, 'readNotices'])->name('notices.read');

        Route::get('/', DashboardController::class)->name('dashboard');

        // accounts: one page for every account category, and the statement of one account
        $crud('accounts/{group}', PartyController::class, 'accounts');
        Route::get('accounts/{group}/{id}/statement', [PartyController::class, 'statement'])->name('accounts.statement')->whereNumber('id');

        // transactions: the daily papers
        $paper('money-in', MoneyInController::class, 'money-in');
        $paper('money-out', MoneyOutController::class, 'money-out');
        $paper('transfers', TransferController::class, 'transfers');
        $paper('exchanges', ExchangeController::class, 'exchanges');
        $paper('income', IncomeController::class, 'income');
        $paper('expenses', ExpenseController::class, 'expenses');
        Route::get('journal', [JournalController::class, 'index'])->name('journal.index');

        // finance
        Route::get('chart-of-accounts', [ChartController::class, 'index'])->name('chart.index');
        $crud('shareholders', ShareholderController::class, 'shareholders');
        $paper('capital', CapitalController::class, 'capital');
        $paper('distributions', DistributionController::class, 'distributions');
        $paper('assets', FixedAssetController::class, 'assets');
        $paper('depreciation', DepreciationController::class, 'depreciation');
        $paper('liabilities', LiabilityController::class, 'liabilities');
        Route::get('closing', [ClosingController::class, 'index'])->name('closing.index');
        Route::post('closing', [ClosingController::class, 'store'])->name('closing.store');

        // reports
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/trial-balance', [ReportController::class, 'trial'])->name('reports.trial');
        Route::get('reports/profit-and-loss', [ReportController::class, 'profit'])->name('reports.profit');
        Route::get('reports/balance-sheet', [ReportController::class, 'balance'])->name('reports.balance');
        Route::get('reports/balances', [ReportController::class, 'balances'])->name('reports.balances');
        Route::get('reports/due-payments', [ReportController::class, 'due'])->name('reports.due');
        Route::get('currency-position', [TreasuryController::class, 'index'])->name('treasury.index');

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

        // configuration
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        $crud('account-categories', AccountGroupController::class, 'account-categories');
        $crud('currencies', CurrencyController::class, 'currencies');
        Route::get('rates', [RateController::class, 'index'])->name('rates.index');
        Route::put('rates', [RateController::class, 'update'])->name('rates.update');
        $crud('expense-categories', ExpenseCategoryController::class, 'expense-categories');
    });
});
