<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "We paid": what a business sent after paying its subscription through HesabPay. */
class PaymentRequest extends Model
{
    use UsesCentralConnection;

    public const STATUSES = ['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Rejected'];

    /** why a request is rejected: chosen from this list, never typed */
    public const REASONS = [
        'not_received' => 'The money did not arrive on our HesabPay account',
        'wrong_amount' => 'The amount that arrived is less than what was written',
        'wrong_receipt' => 'The picture is not the receipt of this payment',
        'unreadable' => 'The picture cannot be read',
        'duplicate' => 'This payment was already sent and recorded',
    ];

    protected $guarded = [];

    protected $casts = ['decided_at' => 'datetime', 'amount' => 'decimal:2'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
