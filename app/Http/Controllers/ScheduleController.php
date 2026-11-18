<?php

namespace App\Http\Controllers;

use App\Models\ReportSchedule;
use App\Services\Schedules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Scheduled reports: the Excel workbook of the Export Center, sent by email every day, week or month. */
class ScheduleController extends CrudController
{
    protected string $model = ReportSchedule::class;

    protected string $route = 'schedules';

    protected string $permission = 'reports';

    protected string $title = 'Scheduled Reports';

    protected string $singular = 'Scheduled report';

    protected string $icon = 'clock';

    protected array $search = ['name', 'email'];

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:150']],
            ['name' => 'email', 'label' => 'Send to (email)', 'type' => 'email', 'rules' => ['required', 'email', 'max:190']],
            ['name' => 'content', 'label' => 'What it holds', 'type' => 'select', 'options' => array_map(fn ($c) => __($c[0]), ReportSchedule::CONTENTS), 'rules' => ['required', Rule::in(array_keys(ReportSchedule::CONTENTS))]],
            ['name' => 'frequency', 'label' => 'How often', 'type' => 'select', 'options' => array_map(fn ($f) => __($f), ReportSchedule::FREQUENCIES), 'rules' => ['required', Rule::in(array_keys(ReportSchedule::FREQUENCIES))],
                'help' => 'Every day: that day. Every week: the last seven days. Every month: the last month.'],
            ['name' => 'day', 'label' => 'Day', 'type' => 'number', 'default' => 1, 'rules' => ['required', 'integer', 'min:1', 'max:28'],
                'help' => 'Every week: 1 is Saturday, 7 is Friday. Every month: the day of the month, 1 to 28. Every day: not used.'],
            ['name' => 'hour', 'label' => 'At the hour', 'type' => 'number', 'default' => 20, 'rules' => ['required', 'integer', 'min:0', 'max:23']],
            ['name' => 'is_active', 'label' => 'In use', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (ReportSchedule $s) => $s->name,
            'Send to (email)' => fn (ReportSchedule $s) => $s->email,
            'What it holds' => fn (ReportSchedule $s) => __(ReportSchedule::CONTENTS[$s->content][0] ?? $s->content),
            'How often' => fn (ReportSchedule $s) => __(ReportSchedule::FREQUENCIES[$s->frequency] ?? $s->frequency),
            'At the hour' => fn (ReportSchedule $s) => $s->hour,
            'Last sent on' => fn (ReportSchedule $s) => fmt_date($s->last_sent_on),
            'In use' => fn (ReportSchedule $s) => $s->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        return $data + ($record ? [] : ['created_by' => $request->user()->id]);
    }

    protected function rowLinks(Model $record): array
    {
        return auth()->user()->can('reports.manage') ? [['label' => __('Send now'), 'url' => route('schedules.send', ['id' => $record->id]), 'post' => true]] : [];
    }

    public function send(Request $request): RedirectResponse
    {
        Gate::authorize('reports.manage');
        $schedule = ReportSchedule::findOrFail((int) $request->route('id'));
        audit('exported', null, 'Scheduled report '.$schedule->name.' → '.$schedule->email);

        return Schedules::send($schedule) ? back()->with('success', __('The report was sent to :email.', ['email' => $schedule->email]))
            : back()->with('error', __('The email could not be sent. Check the mail settings of the server.'));
    }
}
