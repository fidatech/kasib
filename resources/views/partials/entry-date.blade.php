{{--
    The date of the paper being recorded: this kind of paper is always recorded on today's date,
    so the date is shown and cannot be changed.
--}}
<div class="{{ $col ?? 'col-6 col-md-3' }}">
    <label class="form-label">{{ __('Date') }}</label>
    <div class="input-group" title="{{ __('This paper is recorded on today\'s date.') }}">
        <span class="input-group-text"><x-icon name="calendar-days" /></span>
        <input type="text" class="form-control" value="{{ fmt_date(today_iso()) }}" dir="ltr" readonly tabindex="-1" aria-label="{{ __('Date') }}">
    </div>
</div>
