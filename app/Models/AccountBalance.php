<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The running balance of one account. Only the ledger writes here. */
class AccountBalance extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'account_id';

    protected $guarded = [];

    protected $casts = ['updated_at' => 'datetime'];
}
