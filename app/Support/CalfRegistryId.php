<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class CalfRegistryId
{
    public static function next(?string $breed, ?string $herdCode, string $birthDate): string
    {
        $sequence = self::sequence($breed, $herdCode, $birthDate);
        abort_if($sequence['next_id'] === null, 422, 'This herd and year has reached calf series 999.');

        return $sequence['next_id'];
    }

    public static function sequence(?string $breed, ?string $herdCode, string $birthDate): array
    {
        $series = CalfBreedSeries::forBreed($breed);
        $herdCode = strtoupper(trim($herdCode ?? ''));
        abort_if($series === null, 422, 'Select a breed with a breed series to generate a Calf ID.');
        abort_unless(preg_match('/^[A-Z0-9]+$/', $herdCode), 422, 'The cooperative needs an alphanumeric herd code to generate a Calf ID.');

        $herdAndYear = $herdCode . date('y', strtotime($birthDate));
        $breedSeries = array_unique(CalfBreedSeries::BY_BREED);
        $pattern = '/^[' . implode('', $breedSeries) . ']' . $herdAndYear . '([0-9]{3})$/i';
        $latest = 0;
        foreach (['carabaos' => ['animal_id', 'registry_number'], 'calves' => ['calf_animal_id']] as $table => $columns) {
            foreach ($columns as $column) {
                $ids = DB::table($table)->where(function ($query) use ($column, $herdAndYear, $breedSeries) {
                    foreach ($breedSeries as $breedPrefix) {
                        $query->orWhere($column, 'like', $breedPrefix . $herdAndYear . '%');
                    }
                })->pluck($column);
                foreach ($ids as $id) {
                    if (preg_match($pattern, $id, $matches)) {
                        $latest = max($latest, (int) $matches[1]);
                    }
                }
            }
        }

        return [
            'last_series' => $latest > 0 ? sprintf('%03d', $latest) : null,
            'next_id' => $latest < 999 ? $series . $herdAndYear . sprintf('%03d', $latest + 1) : null,
        ];
    }
}
