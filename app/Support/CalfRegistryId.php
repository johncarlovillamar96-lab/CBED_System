<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class CalfRegistryId
{
    public static function lockSequence(): void
    {
        // Both calf-entry flows acquire the same lock before reading the annual series.
        DB::table('cooperatives')->orderBy('id')->lockForUpdate()->first(['id']);
    }

    public static function next(?string $breed, ?string $herdCode, string $birthDate): string
    {
        $sequence = self::sequence($breed, $herdCode, $birthDate);
        abort_if($sequence['next_id'] === null, 422, 'This year has reached calf series 999.');

        return $sequence['next_id'];
    }

    public static function sequence(?string $breed, ?string $herdCode, string $birthDate): array
    {
        $series = CalfBreedSeries::forBreed($breed);
        $herdCode = strtoupper(trim($herdCode ?? ''));
        abort_if($series === null, 422, 'Select a breed with a breed series to generate a Calf ID.');
        abort_unless(preg_match('/^[A-Z0-9]+$/', $herdCode), 422, 'The cooperative needs an alphanumeric herd code to generate a Calf ID.');

        $year = date('y', strtotime($birthDate));
        $breedSeries = array_unique(CalfBreedSeries::BY_BREED);
        $otherHerdCodes = DB::table('cooperatives')->pluck('cooperative_code')->push($herdCode)
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter(fn ($code) => preg_match('/^[A-Z0-9]+$/', $code) && ! preg_match('/^[A-Z]{3}$/', $code))
            ->unique()->all();
        $herdPattern = implode('|', ['[A-Z]{3}', ...$otherHerdCodes]);
        $pattern = '/^[' . implode('', $breedSeries) . '](?:' . $herdPattern . ')' . $year . '([0-9]{3})$/i';
        $latest = 0;
        $lastId = null;
        foreach (['carabaos' => ['animal_id', 'registry_number'], 'calves' => ['calf_animal_id']] as $table => $columns) {
            foreach ($columns as $column) {
                $rows = DB::table($table)->select($column)->where($column, 'like', '%' . $year . '___')->cursor();
                foreach ($rows as $row) {
                    $id = $row->$column;
                    if (preg_match($pattern, $id, $matches) && (int) $matches[1] > $latest) {
                        $latest = (int) $matches[1];
                        $lastId = $id;
                    }
                }
            }
        }

        return [
            'last_series' => $latest > 0 ? sprintf('%03d', $latest) : null,
            'last_id' => $lastId,
            'next_id' => $latest < 999 ? $series . $herdCode . $year . sprintf('%03d', $latest + 1) : null,
        ];
    }
}
