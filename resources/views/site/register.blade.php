@extends('layouts.site', ['title' => __('Register your business')])

@section('content')
<section class="site-section">
    <div class="site-wrap site-narrow">
        <h1 class="site-title">{{ __('Register your business') }}</h1>
        <p class="site-lead">{{ __('Fill in the form. Our team checks the request; once it is approved, your system is opened at your own address.') }}</p>

        <form method="POST" action="{{ route('site.register.store') }}" class="card">
            @csrf
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">{{ __('Please correct the boxes marked below.') }}</div>
                @endif
                <div class="site-trap" aria-hidden="true"><label for="website">Website</label><input type="text" id="website" name="website" tabindex="-1" autocomplete="off"></div>

                <h2 class="site-form-head">{{ __('The business') }}</h2>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="name">{{ __('Name of the business') }} <span class="req">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" maxlength="150" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="name_local">{{ __('Name in Dari or Pashto') }}</label>
                        <input type="text" class="form-control" id="name_local" name="name_local" value="{{ old('name_local') }}" maxlength="150" dir="rtl">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="slug">{{ __('Address you want') }} <span class="req">*</span></label>
                        <div class="input-group" dir="ltr">
                            <input type="text" class="form-control @error('slug') is-invalid @enderror" id="slug" name="slug" value="{{ old('slug') }}" maxlength="40" required autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="kabul" aria-describedby="slugHelp">
                            <span class="input-group-text">.{{ config('tenancy.base_domain') }}</span>
                        </div>
                        @error('slug')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text" id="slugHelp">{{ __('Small English letters, digits and dashes. Your staff open the system at this address.') }}</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="licence_no">{{ __('DAB licence number') }}</label>
                        <input type="text" class="form-control" id="licence_no" name="licence_no" value="{{ old('licence_no') }}" maxlength="60" dir="ltr">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="province">{{ __('Province') }}</label>
                        <input type="text" class="form-control" id="province" name="province" value="{{ old('province') }}" maxlength="100">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="branches">{{ __('Branches besides the main one') }}</label>
                        <input type="text" inputmode="numeric" dir="ltr" class="form-control @error('branches') is-invalid @enderror" id="branches" name="branches" value="{{ old('branches', 0) }}" autocomplete="off">
                        @error('branches')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <h2 class="site-form-head">{{ __('The owner') }}</h2>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="owner_name">{{ __('Full name') }} <span class="req">*</span></label>
                        <input type="text" class="form-control @error('owner_name') is-invalid @enderror" id="owner_name" name="owner_name" value="{{ old('owner_name') }}" maxlength="150" required autocomplete="name">
                        @error('owner_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="owner_phone">{{ __('Phone') }} <span class="req">*</span></label>
                        <input type="tel" class="form-control @error('owner_phone') is-invalid @enderror" id="owner_phone" name="owner_phone" value="{{ old('owner_phone') }}" maxlength="40" required dir="ltr" autocomplete="tel">
                        @error('owner_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="owner_email">{{ __('Email') }} <span class="req">*</span></label>
                        <input type="email" class="form-control @error('owner_email') is-invalid @enderror" id="owner_email" name="owner_email" value="{{ old('owner_email') }}" maxlength="190" required dir="ltr" autocomplete="email" aria-describedby="emailHelp">
                        @error('owner_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text" id="emailHelp">{{ __('You sign in with this email and the password below.') }}</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="password">{{ __('Password') }} <span class="req">*</span></label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" minlength="8" required autocomplete="new-password">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="password_confirmation">{{ __('Password again') }} <span class="req">*</span></label>
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" minlength="8" required autocomplete="new-password">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="message">{{ __('Anything we should know') }}</label>
                        <textarea class="form-control" id="message" name="message" rows="3" maxlength="1000">{{ old('message') }}</textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex flex-wrap gap-3 justify-content-between align-items-center">
                <span class="small text-muted">{{ __('Main branch :first the first year, then :renewal a year.', ['first' => '$'.($prices['main_first'] + 0), 'renewal' => '$'.($prices['main_renewal'] + 0)]) }}</span>
                <button type="submit" class="btn btn-primary btn-lg">{{ __('Send the request') }}</button>
            </div>
        </form>
    </div>
</section>
@endsection
