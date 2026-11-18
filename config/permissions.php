<?php

/*
| What a role can be allowed to do. Every module has two switches:
| "view" (open and read) and "manage" (add, change, cancel).
| The Administrator role (the owner) always has everything.
| Modules are added here as they are built.
*/

return [
    'accounts' => 'Accounts of every category, account categories and the chart of accounts',
    'transactions' => 'Money in, money out, transfers, exchanges and income',
    'expenses' => 'Expenses',
    'days' => 'Exchange rates',
    'journal' => 'Day book (roznamcha) and journal entries',
    'capital' => 'Shareholders, capital, drawings and profit distribution',
    'assets' => 'Assets and depreciation',
    'liabilities' => 'Liabilities and their payment',
    'reports' => 'Reports',
    'currencies' => 'Currencies',
    'users' => 'Users and roles',
    'settings' => 'Settings of the business and period closing',
    'audit' => 'Audit log',
];
