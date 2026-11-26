<?php

namespace App\Http\Controllers;

use App\Support\TablePagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MtdController extends Controller
{
    private const TEATS = ['right_front', 'right_back', 'left_front', 'left_back'];

    public const TEST_RESULTS = ['Negative', 'Trace', '+1', '+2', '+3'];

    private const READINGS = ['bulk_kg', 'bulk_result', ...self::TEATS];

    private const CSV_COLUMNS = ['cooperative_id', 'farmer_id', 'carabao_id', 'test_date', 'collection_period', ...self::READINGS, 'remarks', 'milk_yield_liters'];

    private const LEGACY_CSV_COLUMNS = ['cooperative_id', 'farmer_id', 'carabao_id', 'test_date', 'collection_period', 'milk_yield_liters', ...self::TEATS, 'remarks'];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'], 'cooperative_id' => ['nullable', 'integer', 'min:1'],
            'period' => ['nullable', 'in:AM,PM'], 'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $columns = ['cooperative_name', 'farmer_name', 'animal_identifier', 'collection_period', ...self::READINGS, 'test_date', 'remarks', 'actions'];
        $owner = "CONCAT_WS(' ', farmer.first_name, NULLIF(farmer.middle_name, ''), farmer.last_name)";
        $animalId = "COALESCE(NULLIF(animal.animal_id, ''), NULLIF(mtd.registry_number, ''), NULLIF(animal.registry_number, ''))";
        $query = DB::table('mtd_records as mtd')
            ->leftJoin('carabaos as animal', 'animal.id', '=', 'mtd.carabao_id')
            ->leftJoin('farmers as farmer', 'farmer.id', '=', DB::raw('COALESCE(mtd.farmer_id, animal.farmer_id)'))
            ->leftJoin('cooperatives as cooperative', 'cooperative.id', '=', DB::raw('COALESCE(mtd.cooperative_id, animal.cooperative_id, farmer.cooperative_id)'));
        if (isset($filters['cooperative_id'])) $query->where('cooperative.id', $filters['cooperative_id']);
        if (isset($filters['period'])) $query->where('mtd.collection_period', $filters['period']);
        if (isset($filters['date'])) $query->where('mtd.test_date', $filters['date']);
        foreach (preg_split('/\s+/u', $filters['search'] ?? '', -1, PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
            $pattern = '%'.$term.'%';
            $query->where(fn ($builder) => $builder->where('cooperative.cooperative_name', 'like', $pattern)
                ->orWhereRaw("$owner LIKE ?", [$pattern])->orWhereRaw("$animalId LIKE ?", [$pattern])
                ->orWhere('animal.tag_number', 'like', $pattern)->orWhere('mtd.remarks', 'like', $pattern));
        }
        $mtdSummary = (clone $query)->selectRaw('COUNT(*) as records, COUNT(DISTINCT mtd.carabao_id) as animals, SUM(mtd.bulk_kg) as bulk')->first();
        $rows = $query->select('mtd.*', 'cooperative.cooperative_name')->selectRaw("$owner as farmer_name, $animalId as animal_identifier")
            ->orderByDesc('mtd.test_date')->orderByDesc('mtd.id')->paginate(TablePagination::size($request))->withQueryString();
        $rows->through($this->withTeatResults(...));
        $mtdCooperatives = DB::table('cooperatives')->orderBy('cooperative_name')->get(['id', 'cooperative_name']);
        $mtdFarmers = DB::table('farmers')->whereNotNull('cooperative_id')->orderBy('last_name')->orderBy('first_name')
            ->select('id', 'cooperative_id')->selectRaw("CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), last_name) as name")->get();
        $mtdAnimals = DB::table('carabaos')->whereNotNull('farmer_id')
            ->whereRaw("LOWER(TRIM(sex)) IN ('f', 'female')")
            ->orderBy('registry_number')->orderBy('animal_id')
            ->get(['id', 'farmer_id', 'animal_id', 'registry_number', 'tag_number', 'animal_name', 'mtd_missing_teats'])
            ->each(fn ($animal) => $animal->mtd_missing_teats = $this->decodeMissingTeats($animal->mtd_missing_teats));

        return view('modules.index', compact('columns', 'rows', 'filters', 'mtdSummary', 'mtdCooperatives', 'mtdFarmers', 'mtdAnimals') + ['module' => 'mtd', 'moduleTitle' => 'Milk Test Day']);
    }

    public function store(Request $request)
    {
        $rules = ['cooperative_id' => ['required', 'integer'], 'farmer_id' => ['required', 'integer'], 'rows' => ['required', 'array', 'min:1', 'max:100']];
        foreach ($this->readingRules() as $field => $rule) $rules['rows.*.'.$field] = $rule;
        $rules['rows.*.carabao_id'][] = 'distinct';
        $rules['rows.*.teat_availability_set'] = ['sometimes', 'boolean'];
        $rules['rows.*.missing_teats'] = ['sometimes', 'array', 'max:4'];
        $rules['rows.*.missing_teats.*'] = [\Illuminate\Validation\Rule::in(self::TEATS)];
        $data = $request->validateWithBag('mtdEntry', $rules, [
            'rows.*.carabao_id.distinct' => 'Select each animal only once per batch.',
        ]);
        $rowFields = $this->readingRules() + ['teat_availability_set' => true, 'missing_teats' => true];
        $records = array_map(fn ($row) => array_intersect_key($row, $rowFields) + ['cooperative_id' => $data['cooperative_id'], 'farmer_id' => $data['farmer_id']], $data['rows']);
        $count = $this->saveRecords($records, 'mtdEntry', false);

        return redirect()->route('ui.mtd')->with('success', "Saved {$count} MTD record(s).");
    }

    public function update(Request $request, int $record)
    {
        $rules = $this->readingRules();
        unset($rules['carabao_id']);
        $rules['collection_period'] = ['nullable', 'in:AM,PM'];
        $data = $request->validate($rules);

        DB::transaction(function () use ($record, $data) {
            $query = DB::table('mtd_records')->where('id', $record);
            $existing = $query->first();
            abort_unless($existing, 404, 'MTD record was not found.');
            if ($existing->cooperative_id) DB::table('cooperatives')->where('id', $existing->cooperative_id)->lockForUpdate()->first();
            $existing = $query->lockForUpdate()->first();
            abort_unless($existing, 404, 'MTD record was not found.');

            $period = $data['collection_period'] ?? null;
            if ($existing->collection_period && $period === null) {
                throw ValidationException::withMessages(['collection_period' => 'Choose AM or PM.']);
            }
            if ($existing->carabao_id && $period && DB::table('mtd_records')->where('id', '!=', $record)
                ->where('carabao_id', $existing->carabao_id)->where('test_date', $data['test_date'])->where('collection_period', $period)->exists()) {
                throw ValidationException::withMessages(['test_date' => 'An entry already exists for this animal, date, and period.']);
            }

            $missing = $this->decodeMissingTeats($existing->missing_teats);
            $fields = $data;
            foreach (self::TEATS as $teat) {
                if (in_array($teat, $missing, true) && ! empty($data[$teat])) {
                    throw ValidationException::withMessages([$teat => 'This teat was marked missing on this report.']);
                }
                if (array_key_exists($teat, $data)) $fields[$teat.'_result'] = $data[$teat];
                unset($fields[$teat]);
            }
            $query->update($fields + ['updated_at' => now()]);
        });

        session()->flash('success', 'MTD record updated.');
        return response()->noContent();
    }

    public function destroy(int $record)
    {
        abort_unless(DB::table('mtd_records')->where('id', $record)->delete(), 404, 'MTD record was not found.');

        session()->flash('success', 'MTD record deleted.');
        return response()->noContent();
    }

    private function readingRules(): array
    {
        return [
            'carabao_id' => ['required', 'integer'], 'test_date' => ['required', 'date_format:Y-m-d'],
            'collection_period' => ['required', 'in:AM,PM'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'bulk_kg' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ] + array_fill_keys(['bulk_result', ...self::TEATS], ['nullable', \Illuminate\Validation\Rule::in(self::TEST_RESULTS)]);
    }

    private function withTeatResults(object $row): object
    {
        foreach (self::TEATS as $teat) $row->$teat = $row->{$teat.'_result'} ?? $row->$teat;
        $row->missing_teats = $this->decodeMissingTeats($row->missing_teats);

        return $row;
    }

    private function decodeMissingTeats(?string $value): array
    {
        $decoded = json_decode($value ?? '[]', true);

        return array_values(array_intersect(self::TEATS, is_array($decoded) ? $decoded : []));
    }

    private function saveRecords(array $records, string $bag, bool $skipDuplicates): int
    {
        return DB::transaction(function () use ($records, $bag, $skipDuplicates) {
            DB::table('cooperatives')->whereIn('id', array_column($records, 'cooperative_id'))->orderBy('id')->lockForUpdate()->pluck('id');
            $saved = 0;
            foreach ($records as $index => $record) {
                $animal = DB::table('carabaos as animal')->join('farmers as farmer', 'farmer.id', '=', 'animal.farmer_id')
                    ->where('animal.id', $record['carabao_id'])->where('farmer.id', $record['farmer_id'])
                    ->where('farmer.cooperative_id', $record['cooperative_id'])->lockForUpdate()
                    ->first(['animal.registry_number', 'animal.animal_id', 'animal.mtd_missing_teats', 'animal.sex']);
                $availabilitySet = (bool) ($record['teat_availability_set'] ?? false);
                $missing = $availabilitySet
                    ? array_values(array_intersect(self::TEATS, $record['missing_teats'] ?? []))
                    : $this->decodeMissingTeats($animal->mtd_missing_teats ?? null);
                unset($record['teat_availability_set'], $record['missing_teats']);
                $issue = null;
                if (! $animal) $issue = 'Select an animal belonging to the selected farmer and cooperative.';
                elseif ($bag === 'mtdEntry' && ! in_array(strtolower(trim((string) $animal->sex)), ['f', 'female'], true)) {
                    $issue = 'Select a female animal for Milk Test Day.';
                }
                elseif (! array_filter(array_intersect_key($record, array_flip([...self::READINGS, 'milk_yield_liters'])), fn ($value) => $value !== null && $value !== '')) {
                    $issue = 'Enter at least one Bulk or teat reading.';
                }
                foreach ($missing as $teat) {
                    if (isset($record[$teat]) && $record[$teat] !== '') $issue ??= 'Remove the result for any teat marked missing.';
                }
                $exists = DB::table('mtd_records')->where('carabao_id', $record['carabao_id'])->where('test_date', $record['test_date'])
                    ->where('collection_period', $record['collection_period'])->exists();
                if ($issue === null && $exists && $skipDuplicates) continue;
                if ($issue || $exists) {
                    $message = $issue ?? 'An entry already exists for this animal, date, and AM / PM period.';
                    throw ValidationException::withMessages([$skipDuplicates ? 'file' : 'rows' => 'Entry '.($index + 1).': '.$message])->errorBag($bag);
                }
                foreach (self::TEATS as $teat) {
                    $record[$teat.'_result'] = $record[$teat] ?? null;
                    unset($record[$teat]);
                }
                $record['missing_teats'] = json_encode($missing);
                DB::table('mtd_records')->insert($record + ['registry_number' => $animal->registry_number ?: $animal->animal_id, 'created_at' => now(), 'updated_at' => now()]);
                if ($availabilitySet && $missing !== $this->decodeMissingTeats($animal->mtd_missing_teats)) {
                    DB::table('carabaos')->where('id', $record['carabao_id'])->update(['mtd_missing_teats' => $record['missing_teats']]);
                }
                $saved++;
            }
            return $saved;
        });
    }

    public function download(bool $template)
    {
        return response()->streamDownload(function () use ($template) {
            $file = fopen('php://output', 'w');
            fputcsv($file, self::CSV_COLUMNS);
            if (! $template) {
                DB::table('mtd_records as mtd')->leftJoin('carabaos as animal', 'animal.id', '=', 'mtd.carabao_id')
                    ->select('mtd.*')->selectRaw('COALESCE(mtd.farmer_id, animal.farmer_id) as export_farmer_id')
                    ->orderBy('mtd.id')->chunk(500, function ($rows) use ($file) {
                        foreach ($rows as $row) {
                            $this->withTeatResults($row);
                            $row->farmer_id = $row->export_farmer_id;
                            fputcsv($file, array_map(fn ($field) => $row->$field, self::CSV_COLUMNS));
                        }
                    });
            }
            fclose($file);
        }, $template ? 'mtd_template.csv' : 'mtd_export.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);
        $file = fopen($request->file('file')->getRealPath(), 'r');
        $records = [];
        try {
            do { $header = fgetcsv($file, 0, ',', '"', ''); } while ($header === [null]);
            if ($header) $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            if ($header !== self::CSV_COLUMNS && $header !== self::LEGACY_CSV_COLUMNS) throw ValidationException::withMessages(['file' => 'Use the MTD CSV template.']);
            $rules = ['cooperative_id' => ['required', 'integer'], 'farmer_id' => ['required', 'integer'],
                'milk_yield_liters' => ['nullable', 'numeric', 'min:0', 'max:999999.99']] + $this->readingRules();
            while (($row = fgetcsv($file, 0, ',', '"', '')) !== false) {
                if ($row === [null]) continue;
                if (count($row) !== count($header)) throw ValidationException::withMessages(['file' => 'A CSV row has the wrong number of columns.']);
                $values = array_map(fn ($value) => trim((string) $value) === '' ? null : trim((string) $value), $row);
                $validator = Validator::make(array_combine($header, $values), $rules);
                if ($validator->fails()) throw ValidationException::withMessages(['file' => 'Entry '.(count($records) + 1).': '.$validator->errors()->first()]);
                $records[] = $validator->validated();
            }
        } finally {
            fclose($file);
        }
        if (! $records) throw ValidationException::withMessages(['file' => 'The CSV contains no MTD records.']);
        $count = $this->saveRecords($records, 'default', true);

        return redirect()->route('ui.mtd')->with('success', "Imported {$count} MTD record(s).");
    }
}
