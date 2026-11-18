<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Central\AdminLog;
use App\Models\Central\CentralSetting;
use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\PaymentRequest;
use App\Models\Central\Tenant;
use App\Support\Num;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Renewal from inside the system. A business pays to the HesabPay number of the company and
 * sends the number of the transaction with a picture of it. When the administrator approves,
 * the payment is recorded on the invoice of the year and the licence moves on by itself.
 */
class PaymentRequests
{
    public static function submit(Tenant $tenant, mixed $amount, string $reference, ?UploadedFile $picture, ?string $sender): PaymentRequest
    {
        $reference = trim($reference);
        if (PaymentRequest::where('tenant_id', $tenant->id)->where('reference', $reference)->where('status', '!=', 'rejected')->exists()) {
            throw new UserError(__('This transaction number was already sent.'));
        }
        $request = PaymentRequest::create([
            'tenant_id' => $tenant->id, 'amount' => Num::money($amount), 'reference' => mb_substr($reference, 0, 120), 'sender' => $sender ? mb_substr($sender, 0, 150) : null,
            'invoice_id' => Invoice::where('tenant_id', $tenant->id)->where('status', 'unpaid')->orderBy('id')->value('id'),
            'screenshot_path' => $picture?->store('payment-requests/'.$tenant->id, 'local'),
        ]);
        // the company is told by email; a mail that cannot leave never stops the request
        try {
            if ($to = CentralSetting::read('company_email')) {
                Mail::raw($tenant->name.' ('.$tenant->domain.")\nHesabPay: ".$request->reference."\n$".$request->amount, fn ($m) => $m->to($to)->subject('Kasib · payment request · '.$tenant->name));
            }
        } catch (Throwable $e) {
            report($e);
        }

        return $request;
    }

    /** Approve: the payment goes on the open invoice (or on a new one for the next year) and the licence follows. */
    public static function approve(PaymentRequest $request, mixed $amount, int $adminId): Payment
    {
        if ($request->status !== 'pending') {
            throw new UserError(__('This request is already decided.'));
        }
        $tenant = $request->tenant;

        return DB::connection($request->getConnectionName())->transaction(function () use ($request, $tenant, $amount, $adminId) {
            $invoice = Invoice::where('tenant_id', $tenant->id)->where('status', 'unpaid')->orderBy('id')->first() ?? Billing::invoiceNextYear($tenant, 'HesabPay '.$request->reference);
            $payment = Payment::create([
                'tenant_id' => $tenant->id, 'invoice_id' => $invoice->id, 'amount' => Num::money($amount), 'method' => 'hesabpay', 'reference' => $request->reference,
                'paid_on' => now()->toDateString(), 'note' => 'Sent from inside the system'.($request->sender ? ' by '.$request->sender : ''), 'admin_id' => $adminId,
                'number' => sprintf('RCP-%06d', ((int) Payment::max('id')) + 1),
            ]);
            Billing::settle($invoice);
            Commissions::forPayment($payment);
            $request->update(['status' => 'approved', 'invoice_id' => $invoice->id, 'payment_id' => $payment->id, 'decided_by' => $adminId, 'decided_at' => now()]);
            AdminLog::record('payment', $tenant, $payment->number.' $'.$payment->amount.' hesabpay (request '.$request->id.')');

            return $payment;
        });
    }

    public static function reject(PaymentRequest $request, string $reason, int $adminId): void
    {
        if ($request->status !== 'pending') {
            throw new UserError(__('This request is already decided.'));
        }
        $request->update(['status' => 'rejected', 'reason' => $reason, 'decided_by' => $adminId, 'decided_at' => now()]);
        AdminLog::record('updated', $request->tenant, 'Payment request '.$request->id.' rejected: '.$reason);
    }
}
