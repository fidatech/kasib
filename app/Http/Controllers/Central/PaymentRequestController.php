<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\PaymentRequest;
use App\Services\PaymentRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Payment requests: what the businesses say they paid through HesabPay, waiting to be checked. */
class PaymentRequestController extends Controller
{
    public function index(Request $request): View
    {
        $status = array_key_exists((string) $request->query('status'), PaymentRequest::STATUSES) ? (string) $request->query('status') : 'pending';

        return view('central.payment-requests', [
            'requests' => PaymentRequest::with(['tenant', 'invoice', 'payment'])->where('status', $status)->orderByDesc('id')->paginate(25)->withQueryString(),
            'status' => $status, 'counts' => PaymentRequest::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function approve(Request $request): RedirectResponse
    {
        $row = PaymentRequest::with('tenant')->findOrFail((int) $request->route('id'));
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0', 'max:10000000']]);
        $payment = PaymentRequests::approve($row, $data['amount'], (int) $request->user('admin')->id);

        return back()->with('success', __(':number saved.', ['number' => $payment->number]).' '.__('The licence of :name runs to :date.', ['name' => $row->tenant->name, 'date' => (string) $row->tenant->fresh()->licence_expires_on?->toDateString()]));
    }

    public function reject(Request $request): RedirectResponse
    {
        $row = PaymentRequest::with('tenant')->findOrFail((int) $request->route('id'));
        $data = $request->validate(['reason' => ['required', Rule::in(array_keys(PaymentRequest::REASONS))]]);
        PaymentRequests::reject($row, $data['reason'], (int) $request->user('admin')->id);

        return back()->with('success', __('Saved.'));
    }

    /** The picture of the payment. */
    public function picture(Request $request): Response
    {
        $row = PaymentRequest::findOrFail((int) $request->route('id'));
        abort_unless($row->screenshot_path && Storage::disk('local')->exists($row->screenshot_path), 404);

        return response(Storage::disk('local')->get($row->screenshot_path), 200, ['Content-Type' => Storage::disk('local')->mimeType($row->screenshot_path) ?: 'image/jpeg', 'X-Content-Type-Options' => 'nosniff']);
    }
}
