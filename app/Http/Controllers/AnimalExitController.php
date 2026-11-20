<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use App\Support\TablePagination;
use App\Support\AnimalExitRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AnimalExitController extends Controller
{
    private const DETAILS = AnimalExitRecord::DETAILS;

    private const CSV_COLUMNS = ['carabao_id', 'exit_date', 'nature_of_exit', ...self::DETAILS, 'remarks'];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'], 'cooperative_id' => ['nullable', 'integer', 'min:1'],
            'nature' => ['nullable', 'string', 'max:255'], 'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $columns = ['cooperative_name', 'farmer_name', 'animal_identifier', 'exit_date', 'nature_of_exit', ...self::DETAILS, 'remarks', 'actions'];
        $query = $this->records();
        $exitTypes = (clone $query)->whereNotNull('nature_of_exit')->where('nature_of_exit', '!=', '')->distinct()->orderBy('nature_of_exit')->pluck('nature_of_exit');
        foreach (['cooperative_id' => 'cooperative_id', 'nature' => 'nature_of_exit', 'date' => 'exit_date'] as $filter => $field) {
            if (isset($filters[$filter])) $query->where($field, $filters[$filter]);
        }
        foreach (preg_split('/\s+/u', $filters['search'] ?? '', -1, PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
            $query->where(function (Builder $builder) use ($term) {
                foreach (['cooperative_name', 'farmer_name', 'animal_identifier', 'nature_of_exit', ...self::DETAILS, 'place_of_disposal', 'remarks'] as $field) {
                    $builder->orWhere($field, 'like', '%'.$term.'%');
                }
            });
        }
        $exitSummary = (clone $query)->selectRaw("COUNT(*) as records,
            SUM(LOWER(TRIM(nature_of_exit)) LIKE 'transfer%') as transfers,
            SUM(LOWER(TRIM(nature_of_exit)) IN ('culled', 'cull', 'culling')) as culled,
            SUM(LOWER(TRIM(nature_of_exit)) IN ('sold', 'sale')) as sold,
            SUM(LOWER(TRIM(nature_of_exit)) IN ('dead', 'death', 'deceased')) as deaths")->first();
        $rows = $query->orderByDesc('exit_date')->orderByDesc('record_id')->orderByDesc('carabao_id')->paginate(TablePagination::size($request))->withQueryString();
        $rows->through($this->withLocation(...));
        $exitCooperatives = DB::table('cooperatives')->orderBy('cooperative_name')->get(['id', 'cooperative_name']);
        $exitFarmers = DB::table('farmers')->whereNotNull('cooperative_id')->orderBy('last_name')->orderBy('first_name')
            ->select('id', 'cooperative_id')->selectRaw("CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), last_name) as name")->get();
        $exitAnimals = DB::table('carabaos as animal')->join('farmers as farmer', 'farmer.id', '=', 'animal.farmer_id')->orderBy('animal.animal_id')
            ->select('animal.id', 'animal.farmer_id', 'animal.animal_name', 'animal.entrustment', 'animal.ownership_status')
            ->selectRaw("COALESCE(animal.cooperative_id, farmer.cooperative_id) as cooperative_id,
                COALESCE(NULLIF(animal.animal_id, ''), NULLIF(animal.registry_number, ''), NULLIF(animal.tag_number, ''), CAST(animal.id AS CHAR)) as animal_number")->get();

        return view('modules.index', compact('columns', 'rows', 'filters', 'exitTypes', 'exitSummary', 'exitCooperatives', 'exitFarmers', 'exitAnimals') + ['module' => 'exits', 'moduleTitle' => 'Animal Exits']);
    }

    private function records(): Builder
    {
        $farmerName = "NULLIF(TRIM(CONCAT_WS(' ', farmer.first_name, NULLIF(farmer.middle_name, ''), farmer.last_name)), '')";
        $animalId = "COALESCE(NULLIF(animal.registry_number, ''), NULLIF(animal.animal_id, ''), NULLIF(animal.tag_number, ''))";
        $history = DB::table('nature_of_exits as history')
            ->leftJoin('carabaos as animal', 'animal.id', '=', 'history.carabao_id')
            ->leftJoin('farmers as farmer', 'farmer.id', '=', 'animal.farmer_id')
            ->leftJoin('cooperatives as cooperative', 'cooperative.id', '=', DB::raw('COALESCE(history.cooperative_id, animal.cooperative_id, farmer.cooperative_id)'))
            ->selectRaw("history.id as record_id, history.carabao_id, cooperative.id as cooperative_id, cooperative.cooperative_name,
                COALESCE(NULLIF(history.farmer_name, ''), $farmerName) as farmer_name,
                COALESCE(NULLIF(history.registry_number, ''), $animalId) as animal_identifier,
                history.exit_date, history.nature_of_exit,
                CASE WHEN history.details_entered THEN history.reason_of_exit ELSE animal.exit_reason END as reason_of_exit,
                CASE WHEN history.details_entered THEN history.entrustment ELSE animal.entrustment END as entrustment,
                CASE WHEN history.details_entered THEN history.ownership_status ELSE animal.ownership_status END as ownership_status,
                CASE WHEN history.details_entered THEN history.disposed_to ELSE animal.disposed_to END as disposed_to,
                history.disposal_province, history.disposal_municipality, history.disposal_barangay,
                CASE WHEN history.details_entered THEN NULL ELSE animal.place_of_disposal END as place_of_disposal,
                history.remarks");
        $inventory = DB::table('carabaos as animal')
            ->leftJoin('farmers as farmer', 'farmer.id', '=', 'animal.farmer_id')
            ->leftJoin('cooperatives as cooperative', 'cooperative.id', '=', DB::raw('COALESCE(animal.cooperative_id, farmer.cooperative_id)'))
            ->where(fn (Builder $query) => $query->whereRaw("NULLIF(TRIM(animal.nature_of_exit), '') IS NOT NULL")->orWhereIn('animal.status', ['Out', 'Dead']))
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')->from('nature_of_exits as existing')->whereColumn('existing.carabao_id', 'animal.id'))
            ->selectRaw("NULL as record_id, animal.id as carabao_id, cooperative.id as cooperative_id, cooperative.cooperative_name,
                $farmerName as farmer_name, $animalId as animal_identifier, animal.death_date as exit_date,
                COALESCE(NULLIF(animal.nature_of_exit, ''), animal.status) as nature_of_exit, animal.exit_reason as reason_of_exit,
                animal.entrustment, animal.ownership_status, animal.disposed_to, NULL as disposal_province, NULL as disposal_municipality,
                NULL as disposal_barangay, animal.place_of_disposal, animal.remarks");

        return DB::query()->fromSub($history->unionAll($inventory), 'exit_records');
    }

    private function withLocation(object $row): object
    {
        $parts = array_map('trim', explode(',', (string) $row->place_of_disposal));
        if (count($parts) === 2 && ! in_array('', $parts, true)) {
            [$row->disposal_municipality, $row->disposal_province] = $parts;
        } elseif (count($parts) === 3 && ! in_array('', $parts, true)) {
            [$row->disposal_barangay, $row->disposal_municipality, $row->disposal_province] = $parts;
        }

        return $row;
    }

    private function rules(): array
    {
        return AnimalExitRecord::rules();
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules() + [
            'carabao_id' => ['required', 'integer'], 'cooperative_id' => ['required', 'integer'], 'farmer_id' => ['required', 'integer'],
        ]);
        if (! $this->saveExit($data)) {
            throw ValidationException::withMessages(['exit_date' => 'An exit of this type is already recorded for this animal on that date.']);
        }

        session()->flash('success', 'Exit record saved.');
        return response()->noContent();
    }

    private function saveExit(array $data): bool
    {
        $animal = DB::table('carabaos as animal')->leftJoin('farmers as farmer', 'farmer.id', '=', 'animal.farmer_id')
            ->where('animal.id', $data['carabao_id'])->select('animal.id', 'animal.registry_number', 'animal.animal_id', 'farmer.id as farmer_id', 'farmer.cooperative_id as farmer_cooperative_id')
            ->selectRaw("COALESCE(animal.cooperative_id, farmer.cooperative_id) as cooperative_id, CONCAT_WS(' ', farmer.first_name, NULLIF(farmer.middle_name, ''), farmer.last_name) as farmer_name")->first();
        if (! $animal || (isset($data['farmer_id']) && ((int) $animal->farmer_id !== (int) $data['farmer_id']
            || (int) $animal->cooperative_id !== (int) $data['cooperative_id'] || (int) $animal->farmer_cooperative_id !== (int) $data['cooperative_id']))) {
            throw ValidationException::withMessages(['carabao_id' => 'Select an animal belonging to the selected cooperative and farmer.']);
        }
        return AnimalExitRecord::save($animal->id, $data, [
            'cooperative_id' => $animal->cooperative_id, 'farmer_name' => $animal->farmer_name ?: null,
            'registry_number' => $animal->registry_number ?: $animal->animal_id,
        ]) !== null;
    }

    public function update(Request $request, int $record)
    {
        $data = $request->validate($this->rules());
        $row = DB::table('nature_of_exits')->where('id', $record)->first(['carabao_id']);
        abort_unless($row, 404, 'Exit record was not found.');
        if (AnimalExitRecord::save($row->carabao_id, $data, [], $record) === null) {
            throw ValidationException::withMessages(['exit_date' => 'An exit of this type is already recorded for this animal on that date.']);
        }
        session()->flash('success', 'Exit record updated.');
        return response()->noContent();
    }

    public function destroy(int $record)
    {
        abort_unless(DB::table('nature_of_exits')->where('id', $record)->delete(), 404, 'Exit record was not found.');
        session()->flash('success', 'Exit record deleted.');
        return response()->noContent();
    }

    public function download(bool $template)
    {
        return response()->streamDownload(function () use ($template) {
            $file = fopen('php://output', 'w');
            fputcsv($file, self::CSV_COLUMNS, ',', '"', '');
            if (! $template) {
                $this->records()->orderBy('carabao_id')->orderBy('record_id')->chunk(500, function ($rows) use ($file) {
                    foreach ($rows as $row) {
                        $this->withLocation($row);
                        fputcsv($file, array_map(fn ($field) => $row->$field, self::CSV_COLUMNS), ',', '"', '');
                    }
                });
            }
            fclose($file);
        }, $template ? 'exits_template.csv' : 'exits_export.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);
        $file = fopen($request->file('file')->getRealPath(), 'r');
        try {
            $count = DB::transaction(function () use ($file) {
                do { $header = fgetcsv($file, 0, ',', '"', ''); } while ($header === [null]);
                if ($header) $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
                if ($header !== self::CSV_COLUMNS) throw ValidationException::withMessages(['file' => 'Use the Exit Records CSV template.']);
                $count = 0;
                for ($line = 2; ($row = fgetcsv($file, 0, ',', '"', '')) !== false; $line++) {
                    if ($row === [null]) continue;
                    if (count($row) !== count($header)) throw ValidationException::withMessages(['file' => "Row {$line} has the wrong number of columns."]);
                    $values = array_map(fn ($value) => trim((string) $value) === '' ? null : trim((string) $value), $row);
                    $validator = Validator::make(array_combine($header, $values), $this->rules() + ['carabao_id' => ['required', 'integer', 'exists:carabaos,id']]);
                    if ($validator->fails()) throw ValidationException::withMessages(['file' => "Row {$line}: ".$validator->errors()->first()]);
                    $count += (int) $this->saveExit($validator->validated());
                }
                return $count;
            });
        } finally {
            fclose($file);
        }
        return redirect()->route('ui.exits')->with('success', "Imported {$count} exit record(s).");
    }
}
