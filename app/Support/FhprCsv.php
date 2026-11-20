<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class FhprCsv
{
    private const DATES = [
        'date_of_birth', 'previous_calving_date', 'present_calving_date', 'dry_off_date',
        'first_service_date', 'first_ai_date', 'bull_in_date', 'next_heat_date',
        'second_heat_cycle_date', 'expected_pd_date',
    ];

    private const COUNTS = ['days_in_milk', 'days_to_first_service', 'days_open', 'gestation_length_days', 'parity'];
    private const TEXT = ['farmer_name', 'animal_type', 'tag_number', 'mating_type', 'bull_name_or_id', 'action_taken'];

    private static function rules(): array
    {
        return [
            'animal_identifier' => ['required', 'string', 'max:255'],
            'cooperative_lookup' => ['nullable', 'string', 'max:255'],
            'cooperative_id' => ['nullable', 'integer', 'min:1'],
            'body_condition_score' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'calving_interval_months' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'remarks' => ['nullable', 'string'],
        ] + array_fill_keys(self::DATES, ['nullable', 'date'])
          + array_fill_keys(self::COUNTS, ['nullable', 'integer', 'min:0', 'max:65535'])
          + array_fill_keys(self::TEXT, ['nullable', 'string', 'max:255']);
    }

    public static function headers(): array
    {
        $fields = array_diff(array_keys(self::rules()), ['animal_identifier', 'cooperative_lookup', 'cooperative_id']);
        return ['Animal ID', 'Cooperative', ...array_map(fn ($field) => ucwords(str_replace('_', ' ', $field)), $fields)];
    }

    public static function import(UploadedFile $file): int
    {
        $stream = fopen($file->getRealPath(), 'r');
        try {
            do { $header = fgetcsv($stream, 0, ',', '"', ''); } while ($header === [null]);
            $columns = array_map(self::columnName(...), $header ?: []);
            if (! in_array('animal_identifier', $columns, true)) {
                throw ValidationException::withMessages(['file' => 'The CSV needs an Animal ID column.']);
            }
            $rules = self::rules();
            $recognized = array_intersect($columns, array_keys($rules));
            if (count($recognized) !== count(array_unique($recognized))) {
                throw ValidationException::withMessages(['file' => 'The CSV contains duplicate FHPR columns.']);
            }

            return DB::transaction(function () use ($stream, $columns, $rules) {
                $imported = 0;
                for ($line = 2; ($row = fgetcsv($stream, 0, ',', '"', '')) !== false; $line++) {
                    if ($row === [null]) continue;
                    if (count($row) !== count($columns)) self::invalidRow($line, 'The number of values does not match the header.');
                    $values = array_map(static function ($value) {
                        $value = trim((string) $value);
                        return in_array($value, ['', '-', '—'], true) ? null : $value;
                    }, $row);
                    if (array_filter($values, fn ($value) => $value !== null) === []) continue;
                    $validator = Validator::make(array_combine($columns, $values), $rules, [], ['animal_identifier' => 'Animal ID']);
                    if ($validator->fails()) self::invalidRow($line, $validator->errors()->first());

                    $data = $validator->validated();
                    $animal = self::findAnimal($data, $line);
                    unset($data['animal_identifier'], $data['cooperative_lookup'], $data['cooperative_id']);
                    $data = array_filter($data, fn ($value) => $value !== null);
                    foreach (array_intersect(self::DATES, array_keys($data)) as $field) {
                        $data[$field] = CarbonImmutable::parse($data[$field])->toDateString();
                    }
                    self::saveRecord($animal, $data);
                    $imported++;
                }
                return $imported;
            });
        } finally {
            fclose($stream);
        }
    }

    private static function columnName(?string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', trim($header ?? ''));
        $field = preg_replace('/[^a-z0-9]+/', '_', strtolower($header));
        return match (trim($field, '_')) {
            'animal_id', 'registry_number', 'registry_id', 'animal_registry_id' => 'animal_identifier',
            'cooperative', 'cooperative_name', 'cooperative_code', 'herd_code' => 'cooperative_lookup',
            default => trim($field, '_'),
        };
    }

    private static function findAnimal(array $data, int $line): object
    {
        $identifier = $data['animal_identifier'];
        $matches = DB::table('carabaos as animal')
            ->leftJoin('farmers as farmer', 'farmer.id', '=', 'animal.farmer_id')
            ->leftJoin('cooperatives as cooperative', fn ($join) => $join->on('cooperative.id', '=', DB::raw('COALESCE(animal.cooperative_id, farmer.cooperative_id)')))
            ->where(fn ($animals) => $animals->where('animal.animal_id', $identifier)->orWhere('animal.registry_number', $identifier))
            ->when($data['cooperative_id'] ?? null, fn ($animals, $id) => $animals->where('cooperative.id', $id))
            ->when($data['cooperative_lookup'] ?? null, fn ($animals, $name) => $animals->where(fn ($matching) =>
                $matching->where('cooperative.cooperative_name', $name)->orWhere('cooperative.cooperative_code', $name)))
            ->select('animal.*', 'cooperative.id as linked_cooperative_id')
            ->selectRaw("TRIM(CONCAT_WS(' ', NULLIF(farmer.first_name, ''), NULLIF(farmer.middle_name, ''), NULLIF(farmer.last_name, ''))) as linked_farmer_name")
            ->limit(2)->get();
        if ($matches->isEmpty()) self::invalidRow($line, "Animal ID $identifier was not found in the specified inventory.");
        if ($matches->count() > 1) self::invalidRow($line, "Animal ID $identifier matches more than one animal. Specify the cooperative.");
        $animal = $matches->first();
        if (! in_array(strtoupper(trim((string) $animal->sex)), ['F', 'FEMALE'], true) || trim((string) $animal->classification) === '') {
            self::invalidRow($line, "Animal ID $identifier must be a female animal with an inventory classification.");
        }

        return $animal;
    }

    private static function saveRecord(object $animal, array $data): void
    {
        DB::table('carabaos')->where('id', $animal->id)->lockForUpdate()->first(['id']);
        $registry = $animal->registry_number ?: $animal->animal_id;
        $cooperative = $animal->linked_cooperative_id;
        $existing = DB::table('fhpr_records')->where('cooperative_id', $cooperative)->where('registry_number', $registry)
            ->lockForUpdate()->first(['id'])
            ?? DB::table('fhpr_records')->where('carabao_id', $animal->id)->orderByDesc('id')->lockForUpdate()->first(['id']);
        $data += ['carabao_id' => $animal->id, 'cooperative_id' => $cooperative, 'registry_number' => $registry, 'updated_at' => now()];
        if ($existing) {
            DB::table('fhpr_records')->where('id', $existing->id)->update($data);
            return;
        }
        DB::table('fhpr_records')->insert($data + [
            'farmer_name' => $animal->linked_farmer_name ?: null,
            'tag_number' => $animal->tag_number,
            'date_of_birth' => $animal->birth_date,
            'created_at' => now(),
        ]);
    }

    private static function invalidRow(int $line, string $message): never
    {
        throw ValidationException::withMessages(['file' => "Row $line. $message"]);
    }
}
