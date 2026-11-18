<?php

namespace App\Http\Controllers;

use App\Services\ExportCenter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The Export Center: one Excel workbook of a period, with a sheet for every module that is ticked. */
class ExportController extends Controller
{
    public function index(): View
    {
        Gate::authorize('reports.view');

        return view('reports.export', ['modules' => ExportCenter::MODULES, 'from' => now()->startOfYear()->toDateString(), 'to' => today_iso()]);
    }

    public function download(Request $request): Response
    {
        Gate::authorize('reports.view');
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => [Rule::in(array_keys(ExportCenter::MODULES))],
        ]);
        audit('exported', null, 'Export Center '.$data['from'].' – '.$data['to'].' ('.implode(', ', $data['modules']).')');
        $name = (Str::slug((string) (setting('company_name') ?: tenant()?->name)) ?: 'kasib').'-'.$data['from'].'-'.$data['to'].'.xlsx';

        return response(ExportCenter::build($data['from'], $data['to'], $data['modules']), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }
}
