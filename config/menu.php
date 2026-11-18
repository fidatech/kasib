<?php

/*
|--------------------------------------------------------------------------
| Sidebar menu of a business
|--------------------------------------------------------------------------
|
|   ['label' => 'Text', 'icon' => 'gauge-high', 'route' => 'dashboard']
|   ['label' => 'Text', 'icon' => 'users', 'children' => [
|       ['label' => 'Text', 'route' => 'users.index', 'active' => 'users.*', 'can' => 'users.view'],
|   ]]
|
| icon      a Font Awesome solid icon name; after adding one run: php tools/build-assets.php
| params    route parameters (and query values) of the link
| active    route-name pattern that highlights the entry (defaults to the route)
| can       permission needed to see the entry
| advanced  true: hidden while "Simple mode" is on in System Settings
| pro       true: a page of version 2, shown only to a business on the Pro plan
| dynamic   'account_groups': the children are the account categories of the business, one page
|           each. A category added under Configuration appears here by itself.
| url       instead of route: a page outside the system, opened in a new tab
|
*/

return [
    ['label' => 'Dashboard', 'icon' => 'gauge-high', 'route' => 'dashboard'],

    ['label' => 'Assistant', 'icon' => 'comments', 'route' => 'assistant.index', 'active' => 'assistant.*', 'can' => 'reports.view', 'pro' => true],

    ['label' => 'Accounts', 'icon' => 'address-book', 'dynamic' => 'account_groups', 'children' => []],

    // the row of buttons under the navbar (config/quickbar.php) opens the forms of the daily papers too
    ['label' => 'Transactions', 'icon' => 'right-left', 'children' => [
        ['label' => 'Money In', 'route' => 'money-in.index', 'active' => 'money-in.*', 'can' => 'transactions.view'],
        ['label' => 'Money Out', 'route' => 'money-out.index', 'active' => 'money-out.*', 'can' => 'transactions.view'],
        ['label' => 'Transfers', 'route' => 'transfers.index', 'active' => 'transfers.*', 'can' => 'transactions.view'],
        ['label' => 'Exchanges', 'route' => 'exchanges.index', 'active' => 'exchanges.*', 'can' => 'transactions.view'],
        ['label' => 'Income', 'route' => 'income.index', 'active' => 'income.*', 'can' => 'transactions.view'],
        ['label' => 'Expenses', 'route' => 'expenses.index', 'active' => 'expenses.*', 'can' => 'expenses.view'],
        ['label' => 'Recurring Expenses', 'route' => 'recurring-expenses.index', 'active' => 'recurring-expenses.*', 'can' => 'expenses.view'],
        ['label' => 'Expense Budgets', 'route' => 'budgets.index', 'active' => 'budgets.*', 'can' => 'expenses.view'],
        ['label' => 'Deals', 'route' => 'deals.index', 'active' => 'deals.*', 'can' => 'transactions.view'],
        ['label' => 'Money Between Branches', 'route' => 'branch-transfers.index', 'active' => 'branch-transfers.*', 'can' => 'transactions.view', 'pro' => true],
        ['label' => 'Hawala Tracking', 'route' => 'hawalas.index', 'active' => 'hawalas.*', 'can' => 'transactions.view'],
        ['label' => 'Cash Boxes & Handover', 'route' => 'handovers.index', 'active' => 'handovers.*', 'can' => 'transactions.view'],
        ['label' => 'Journal Entries', 'route' => 'journal.create', 'can' => 'journal.manage', 'advanced' => true],
    ]],

    ['label' => 'Sales & Purchases', 'icon' => 'file-invoice-dollar', 'pro' => true, 'children' => [
        ['label' => 'Sales', 'route' => 'trade.index', 'params' => ['kind' => 'sales'], 'can' => 'transactions.view'],
        ['label' => 'Purchases', 'route' => 'trade.index', 'params' => ['kind' => 'purchases'], 'can' => 'transactions.view'],
        ['label' => 'Sale Returns', 'route' => 'trade.index', 'params' => ['kind' => 'sale-returns'], 'can' => 'transactions.view'],
        ['label' => 'Purchase Returns', 'route' => 'trade.index', 'params' => ['kind' => 'purchase-returns'], 'can' => 'transactions.view'],
        ['label' => 'Items', 'route' => 'items.index', 'active' => 'items.*', 'can' => 'transactions.view'],
        ['label' => 'Units', 'route' => 'units.index', 'active' => 'units.*', 'can' => 'transactions.view'],
        ['label' => 'Warehouses', 'route' => 'warehouses.index', 'active' => 'warehouses.*', 'can' => 'transactions.view'],
        ['label' => 'Stock Moves', 'route' => 'stock-moves.index', 'active' => 'stock-moves.*', 'can' => 'transactions.view'],
        ['label' => 'Stock on Hand', 'route' => 'trade.reports.stock', 'can' => 'reports.view'],
        ['label' => 'Sales by Item', 'route' => 'trade.reports.items', 'can' => 'reports.view'],
        ['label' => 'Sales by Customer', 'route' => 'trade.reports.parties', 'can' => 'reports.view'],
    ]],

    ['label' => 'Finance', 'icon' => 'landmark', 'advanced' => true, 'children' => [
        ['label' => 'Chart of Accounts', 'route' => 'chart.index', 'can' => 'accounts.view'],
        ['label' => 'Opening Balances', 'route' => 'opening.index', 'active' => 'opening.*', 'can' => 'accounts.view'],
        ['label' => 'Shareholders', 'route' => 'shareholders.index', 'active' => 'shareholders.*', 'can' => 'capital.view'],
        ['label' => 'Capital & Drawings', 'route' => 'capital.index', 'active' => 'capital.*', 'can' => 'capital.view'],
        ['label' => 'Profit Distribution', 'route' => 'distributions.index', 'active' => 'distributions.*', 'can' => 'capital.view'],
        ['label' => 'Assets', 'route' => 'assets.index', 'active' => 'assets.*', 'can' => 'assets.view'],
        ['label' => 'Depreciation', 'route' => 'depreciation.index', 'active' => 'depreciation.*', 'can' => 'assets.view'],
        ['label' => 'Liabilities', 'route' => 'liabilities.index', 'active' => 'liabilities.*', 'can' => 'liabilities.view'],
        ['label' => 'Bank Reconciliation', 'route' => 'reconciliation.index', 'can' => 'accounts.view'],
        ['label' => 'Daily Cash Count', 'route' => 'cash-counts.index', 'active' => 'cash-counts.*', 'can' => 'transactions.view'],
        ['label' => 'Period Closing', 'route' => 'closing.index', 'can' => 'settings.view'],
    ]],

    ['label' => 'Reports', 'icon' => 'chart-column', 'children' => [
        ['label' => 'Roznamcha', 'route' => 'journal.index', 'active' => 'journal.*', 'can' => 'journal.view'],
        ['label' => 'Currency Position', 'route' => 'treasury.index', 'can' => 'accounts.view'],
        ['label' => 'Customer & Supplier Balances', 'route' => 'reports.balances', 'can' => 'reports.view'],
        ['label' => 'Due Payments', 'route' => 'reports.due', 'can' => 'reports.view'],
        ['label' => 'Calendar', 'route' => 'calendar.index', 'can' => 'reports.view'],
        ['label' => 'Reminders', 'route' => 'reminders.index', 'can' => 'accounts.view'],
        ['label' => 'Balance Confirmations', 'route' => 'confirmations.index', 'can' => 'accounts.view'],
        ['label' => 'Cash Forecast', 'route' => 'reports.forecast', 'can' => 'reports.view'],
        ['label' => 'Depositor Insights', 'route' => 'reports.depositors', 'can' => 'reports.view'],
        ['label' => 'Rate History', 'route' => 'reports.rates', 'can' => 'reports.view'],
        ['label' => 'Unusual Activity', 'route' => 'reports.alerts', 'can' => 'reports.view'],
        ['label' => 'Trial Balance', 'route' => 'reports.trial', 'can' => 'reports.view', 'advanced' => true],
        ['label' => 'Profit & Loss', 'route' => 'reports.profit', 'can' => 'reports.view', 'advanced' => true],
        ['label' => 'Balance Sheet', 'route' => 'reports.balance', 'can' => 'reports.view', 'advanced' => true],
        ['label' => 'Cancelled Papers', 'route' => 'journal.index', 'params' => ['cancelled' => 1], 'can' => 'journal.view'],
        ['label' => 'Zakat Report', 'route' => 'reports.zakat', 'can' => 'reports.view', 'advanced' => true],
        ['label' => 'Export Center', 'route' => 'export.index', 'active' => 'export.*', 'can' => 'reports.view'],
        ['label' => 'Scheduled Reports', 'route' => 'schedules.index', 'active' => 'schedules.*', 'can' => 'reports.view'],
        ['label' => 'All Reports', 'route' => 'reports.index', 'can' => 'reports.view'],
    ]],

    ['label' => 'Administration', 'icon' => 'user-shield', 'children' => [
        ['label' => 'Branches', 'route' => 'branches.index', 'active' => 'branches.*', 'can' => 'settings.view', 'pro' => true],
        ['label' => 'Users', 'route' => 'users.index', 'active' => 'users.*', 'can' => 'users.view'],
        ['label' => 'Roles', 'route' => 'roles.index', 'active' => 'roles.*', 'can' => 'users.view'],
        ['label' => 'Permissions', 'route' => 'permissions.index', 'can' => 'users.view'],
        ['label' => 'Audit Log', 'route' => 'audit.index', 'can' => 'audit.view'],
        ['label' => 'Sign-in History', 'route' => 'audit.index', 'params' => ['action' => 'signed in'], 'can' => 'audit.view'],
        ['label' => 'Two-step sign-in', 'route' => 'two-step.show'],
        ['label' => 'My Businesses', 'route' => 'businesses.index', 'active' => 'businesses.*', 'pro' => true],
        ['label' => 'Active Sessions', 'route' => 'sessions.index'],
        ['label' => 'Document Vault', 'route' => 'documents.index', 'active' => 'documents.*', 'can' => 'settings.view'],
        ['label' => 'Backup & Restore', 'route' => 'backups.index', 'active' => 'backups.*', 'can' => 'settings.manage'],
    ]],

    ['label' => 'Configuration', 'icon' => 'gear', 'children' => [
        ['label' => 'First Setup', 'route' => 'setup.show', 'can' => 'settings.manage'],
        ['label' => 'Connections', 'route' => 'connections.index', 'active' => 'connections.*', 'can' => 'settings.manage', 'pro' => true],
        ['label' => 'System Settings', 'route' => 'settings.index', 'active' => 'settings.*', 'can' => 'settings.view'],
        ['label' => 'Account Categories', 'route' => 'account-categories.index', 'active' => 'account-categories.*', 'can' => 'accounts.view'],
        ['label' => 'Currencies', 'route' => 'currencies.index', 'active' => 'currencies.*', 'can' => 'currencies.view'],
        ['label' => 'Exchange Rates', 'route' => 'rates.index', 'can' => 'days.view'],
        ['label' => 'Rate Alerts', 'route' => 'rate-alerts.index', 'active' => 'rate-alerts.*', 'can' => 'days.view'],
        ['label' => 'WhatsApp Messages', 'route' => 'whatsapp.index', 'active' => 'whatsapp.*', 'can' => 'settings.view'],
        ['label' => 'Alerts & Summary', 'route' => 'owner.index', 'active' => 'owner.*', 'can' => 'settings.view'],
        ['label' => 'Income & Expense Categories', 'route' => 'expense-categories.index', 'active' => 'expense-categories.*', 'can' => 'accounts.view'],
    ]],

    ['label' => 'Help', 'icon' => 'circle-question', 'route' => 'help.index'],

    // support, licence and invoices open from the menu of the profile in the navbar
];
