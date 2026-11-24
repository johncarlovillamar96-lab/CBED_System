<?php

namespace App\Support;

final class CalfInventoryDetails
{
    public static function displayMissingFields(object $animal, ?object $cooperative): void
    {
        $herdCode = trim((string) $cooperative?->cooperative_code);
        $year = substr((string) $animal->birth_date, 2, 2);
        $series = implode('', array_unique(CalfBreedSeries::BY_BREED));
        if ($herdCode === '' || strlen($year) !== 2
            || in_array(strtolower(trim((string) $animal->dam_id)), ['', '-', '--', 'n/a', 'na', 'none', 'unknown', '0'], true)
            || ! preg_match('/^['.$series.']'.preg_quote($herdCode.$year, '/').'\d{3}$/iD', (string) $animal->animal_id)) {
            return;
        }

        foreach (self::forBirth($animal->animal_id, $cooperative, $animal, []) as $field => $value) {
            if (blank($animal->{$field} ?? null)) {
                $animal->{$field} = $value;
            }
        }
    }

    public static function forBirth(string $calfId, ?object $cooperative, object $dam, array $data): array
    {
        $place = array_filter(array_map(
            fn ($part) => trim((string) $part),
            [$cooperative?->barangay, $cooperative?->municipality, $cooperative?->province],
        ), fn ($part) => $part !== '');

        if ($place === []) {
            $place = array_filter(array_map(
                fn ($field) => trim((string) ($data[$field] ?? $dam->{$field} ?? '')),
                ['barangay', 'municipality', 'province'],
            ), fn ($part) => $part !== '');
        }

        $name = trim((string) $cooperative?->cooperative_name);

        return [
            'registry_number' => $calfId,
            'birth_place' => $data['birth_place'] ?? (implode(', ', array_unique($place)) ?: null),
            'breeder' => $name !== '' ? $name : ($data['breeder'] ?? null),
            'association_coop_institution' => $name !== '' ? $name : ($data['association_coop_institution'] ?? null),
        ];
    }
}
