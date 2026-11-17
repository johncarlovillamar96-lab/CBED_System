<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FhprClassification
{
    public static function resolve(?string $saved, mixed $daysInMilk, ?string $dryOffDate, ?string $pdResult): ?string
    {
        if (trim((string) $saved) === '') return null;
        $key = preg_replace('/[\s_-]+/', '', strtolower($saved));
        $result = strtolower(trim((string) $pdResult));
        $positive = in_array($result, ['+', 'p', 'positive', 'pregnant', 'yes'], true);
        $negative = in_array($result, ['-', 'n', 'negative', 'not pregnant', 'non-pregnant', 'no'], true);
        $pregnant = $positive || (! $negative && str_ends_with($key, 'pregnant'));

        if ($dryOffDate) {
            $base = 'CowDry';
        } elseif (in_array($key, ['heifer', 'heiferpregnant'], true)) {
            $base = 'Heifer';
        } elseif (is_numeric($daysInMilk) && $daysInMilk >= 0) {
            $base = 'CowLactating';
        } elseif ($positive) {
            $base = 'Heifer';
        } else {
            $base = preg_replace('/Pregnant$/i', '', str_replace(' ', '', $saved));
            if (! in_array($base, ['Heifer', 'CowDry', 'CowLactating'], true)) return $saved;
        }

        return $base.($pregnant ? 'Pregnant' : '');
    }

    public static function latestPdResult(int $animalId): ?string
    {
        return DB::table('mating_records')->where('carabao_id', $animalId)
            ->whereNotNull('pregnancy_result')->whereRaw("TRIM(pregnancy_result) <> ''")
            ->orderByDesc('pregnancy_check_date')->orderByDesc('mating_date')->orderByDesc('id')
            ->value('pregnancy_result');
    }

    public static function syncAnimal(int $animalId): void
    {
        if (! Schema::hasTable('fhpr_records')) return;
        DB::transaction(function () use ($animalId) {
            $animal = DB::table('carabaos')->where('id', $animalId)->lockForUpdate()->first();
            $records = DB::table('fhpr_records')->where('carabao_id', $animalId)->orderByDesc('id')->lockForUpdate()->get();
            if (! $animal || $records->isEmpty()) return;
            if (isset($animal->sex) && ! in_array(strtoupper(trim($animal->sex)), ['F', 'FEMALE'], true)) return;
            $result = self::latestPdResult($animalId);
            $excluded = InventoryClassification::isExcluded($animal->status, $animal->nature_of_exit ?? null);
            $classification = null;
            foreach ($records as $index => $record) {
                $value = $excluded ? null : self::resolve($animal->classification, $record->days_in_milk ?? null, $record->dry_off_date ?? null, $result);
                if ($index === 0) $classification = $value;
                if ($record->status_updated !== $value) {
                    DB::table('fhpr_records')->where('id', $record->id)->update(['status_updated' => $value, 'updated_at' => now()]);
                }
            }
            if ($animal->classification !== $classification) {
                DB::table('carabaos')->where('id', $animalId)->update(['classification' => $classification, 'updated_at' => now()]);
            }
        });
    }
}
