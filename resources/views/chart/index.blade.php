@extends('layouts.app', ['title' => __('Chart of Accounts')])

@section('content')
<x-page-header :title="__('Chart of Accounts')" icon="address-book" :subtitle="__('Every group fills one line of a Da Afghanistan Bank statement.')">
    <a class="btn btn-outline-soft" href="{{ route('expense-categories.index') }}">{{ __('Expense Categories') }}</a>
    @can('accounts.manage')<a class="btn btn-outline-soft" href="{{ route('account-categories.index') }}">{{ __('Account Categories') }}</a>@endcan
</x-page-header>

@foreach (\App\Models\AccountCategory::NATURES as $nature => $heading)
    @if (! empty($groups[$nature]))
        <div class="card {{ $loop->first ? '' : 'mt-3' }}">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __($heading) }}</h2></div>
            <div class="card-body">
                <table class="table table-hover w-100">
                    <thead><tr><th>{{ __('Group') }}</th><th class="text-end">{{ __('Accounts') }}</th><th class="text-end">{{ __('Balance') }} {{ $base }}</th></tr></thead>
                    <tbody>
                        @foreach ($groups[$nature] as $group)
                            <tr>
                                <td>{{ $group->label() }}@unless ($group->is_system) <span class="text-muted small">· {{ __('Added by the business') }}</span>@endunless</td>
                                <td class="text-end">{{ $counts[$group->id] ?? 0 }}</td>
                                <td class="text-end text-nowrap">{{ money(in_array($nature, ['asset', 'expense'], true) ? ($totals[$group->id] ?? 0) : \App\Support\Num::neg($totals[$group->id] ?? 0)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endforeach
@endsection
