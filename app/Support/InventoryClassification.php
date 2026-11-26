<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryClassification
{
    public static function ageAdjustedLabel(?string $label, ?string $sex, ?string $birthDate, ?string $status, ?string $exit): ?string
    {
        $status = strtolower(trim((string) $status));
        $exit = strtolower(trim((string) $exit));
        if (!$birthDate || $status === 'dead' || ($exit ?: ($status ?: 'live')) !== 'live') return $label;
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $birthDate, $parts)
            || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) return $label;

        $today = Carbon::today('Asia/Singapore');
        $birthday = Carbon::parse($birthDate, 'Asia/Singapore')->startOfDay();
        if ($birthday->greaterThan($today)) return $label;

        $age = $today->year - $birthday->year - (int) ($today->format('md') < $birthday->format('md'));
        $key = preg_replace('/[\s_-]+/', '', strtolower(trim((string) $label)));
        $sex = strtolower(trim((string) $sex));
        if (in_array($sex, ['f', 'female'], true) && $age < 3 && in_array($key, ['femalecalf', 'yearlingheifer', 'heifer'], true)) {
            return $age < 1 ? 'Female Calf' : ($age < 2 ? 'Yearling Heifer' : 'Heifer');
        }
        if (in_array($sex, ['m', 'male'], true) && in_array($key, ['malecalf', 'yearlingbull', 'juniorbull', 'seniorbull', 'youngbull', 'bull'], true)) {
            return $age < 1 ? 'Male Calf' : ($age < 2 ? 'Yearling Bull' : 'Senior Bull');
        }

        return $label;
    }

    public static function breedingInventoryQuery(): Builder
    {
        $animals = DB::table('carabaos as animal')
            ->select('animal.status', 'animal.sex', 'animal.classification')
            ->addSelect(Schema::hasColumn('carabaos', 'nature_of_exit')
                ? 'animal.nature_of_exit'
                : DB::raw('NULL as nature_of_exit'));

        if (Schema::hasColumn('carabaos', 'exit_reason')) {
            $animals->addSelect('animal.exit_reason');
        }
        if (Schema::hasColumns('fhpr_records', ['carabao_id', 'days_in_milk'])) {
            $animals->addSelect([
                'days_in_milk' => DB::table('fhpr_records')
                    ->select('days_in_milk')
                    ->whereColumn('carabao_id', 'animal.id')
                    ->whereNotNull('days_in_milk')
                    ->orderByDesc('id')
                    ->limit(1),
            ]);
        }

        return $animals;
    }

    public static function isLiveInInventorySummary(object $animal): bool
    {
        $status = strtolower(trim((string) ($animal->status ?? '')));
        $exitReason = trim((string) ($animal->exit_reason ?? ''));

        return $status === 'live' || ($status === '' && $exitReason === '');
    }

    public static function isLiveBreedableFemale(object $animal): bool
    {
        $status = strtolower(trim((string) $animal->status));
        $exit = strtolower(trim((string) $animal->nature_of_exit));
        $sex = strtolower(trim((string) $animal->sex));
        if ($status === 'dead' || ($exit ?: ($status ?: 'live')) !== 'live' || ! in_array($sex, ['f', 'female'], true)) {
            return false;
        }

        $classification = preg_replace('/[\s_-]+/', '', strtolower((string) $animal->classification));
        $daysInMilk = (int) ($animal->days_in_milk ?? 0);

        return in_array($classification, ['heifer', 'cowdry'], true)
            || ($classification === 'cowlactating' && $daysInMilk >= 25 && $daysInMilk <= 45);
    }

    public static function isExcluded(?string $status, ?string $natureOfExit = null): bool
    {
        foreach ([$status, $natureOfExit] as $value) {
            $value = strtolower(trim((string) $value));
            if ($value === 'dead' || str_starts_with($value, 'cull') || str_starts_with($value, 'transf')) return true;
        }

        return false;
    }
}
