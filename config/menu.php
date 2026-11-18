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
| dynamic   'account_groups': the children are the account categories of the business, one page
|           each. A category added under Configuration appears here by itself.
| url       instead of route: a page outside the system, opened in a new tab
|
*/

return [
    ['label' => 'Dashboard', 'icon' => 'gauge-high', 'route' => 'dashboard'],

    ['label' => 'Accounts', 'icon' => 'address-book', 'dynamic' => 'account_groups', 'children' => []],

    // the row of buttons under the navbar (config/quickbar.php) opens the forms of the daily papers too
    ['label' => 'Transactions', 'icon' => 'right-left', 'children' => [
        ['label' => 'Money In', 'route' => 'money-in.index', 'active' => 'money-in.*', 'can' => 'transactions.view'],
        ['label' => 'Money Out', 'route' => 'money-out.index', 'active' => 'money-out.*', 'can' => 'transactions.view'],
        ['label' => 'Transfers', 'route' => 'transfers.index', 'active' => 'transfers.*', 'can' => 'transactions.view'],
        ['label' => 'Exchanges', 'route' => 'exchanges.index', 'active' => 'exchanges.*', 'can' => 'transactions.view'],
        ['label' => 'Income', 'route' => 'income.index', 'active' => 'income.*', 'can' => 'transactions.view'],
        ['label' => 'Expenses', 'route' => 'expenses.index', 'active' => 'expenses.*', 'can' => 'expenses.view'],
    ]],

    ['label' => 'Finance', 'icon' => 'landmark', 'advanced' => true, 'children' => [
        ['label' => 'Chart of Accounts', 'route' => 'chart.index', 'can' => 'accounts.view'],
        ['label' => 'Shareholders', 'route' => 'shareholders.index', 'active' => 'shareholders.*', 'can' => 'capital.view'],
        ['label' => 'Capital & Drawings', 'route' => 'capital.index', 'active' => 'capital.*', 'can' => 'capital.view'],
        ['label' => 'Profit Distribution', 'route' => 'distributions.index', 'active' => 'distributions.*', 'can' => 'capital.view'],
        ['label' => 'Assets', 'route' => 'assets.index', 'active' => 'assets.*', 'can' => 'assets.view'],
        ['label' => 'Depreciation', 'route' => 'depreciation.index', 'active' => 'depreciation.*', 'can' => 'assets.view'],
        ['label' => 'Liabilities', 'route' => 'liabilities.index', 'active' => 'liabilities.*', 'can' => 'liabilities.view'],
        ['label' => 'Period Closing', 'route' => 'closing.index', 'can' => 'settings.view'],
    ]],

    ['label' => 'Reports', 'icon' => 'chart-column', 'children' => [
        ['label' => 'Roznamcha', 'route' => 'journal.index', 'active' => 'journal.*', 'can' => 'journal.view'],
        ['label' => 'Currency Position', 'route' => 'treasury.index', 'can' => 'accounts.view'],
        ['label' => 'Customer & Supplier Balances', 'route' => 'reports.balances', 'can' => 'reports.view'],
        ['label' => 'Due Payments', 'route' => 'reports.due', 'can' => 'reports.view'],
        ['label' => 'Trial Balance', 'route' => 'reports.trial', 'can' => 'reports.view', 'advanced' => true],
        ['label' => 'Profit & Loss', 'route' => 'reports.profit', 'can' => 'reports.view', 'advanced' => true],
        ['label' => 'Balance Sheet', 'route' => 'reports.balance', 'can' => 'reports.view', 'advanced' => true],
        ['label' => 'All Reports', 'route' => 'reports.index', 'can' => 'reports.view'],
    ]],

    ['label' => 'Administration', 'icon' => 'user-shield', 'children' => [
        ['label' => 'Users', 'route' => 'users.index', 'active' => 'users.*', 'can' => 'users.view'],
        ['label' => 'Roles', 'route' => 'roles.index', 'active' => 'roles.*', 'can' => 'users.view'],
        ['label' => 'Permissions', 'route' => 'permissions.index', 'can' => 'users.view'],
        ['label' => 'Audit Log', 'route' => 'audit.index', 'can' => 'audit.view'],
    ]],

    ['label' => 'Configuration', 'icon' => 'gear', 'children' => [
        ['label' => 'System Settings', 'route' => 'settings.index', 'active' => 'settings.*', 'can' => 'settings.view'],
        ['label' => 'Account Categories', 'route' => 'account-categories.index', 'active' => 'account-categories.*', 'can' => 'accounts.view'],
        ['label' => 'Currencies', 'route' => 'currencies.index', 'active' => 'currencies.*', 'can' => 'currencies.view'],
        ['label' => 'Exchange Rates', 'route' => 'rates.index', 'can' => 'days.view'],
        ['label' => 'Income & Expense Categories', 'route' => 'expense-categories.index', 'active' => 'expense-categories.*', 'can' => 'accounts.view'],
    ]],

    // support, licence and invoices open from the menu of the profile in the navbar
];
