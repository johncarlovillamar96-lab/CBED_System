<?php

namespace App\Support;

class InventoryClassification
{
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
