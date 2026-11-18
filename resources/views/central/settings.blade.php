@extends('layouts.central', ['title' => __('Settings')])

@section('content')
<x-page-header :title="__('Settings')" icon="gear" :subtitle="__('Your company on invoices and receipts, and the rules of licences and backups')" />

<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    @if ($errors->any())
        <div class="service-strip is-danger"><x-icon name="triangle-exclamation" /><div><strong>{{ __('Some boxes need a look.') }}</strong>{{ $errors->first() }}</div></div>
    @endif

    <div class="row g-3">
        {{-- who the service belongs to: printed on every invoice and receipt --}}
        <div class="col-12 col-xl-7">
            <div class="card mb-0">
                <div class="card-header"><div class="set-head"><span class="set-icon"><x-icon name="building" /></span><div><h2 class="h5">{{ __('Company') }}</h2><small>{{ __('Printed on top of every invoice and receipt') }}</small></div></div></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6"><label class="form-label" for="c-name">{{ __('Name') }} <span class="text-danger">*</span></label><input type="text" class="form-control @error('company_name') is-invalid @enderror" id="c-name" name="company_name" value="{{ old('company_name', $company['company_name']) }}" required>@error('company_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-12 col-md-6"><label class="form-label" for="c-web">{{ __('Website') }}</label><div class="input-group"><span class="input-group-text"><x-icon name="globe" /></span><input type="text" class="form-control" id="c-web" name="company_website" value="{{ old('company_website', $company['company_website']) }}" dir="ltr" placeholder="fida.af"></div></div>
                        <div class="col-12 col-md-6"><label class="form-label" for="c-phone">{{ __('Phone') }}</label><div class="input-group"><span class="input-group-text"><x-icon name="phone" /></span><input type="text" class="form-control" id="c-phone" name="company_phone" value="{{ old('company_phone', $company['company_phone']) }}" dir="ltr" placeholder="+93 700 000 000"></div></div>
                        <div class="col-12 col-md-6"><label class="form-label" for="c-email">{{ __('Email') }}</label><div class="input-group"><span class="input-group-text"><x-icon name="envelope" /></span><input type="email" class="form-control @error('company_email') is-invalid @enderror" id="c-email" name="company_email" value="{{ old('company_email', $company['company_email']) }}" dir="ltr" placeholder="info@fida.af"></div>@error('company_email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                        <div class="col-12"><label class="form-label" for="c-address">{{ __('Address') }}</label><div class="input-group"><span class="input-group-text"><x-icon name="location-dot" /></span><input type="text" class="form-control" id="c-address" name="company_address" value="{{ old('company_address', $company['company_address']) }}"></div></div>
                        <div class="col-12">
                            <label class="form-label" for="c-logo">{{ __('Logo') }}</label>
                            <div class="logo-box">
                                <div class="logo-box-preview">@if ($logo)<img src="{{ route('admin.logo') }}?v={{ time() }}" alt="{{ __('Logo') }}">@else<x-icon name="image" />@endif</div>
                                <div class="flex-grow-1">
                                    <input type="file" class="form-control @error('logo') is-invalid @enderror" id="c-logo" name="logo" accept=".png,.jpg,.jpeg,.webp">
                                    @error('logo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    <div class="form-text">{{ __('PNG, JPG or WebP, up to 1 MB. It is printed on invoices and receipts.') }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12"><label class="form-label" for="c-note">{{ __('Text under every invoice') }}</label><textarea class="form-control @error('invoice_note') is-invalid @enderror" id="c-note" name="invoice_note" rows="4" placeholder="{{ __('For example how to pay: your bank account, HesabPay number, and the days to pay in.') }}">{{ old('invoice_note', $company['invoice_note']) }}</textarea></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="card mb-3">
                <div class="card-header"><div class="set-head"><span class="set-icon"><x-icon name="calendar-days" /></span><div><h2 class="h5">{{ __('Licence control') }}</h2><small>{{ __('What happens when the licence of a business comes to its end') }}</small></div></div></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6"><label class="form-label" for="l-remind">{{ __('Remind before the end') }} <x-help :text="__('From this many days before its licence ends, the business sees a reminder on top of its screens.')" /></label><div class="input-group"><input type="text" inputmode="numeric" dir="ltr" autocomplete="off" class="form-control @error('remind_days') is-invalid @enderror" id="l-remind" name="remind_days" value="{{ old('remind_days', $rules['remind_days']) }}" min="0" max="180" required><span class="input-group-text">{{ __('days') }}</span></div></div>
                        <div class="col-6"><label class="form-label" for="l-grace">{{ __('Days of grace') }} <x-help :text="__('After its licence ends the business keeps working this many days, with a warning.')" /></label><div class="input-group"><input type="text" inputmode="numeric" dir="ltr" autocomplete="off" class="form-control @error('grace_days') is-invalid @enderror" id="l-grace" name="grace_days" value="{{ old('grace_days', $rules['grace_days']) }}" min="0" max="90" required><span class="input-group-text">{{ __('days') }}</span></div></div>
                        <div class="col-12">
                            <label class="switch-row form-check form-switch" for="l-lock">
                                <input class="form-check-input" type="checkbox" role="switch" id="l-lock" name="auto_lock" value="1" @checked(old('auto_lock', $rules['auto_lock']))>
                                <div><strong>{{ __('Lock a business by itself when the days of grace are over') }}</strong><span>{{ __('It opens again by itself the moment a paid invoice moves its licence date. A business you suspend by hand is never opened by the system.') }}</span></div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-0">
                <div class="card-header"><div class="set-head"><span class="set-icon"><x-icon name="database" /></span><div><h2 class="h5">{{ __('Backups') }}</h2><small>{{ __('A copy of every business is saved each night') }}</small></div></div></div>
                <div class="card-body">
                    <label class="form-label" for="b-keep">{{ __('Keep the nightly backups for') }}</label>
                    <div class="input-group" style="max-width:220px"><input type="text" inputmode="numeric" dir="ltr" autocomplete="off" class="form-control @error('backup_keep_days') is-invalid @enderror" id="b-keep" name="backup_keep_days" value="{{ old('backup_keep_days', $keep) }}" min="1" max="365" required><span class="input-group-text">{{ __('days') }}</span></div>
                    <div class="form-text">{{ __('Backups made by hand are kept until someone deletes them.') }}</div>
                </div>
            </div>
        </div>

        {{-- the four prices of the service, side by side --}}
        <div class="col-12" id="prices">
            <div class="card mb-0">
                <div class="card-header"><div class="set-head"><span class="set-icon"><x-icon name="tags" /></span><div><h2 class="h5">{{ __('Prices (US dollars)') }}</h2><small>{{ __('Two plans, Starter and Pro: the first-year price once, then the yearly price. The plan of a business is written on the business itself.') }}</small></div></div></div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach (['main_first' => 'Paid once, when the business starts', 'main_renewal' => 'Every year after the first', 'pro_first' => 'Paid once, when the business starts', 'pro_renewal' => 'Every year after the first', 'branch_first' => 'Paid once for each branch that is added', 'branch_renewal' => 'Every year after the first, for each branch'] as $key => $note)
                            <div class="col-12 col-sm-6 col-xl-4">
                                <div class="price-tile">
                                    <label for="p-{{ $key }}">{{ __(\App\Services\Billing::LABELS[$key]) }}</label>
                                    <small>{{ __($note) }}</small>
                                    <div class="input-group"><span class="input-group-text">$</span><input type="text" inputmode="decimal" class="form-control @error($key) is-invalid @enderror" id="p-{{ $key }}" name="{{ $key }}" value="{{ old($key, rtrim(rtrim($prices[$key], '0'), '.')) }}" dir="ltr" required></div>
                                </div>
                            </div>
                        @endforeach
                        @foreach (['starter' => 'Starter', 'pro' => 'Pro'] as $plan => $planName)
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="pn-{{ $plan }}">{{ __('What the :plan plan includes (shown on the website)', ['plan' => $planName]) }}</label>
                                <textarea class="form-control" id="pn-{{ $plan }}" name="plan_{{ $plan }}_notes" rows="4" dir="auto" placeholder="{{ __('One point on a line') }}">{{ old('plan_'.$plan.'_notes', $planNotes[$plan]) }}</textarea>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- the free trial of the website, where a business pays, and the help videos --}}
        <div class="col-12 col-xl-4">
            <div class="card mb-0 h-100">
                <div class="card-header"><div class="set-head"><span class="set-icon"><x-icon name="user-plus" /></span><div><h2 class="h5">{{ __('Free trial') }}</h2><small>{{ __('A visitor of the website opens his own business and tries the system') }}</small></div></div></div>
                <div class="card-body">
                    <input type="hidden" name="trial_enabled" value="0">
                    <label class="switch-row form-check form-switch mb-3" for="t-on">
                        <input class="form-check-input" type="checkbox" role="switch" id="t-on" name="trial_enabled" value="1" @checked(old('trial_enabled', $trial['enabled']))>
                        <span>{{ __('Open a business by itself when someone registers') }}</span>
                    </label>
                    <label class="form-label" for="t-days">{{ __('Days of the trial') }}</label>
                    <input type="text" inputmode="numeric" dir="ltr" autocomplete="off" class="form-control @error('trial_days') is-invalid @enderror" id="t-days" name="trial_days" value="{{ old('trial_days', $trial['days']) }}" style="max-width:120px">
                    <div class="form-text">{{ __('While it is off, a request waits for the administrator as before. After the trial the business is locked like any licence that ended, until it pays.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card mb-0 h-100">
                <div class="card-header"><div class="set-head"><span class="set-icon"><x-icon name="wallet" /></span><div><h2 class="h5">HesabPay</h2><small>{{ __('Where a business pays its subscription') }}</small></div></div></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6"><label class="form-label" for="h-number">{{ __('HesabPay number') }}</label><input type="text" dir="ltr" class="form-control" id="h-number" name="hesabpay_number" value="{{ old('hesabpay_number', $hesabpay['number']) }}"></div>
                        <div class="col-6"><label class="form-label" for="h-name">{{ __('Name on the account') }}</label><input type="text" class="form-control" id="h-name" name="hesabpay_name" value="{{ old('hesabpay_name', $hesabpay['name']) }}"></div>
                        <div class="col-6"><label class="form-label" for="h-rate">{{ __('Afghani for one dollar') }}</label><input type="text" inputmode="decimal" dir="ltr" class="form-control @error('afn_rate') is-invalid @enderror" id="h-rate" name="afn_rate" value="{{ old('afn_rate', $hesabpay['afn_rate']) }}"></div>
                        <div class="col-6"><label class="form-label" for="h-qr">{{ __('QR code (picture)') }}</label><input type="file" class="form-control @error('hesabpay_qr') is-invalid @enderror" id="h-qr" name="hesabpay_qr" accept="image/png,image/jpeg,image/webp"></div>
                    </div>
                    <div class="form-text">{{ __('Shown to a business on its licence page, with a form to send the transaction number and a picture of the payment.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card mb-0 h-100">
                <div class="card-header"><div class="set-head"><span class="set-icon"><x-icon name="comments" /></span><div><h2 class="h5">{{ __('Assistant') }}</h2><small>{{ __('Answers the questions of Pro businesses from their own figures') }}</small></div></div></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label" for="as-key">{{ __('Key of the Claude API') }} @if ($assistant['on'])<span class="badge bg-success">{{ __('On') }}</span>@else<span class="badge bg-secondary">{{ __('Off') }}</span>@endif</label><input type="password" dir="ltr" class="form-control @error('assistant_key') is-invalid @enderror" id="as-key" name="assistant_key" autocomplete="new-password" placeholder="{{ $assistant['on'] ? '••••••••' : 'sk-ant-...' }}"></div>
                        <div class="col-7"><label class="form-label" for="as-model">{{ __('Model') }}</label><input type="text" dir="ltr" class="form-control" id="as-model" name="assistant_model" value="{{ old('assistant_model', $assistant['model']) }}"></div>
                        <div class="col-5"><label class="form-label" for="as-limit">{{ __('Questions a day') }}</label><input type="number" min="1" max="1000" dir="ltr" class="form-control" id="as-limit" name="assistant_daily_limit" value="{{ old('assistant_daily_limit', $assistant['limit']) }}"></div>
                        @if ($assistant['on'])<div class="col-12"><div class="form-check"><input type="checkbox" class="form-check-input" id="as-off" name="assistant_off" value="1"><label class="form-check-label" for="as-off">{{ __('Remove the key and switch the assistant off') }}</label></div></div>@endif
                    </div>
                    <div class="form-text">{{ __('The key is made at console.anthropic.com and is paid for by the company. Leave the box empty to keep the key that is saved. The limit is for each business.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card mb-0 h-100">
                <div class="card-header"><div class="set-head"><span class="set-icon"><x-icon name="circle-question" /></span><div><h2 class="h5">{{ __('Help videos') }}</h2><small>{{ __('Shown on the help page of every business') }}</small></div></div></div>
                <div class="card-body">
                    <textarea class="form-control @error('help_videos') is-invalid @enderror" name="help_videos" rows="6" dir="ltr" placeholder="Money in and money out | https://youtu.be/...">{{ old('help_videos', $videos) }}</textarea>
                    <div class="form-text">{{ __('One video on a line: the title, a | sign, and the address of the video.') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="save-bar"><span class="text-muted small">{{ __('Changes apply to every business from the next invoice.') }}</span><button class="btn btn-primary" type="submit"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button></div>
</form>
@endsection
