<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class CalfRegistryId
{
    private const SECTION_RANGES = [
        '2026' => [
            ['section' => 'ABGS', 'first' => 34, 'last' => 104],
            ['section' => 'AI', 'first' => 160, 'last' => 360],
            ['section' => 'BEP', 'first' => 361, 'last' => 461],
        ],
    ];

    public static function lockSequence(): void
    {
        // Both calf-entry flows acquire the same lock before reading the annual series.
        DB::table('cooperatives')->orderBy('id')->lockForUpdate()->first(['id']);
    }

    public static function next(?string $breed, ?string $herdCode, string $birthDate, ?int $selectedSeries = null, array $heldSeries = []): string
    {
        $sequence = self::sequence($breed, $herdCode, $birthDate);
        if ($selectedSeries !== null) {
            $number = sprintf('%03d', $selectedSeries);
            abort_unless(in_array($number, $sequence['available_series'], true), 422, 'The selected calf series is no longer available. Check available series and choose another number.');

            return $sequence['id_prefix'] . $number;
        }
        $available = array_diff($sequence['available_series'], $heldSeries);
        abort_if($available === [], 422, 'No calf series numbers are available for this year.');

        return $sequence['id_prefix'] . reset($available);
    }

    public static function sequence(?string $breed, ?string $herdCode, string $birthDate): array
    {
        $series = CalfBreedSeries::forBreed($breed);
        $herdCode = strtoupper(trim($herdCode ?? ''));
        abort_if($series === null, 422, 'Select a breed with a breed series to generate a Calf ID.');
        abort_unless(preg_match('/^[A-Z0-9]+$/', $herdCode), 422, 'The cooperative needs an alphanumeric herd code to generate a Calf ID.');

        $birthYear = date('Y', strtotime($birthDate));
        $year = substr($birthYear, -2);
        $sectionRanges = self::SECTION_RANGES[$birthYear] ?? [];
        $breedSeries = array_unique(CalfBreedSeries::BY_BREED);
        $otherHerdCodes = DB::table('cooperatives')->pluck('cooperative_code')->push($herdCode)
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter(fn ($code) => preg_match('/^[A-Z0-9]+$/', $code) && ! preg_match('/^[A-Z]{3}$/', $code))
            ->unique()->all();
        $herdPattern = implode('|', ['[A-Z]{3}', ...$otherHerdCodes]);
        $pattern = '/^[' . implode('', $breedSeries) . '](?:' . $herdPattern . ')' . $year . '([0-9]{3})$/i';
        $latest = 0;
        $lastId = null;
        $usedNumbers = [];
        foreach (['carabaos' => ['animal_id', 'registry_number'], 'calves' => ['calf_animal_id']] as $table => $columns) {
            foreach ($columns as $column) {
                $rows = DB::table($table)->select($column)->where($column, 'like', '%' . $year . '___')->cursor();
                foreach ($rows as $row) {
                    $id = $row->$column;
                    if (! preg_match($pattern, $id, $matches)) {
                        continue;
                    }
                    $number = (int) $matches[1];
                    $usedNumbers[$number] = true;
                    if ($number > $latest) {
                        $latest = $number;
                        $lastId = $id;
                    }
                }
            }
        }

        $unavailable = $usedNumbers;
        foreach ($sectionRanges as $range) {
            foreach (range($range['first'], $range['last']) as $number) {
                $unavailable[$number] = true;
            }
        }
        $available = array_map(
            fn ($number) => sprintf('%03d', $number),
            array_values(array_diff(range(1, 999), array_keys($unavailable))),
        );
        $prefix = $series . $herdCode . $year;

        return [
            'last_series' => $latest > 0 ? sprintf('%03d', $latest) : null,
            'last_id' => $lastId,
            'next_id' => $available ? $prefix . $available[0] : null,
            'id_prefix' => $prefix,
            'available_series' => $available,
            'reserved_ranges' => array_map(fn ($range) => [
                'section' => $range['section'],
                'first' => $year . sprintf('%03d', $range['first']),
                'last' => $year . sprintf('%03d', $range['last']),
            ], $sectionRanges),
        ];
    }
}
