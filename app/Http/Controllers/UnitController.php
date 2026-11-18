<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Units: what items are counted in. The business adds its own and removes those it does not use. */
class UnitController extends CrudController
{
    protected string $model = Unit::class;

    protected string $route = 'units';

    protected string $permission = 'transactions';

    protected string $title = 'Units';

    protected string $singular = 'Unit';

    protected string $icon = 'ruler';

    protected array $search = ['name', 'name_local'];

    private ?string $was = null;

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:30', Rule::unique('units', 'name')->ignore($record?->id)], 'help' => 'Short, as it is printed on an invoice: kg, carton, pcs ...'],
            ['name' => 'name_local', 'label' => 'Name in Dari or Pashto', 'rules' => ['nullable', 'string', 'max:60']],
        ];
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (Unit $u) => $u->name,
            'Name in Dari or Pashto' => fn (Unit $u) => $u->name_local,
            'Items' => fn (Unit $u) => (string) Item::where('unit', $u->name)->count(),
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        $this->was = $record?->name;

        return $data;
    }

    /** A unit that is renamed is renamed on its items too. */
    protected function afterSave(Model $record, array $virtual, Request $request, bool $created): void
    {
        if (! $created && $this->was !== null && $this->was !== $record->name) {
            Item::where('unit', $this->was)->update(['unit' => $record->name]);
        }
    }

    protected function deleteProblem(Model $record): ?string
    {
        $items = Item::where('unit', $record->name)->count();

        return $items ? __(':count items are counted in this unit. Give them another unit first.', ['count' => $items]) : null;
    }
}
