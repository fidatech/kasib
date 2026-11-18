<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The page behind the address printed on a receipt. Anyone who holds the receipt can open it
 * without signing in; it shows only what is on the receipt, and whether it is real, changed or cancelled.
 */
class ReceiptCheckController extends Controller
{
    public function __invoke(Request $request): View
    {
        $voucher = Voucher::withoutGlobalScope('branch')->with(['party', 'currency', 'otherCurrency'])->where('number', (string) $request->route('number'))->first();
        if ($voucher && ! hash_equals(VoucherController::code($voucher), (string) $request->route('code'))) {
            $voucher = null;
        }

        return view('verify.show', ['voucher' => $voucher, 'business' => (string) (setting('company_name') ?: tenant()?->name)]);
    }
}
