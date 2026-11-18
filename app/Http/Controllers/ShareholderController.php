<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\CapitalEntry;
use App\Models\Shareholder;
use App\Services\CapitalService;
use App\Support\Num;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** The partners of the licensed company. Kept by people of the main branch. */
class ShareholderController extends CrudController
{
    protected string $model = Shareholder::class;

    protected string $route = 'shareholders';

    protected string $permission = 'capital';

    protected string $title = 'Shareholders';

    protected string $singular = 'Shareholder';

    protected string $icon = 'user-tie';

    protected array $search = ['name', 'name_local', 'phone'];

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Full name', 'rules' => ['required', 'string', 'max:150']],
            ['name' => 'name_local', 'label' => 'Name in Dari or Pashto', 'rules' => ['nullable', 'string', 'max:150']],
            ['name' => 'father_name', 'label' => "Father's name", 'rules' => ['nullable', 'string', 'max:150']],
            ['name' => 'phone', 'label' => 'Phone', 'rules' => ['nullable', 'string', 'max:50']],
            ['name' => 'share_percent', 'label' => 'Share (%)', 'type' => 'decimal', 'default' => '0', 'rules' => ['required', 'numeric', 'min:0', 'max:100'],
                'help' => 'All shares together cannot be more than 100'],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function columns(): array
    {
        return [
            'Full name' => fn (Shareholder $s) => $s->label(),
            "Father's name" => fn (Shareholder $s) => $s->father_name,
            'Share (%)' => fn (Shareholder $s) => qty($s->share_percent),
            'Capital (AFN)' => fn (Shareholder $s) => money(CapitalService::balance($s)),
            'Active' => fn (Shareholder $s) => $s->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        abort_unless(current_branch()?->isMain(), 403);

        $others = Shareholder::where('is_active', true)->when($record, fn ($q) => $q->where('id', '!=', $record->id))->get()->reduce(fn ($sum, Shareholder $s) => Num::add($sum, $s->share_percent), '0');
        if (! empty($data['is_active']) && Num::cmp(Num::add($others, $data['share_percent']), '100') === 1) {
            throw new UserError(__('All shares together cannot be more than 100. The others already have :percent.', ['percent' => qty($others)]));
        }

        return $data;
    }

    protected function deleteProblem(Model $record): ?string
    {
        abort_unless(current_branch()?->isMain(), 403);

        return CapitalEntry::where('shareholder_id', $record->id)->exists() ? __('This shareholder has capital entries. Switch the shareholder off instead.') : null;
    }
}
