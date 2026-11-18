<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ text_dir() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $name }}</title>
<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f3f5f9;color:#3b4254;font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif}main{max-width:460px;margin:24px;padding:32px;background:#fff;border:1px solid #edf0f5;border-radius:12px;text-align:center}h1{margin:0 0 8px;font-size:22px;color:#232a3a}p{margin:0;line-height:1.6}</style>
</head>
<body>
<main>
    <h1>{{ $name }}</h1>
    @if (! empty($licence))
        <p>{{ __('The licence of this business has ended and its days of grace are over. Nothing is lost: the system opens again as soon as the licence is renewed.') }}</p>
    @else
        <p>{{ __('This business is not active at the moment. Please contact Fida Technologies.') }}</p>
    @endif
    @isset($pay)
        @if ($sent)
            <p style="padding:12px;border-radius:8px;background:#e7f6ec;color:#17603a">{{ __('We have it. The licence moves on as soon as the payment is checked.') }}</p>
        @else
            <div style="text-align:start;margin:18px 0;padding:14px;border:1px solid #dfe3ec;border-radius:10px">
                <p style="margin:0 0 6px"><strong>{{ __('Pay the subscription through HesabPay') }}</strong></p>
                <p style="margin:0 0 10px">{{ $pay['dueFor'] }}: <strong dir="ltr">${{ money($pay['due']) }}</strong>@if ($pay['hesabpay']['number'])<br>{{ __('HesabPay number') }}: <strong dir="ltr">{{ $pay['hesabpay']['number'] }}</strong> {{ $pay['hesabpay']['name'] }}@endif</p>
                @foreach ($problems as $problem)<p style="margin:0 0 6px;color:#b42318">{{ $problem }}</p>@endforeach
                <form method="POST" action="{{ url('/paid') }}" enctype="multipart/form-data">
                    @csrf
                    <p style="margin:0 0 8px"><label>{{ __('Amount paid (US dollars)') }}<br><input name="amount" inputmode="decimal" dir="ltr" value="{{ old('amount', $pay['due']) }}" required style="width:100%;padding:8px;box-sizing:border-box"></label></p>
                    <p style="margin:0 0 8px"><label>{{ __('Transaction number') }}<br><input name="reference" dir="ltr" maxlength="120" required style="width:100%;padding:8px;box-sizing:border-box"></label></p>
                    <p style="margin:0 0 8px"><label>{{ __('Your name') }}<br><input name="sender" maxlength="150" style="width:100%;padding:8px;box-sizing:border-box"></label></p>
                    <p style="margin:0 0 12px"><label>{{ __('Picture of the payment') }}<br><input type="file" name="picture" accept="image/png,image/jpeg,image/webp" required></label></p>
                    <button type="submit" style="padding:10px 18px;border:0;border-radius:8px;background:#2f5bea;color:#fff;font-size:15px;cursor:pointer">{{ __('We paid: send it') }}</button>
                </form>
            </div>
        @endif
    @endisset
    @if (! empty($company))
        <p style="font-size:14px;opacity:.85"><strong>{{ $company['company_name'] }}</strong><br><span dir="ltr">{{ implode(' · ', array_filter([$company['company_phone'], $company['company_email'], $company['company_website']])) }}</span></p>
    @endif
</main>
</body>
</html>
