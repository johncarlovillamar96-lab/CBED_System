<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryBolusApplication
{
    public static function numbers(array $services): array
    {
        return collect($services)
            ->filter(fn ($service) => is_array($service) && HealthWorksheetMapping::isBolusService((string) ($service['type'] ?? '')))
            ->map(fn ($service) => trim((string) ($service['bolus_number'] ?? '')))
            ->filter(fn ($number) => $number !== '')
            ->unique(fn ($number) => mb_strtolower($number))->values()->all();
    }

    public static function sync(int $animalId, array $services, array $previousServices = []): void
    {
        $numbers = self::numbers($services);
        if ($numbers === []) {
            return;
        }
        if (count($numbers) > 1) {
            throw ValidationException::withMessages(['services' => 'Enter only one Bolus Number per animal activity.']);
        }
        if ($numbers === self::numbers($previousServices)) {
            return;
        }

        $number = $numbers[0];
        $animals = DB::table('carabaos');
        $animal = (clone $animals)->where('id', $animalId)->lockForUpdate()->first(['bolus_id']);
        if ($animal?->bolus_id === $number) {
            return;
        }
        $otherAnimal = (clone $animals)->where('id', '<>', $animalId)
            ->whereRaw('LOWER(TRIM(bolus_id)) = ?', [mb_strtolower($number)])->lockForUpdate()->first(['id']);
        if ($otherAnimal) {
            throw ValidationException::withMessages(['services' => 'This Bolus Number is already assigned to another animal in Carabao Inventory.']);
        }
        $animals->where('id', $animalId)->update(['bolus_id' => $number, 'updated_at' => now()]);
    }
}
