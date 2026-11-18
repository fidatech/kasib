{{-- Where to pay, and the form "we paid". Shown on the licence page of a business. --}}
<div class="card mt-3 mb-0" id="pay">
    <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Pay the subscription through HesabPay') }}</h2></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-lg-5">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted">{{ __('To pay now') }}</div>
                    <div class="h4 mb-1" dir="ltr">${{ money($due) }}@if (\App\Support\Num::isPositive($hesabpay['afn_rate'] ?: 0)) <span class="small text-muted">≈ {{ money(\App\Support\Num::mul($due, $hesabpay['afn_rate'])) }} AFN</span>@endif</div>
                    <div class="small text-muted mb-3">{{ $dueFor }}</div>
                    @if ($hesabpay['number'])
                        <div class="small text-muted">{{ __('HesabPay number') }}</div>
                        <div class="h5 mb-1" dir="ltr">{{ $hesabpay['number'] }}</div>
                        <div class="mb-2">{{ $hesabpay['name'] }}</div>
                        @if ($hesabpay['qr'])<img src="{{ route('service.qr') }}" alt="HesabPay QR" style="max-width: 180px" class="border rounded">@endif
                    @else
                        <div class="text-muted">{{ __('Ask :company for its HesabPay number.', ['company' => $company['company_name']]) }}</div>
                    @endif
                </div>
            </div>
            <div class="col-12 col-lg-7">
                @can('settings.manage')
                    <form method="POST" action="{{ route('service.pay') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label" for="p-amount">{{ __('Amount paid (US dollars)') }} <span class="req">*</span></label>
                                <input type="text" inputmode="decimal" dir="ltr" class="form-control @error('amount') is-invalid @enderror" id="p-amount" name="amount" value="{{ old('amount', $due) }}" required>
                                @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="p-ref">{{ __('Transaction number') }} <span class="req">*</span></label>
                                <input type="text" dir="ltr" class="form-control @error('reference') is-invalid @enderror" id="p-ref" name="reference" value="{{ old('reference') }}" maxlength="120" required>
                                @error('reference')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="p-pic">{{ __('Picture of the payment') }} <span class="req">*</span></label>
                                <input type="file" class="form-control @error('picture') is-invalid @enderror" id="p-pic" name="picture" accept="image/png,image/jpeg,image/webp" required>
                                @error('picture')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-primary"><x-icon name="paper-plane" class="me-1" /> {{ __('We paid: send it') }}</button></div>
                        </div>
                    </form>
                @endcan
                @if ($requests->isNotEmpty())
                    <table class="table table-sm w-100 mt-3 mb-0">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Transaction number') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @foreach ($requests as $row)
                                <tr>
                                    <td class="text-nowrap">{{ fmt_date($row->created_at) }}</td><td dir="ltr">{{ $row->reference }}</td><td class="text-end" dir="ltr">${{ money($row->amount) }}</td>
                                    <td><span class="badge {{ ['pending' => 'bg-secondary-subtle text-secondary', 'approved' => 'bg-success-subtle text-success', 'rejected' => 'bg-danger-subtle text-danger'][$row->status] ?? '' }}">{{ __(\App\Models\Central\PaymentRequest::STATUSES[$row->status] ?? $row->status) }}</span>
                                        @if ($row->status === 'rejected')<div class="small text-muted">{{ __(\App\Models\Central\PaymentRequest::REASONS[$row->reason] ?? (string) $row->reason) }}</div>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>
