<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class InventoryMissingFields
{
    private const FIELDS = [
        'animal' => 'animal_name', 'tag' => 'tag_number', 'bolus' => 'bolus_id',
        'sex' => 'sex', 'breed' => 'breed', 'birthday' => 'birth_date',
        'sire_id' => 'sire_id', 'sire_name' => 'sire_name', 'dam_id' => 'dam_id', 'dam_name' => 'dam_name',
        'birth_place' => 'birth_place', 'breeder' => 'breeder',
        'association_coop_institution' => 'association_coop_institution',
        'barangay' => 'barangay', 'municipality' => 'municipality', 'province' => 'province',
        'district' => 'district', 'region' => 'region',
        'date_of_acquisition' => 'acquisition_date', 'acquisition_cost' => 'acquisition_cost',
        'nature_of_exit' => 'nature_of_exit', 'exit_reason' => 'exit_reason', 'death_date' => 'death_date',
        'entrustment' => 'entrustment', 'ownership_status' => 'ownership_status',
        'disposed_to' => 'disposed_to', 'place_of_disposal' => 'place_of_disposal',
        'value_of_disposed_animals' => 'value_of_disposed_animals', 'remarks' => 'remarks',
    ];

    public function sync(array $records, int $cooperativeId, bool $apply = false): array
    {
        return DB::transaction(function () use ($records, $cooperativeId, $apply) {
            $animals = DB::table('carabaos')->where('cooperative_id', $cooperativeId)->lockForUpdate()->get();
            $farmers = DB::table('farmers')->where('cooperative_id', $cooperativeId)->get()->keyBy('id');
            $normalize = fn ($value) => trim(preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower(trim((string) $value))));
            $plan = [];
            $conflicts = [];
            $matched = 0;
            $skipped = 0;
            foreach ($records as $record) {
                $identity = null;
                foreach (['registry' => 'registry_number', 'tag' => 'tag_number', 'bolus' => 'bolus_id'] as $source => $column) {
                    if (trim((string) ($record[$source] ?? '')) !== '') {
                        $identity = [$column, $normalize($record[$source])];
                        break;
                    }
                }
                if ($identity === null) {
                    if (trim((string) ($record['farmer'] ?? '')).trim((string) ($record['animal'] ?? '')) !== '') $skipped++;
                    continue;
                }
                $candidates = $animals->filter(fn ($animal) => $normalize($animal->{$identity[0]}) === $identity[1]);
                $owner = $normalize($record['farmer'] ?? '');
                if ($owner !== '') {
                    $candidates = $candidates->filter(function ($animal) use ($farmers, $normalize, $owner) {
                        $farmer = $farmers->get($animal->farmer_id);
                        if (! $farmer) return false;
                        $given = trim(($farmer->first_name ?? '').' '.($farmer->middle_name ?? ''));
                        return in_array($owner, array_map($normalize, [$given.' '.$farmer->last_name, $farmer->last_name.' '.$given, $farmer->last_name]), true);
                    });
                }
                if ($candidates->count() > 1) {
                    $out = mb_strtolower(trim((string) ($record['inventory_status'] ?? ''))) === 'out';
                    $status = $out ? (str_contains(mb_strtolower((string) ($record['exit_reason'] ?? '')), 'dead') ? 'Dead' : 'Out') : 'Live';
                    $candidates = $candidates->where('status', $status);
                }
                if ($candidates->count() !== 1) { $skipped++; continue; }
                $matched++;
                $animal = $candidates->first();
                foreach (self::FIELDS as $source => $column) {
                    if ($animal->{$column} !== null && trim((string) $animal->{$column}) !== '') continue;
                    $value = $this->value($record[$source] ?? null, $column);
                    if ($value === null || isset($conflicts[$animal->id][$column])) continue;
                    if (isset($plan[$animal->id][$column]) && $plan[$animal->id][$column] !== $value) {
                        unset($plan[$animal->id][$column]);
                        $conflicts[$animal->id][$column] = true;
                    } else {
                        $plan[$animal->id][$column] = $value;
                    }
                }
            }
            $plan = array_filter($plan);
            $fields = [];
            foreach ($plan as $values) foreach ($values as $column => $value) $fields[$column] = ($fields[$column] ?? 0) + 1;
            $backup = null;
            if ($apply && $plan !== []) {
                $directory = storage_path('app/private/backups');
                if (! is_dir($directory)) mkdir($directory, 0700, true);
                $backup = $directory.'/inventory-missing-fields-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
                $json = json_encode(['cooperative_id' => $cooperativeId, 'before' => $animals->whereIn('id', array_keys($plan))->values(), 'updates' => $plan], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
                if (file_put_contents($backup, $json, LOCK_EX) !== strlen($json)) throw new \RuntimeException('Backup could not be saved.');
                foreach ($plan as $id => $values) DB::table('carabaos')->where('id', $id)->update($values + ['updated_at' => now()]);
            }

            return ['applied' => $apply, 'matched_rows' => $matched, 'skipped_rows' => $skipped,
                'animals_to_update' => count($plan), 'fields' => $fields, 'conflicts' => $conflicts, 'backup' => $backup];
        });
    }

    private function value(mixed $value, string $column): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        if (in_array($column, ['acquisition_cost', 'value_of_disposed_animals'], true)) {
            $value = str_replace(',', '', $value);
            return is_numeric($value) ? number_format((float) $value, 2, '.', '') : null;
        }
        if (in_array($column, ['birth_date', 'acquisition_date', 'death_date'], true)) {
            try {
                return is_numeric($value) ? Date::excelToDateTimeObject((float) $value)->format('Y-m-d') : Carbon::parse($value)->format('Y-m-d');
            } catch (\Throwable) { return null; }
        }

        return $value;
    }
}
