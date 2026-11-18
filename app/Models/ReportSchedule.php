<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A workbook of the Export Center that is sent by email on its own. */
class ReportSchedule extends Model
{
    public const FREQUENCIES = ['daily' => 'Every day', 'weekly' => 'Every week', 'monthly' => 'Every month'];

    /** which sheets of the Export Center go into the workbook */
    public const CONTENTS = [
        'all' => ['Everything', null],
        'papers' => ['The papers of the period', ['money_in', 'money_out', 'transfers', 'exchanges', 'income', 'expenses', 'deals']],
        'statements' => ['The statements', ['trial', 'profit', 'balance']],
        'accounts' => ['The accounts and their balances', ['accounts']],
    ];

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'last_sent_on' => 'date:Y-m-d', 'day' => 'integer', 'hour' => 'integer'];
}
