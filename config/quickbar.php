<?php

/*
|--------------------------------------------------------------------------
| The row of daily work, under the navbar
|--------------------------------------------------------------------------
|
| Buttons shown on every page of a business; each opens the form of a new paper. An entry is
| written like an entry of config/menu.php (label, icon, route, params, active, can), with
| 'tone' for the colour of its icon. Everything else stays in the sidebar.
|
*/

return [
    ['label' => 'Money In', 'icon' => 'arrow-down', 'tone' => 'success', 'route' => 'money-in.create', 'active' => 'money-in.*', 'can' => 'transactions.manage'],
    ['label' => 'Money Out', 'icon' => 'arrow-up', 'tone' => 'danger', 'route' => 'money-out.create', 'active' => 'money-out.*', 'can' => 'transactions.manage'],
    ['label' => 'Exchange', 'icon' => 'arrow-right-arrow-left', 'tone' => 'primary', 'route' => 'exchanges.create', 'active' => 'exchanges.*', 'can' => 'transactions.manage'],
    ['label' => 'Transfer', 'icon' => 'right-left', 'tone' => 'purple', 'route' => 'transfers.create', 'active' => 'transfers.*', 'can' => 'transactions.manage'],
    ['label' => 'Expense', 'icon' => 'receipt', 'tone' => 'warning', 'route' => 'expenses.create', 'active' => 'expenses.*', 'can' => 'expenses.manage'],
];
