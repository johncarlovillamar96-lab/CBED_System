<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class MonitoringReportTable
{
    public static function latestCalves(Collection $calfEvents, Collection $inventory, array $animalLookup, array $rowKeysById, string $asOf): array
    {
        $latest = [];
        foreach ($calfEvents as $event) {
            $details = (array) $event->details;
            $rowKey = is_object($event->details)
                ? ($rowKeysById[$details['dam_id'] ?? ''] ?? null)
                : ($animalLookup[mb_strtolower(trim((string) $event->animal_id))]['row_key'] ?? null);
            $date = $event->date;
            if (! $rowKey || ! $date || $date > $asOf || $date < ($latest[$rowKey]['date'] ?? '')
                || strcasecmp($event->birth_outcome ?? $details['birth_outcome'] ?? '', 'Abortion') === 0) {
                continue;
            }
            $latest[$rowKey] = [
                'date' => $date, 'sex' => $details['sex'] ?? null,
                'method' => $details['mating_method'] ?? $details['nm_ai'] ?? $details['breeding_method'] ?? null,
                'sire_id' => $event->sire_id ?? $details['sire_id'] ?? null,
            ];
        }
        foreach ($inventory as $calf) {
            $dam = mb_strtolower(trim((string) $calf->dam_id));
            if (in_array($dam, ['', '-', '--', 'n/a', 'na', 'none', 'unknown', '0'], true)) {
                continue;
            }
            $rowKey = $animalLookup[$dam]['row_key'] ?? null;
            $date = $calf->birth_date;
            if ($rowKey && $date && $date <= $asOf && $date > ($latest[$rowKey]['date'] ?? '')) {
                $latest[$rowKey] = ['date' => $date, 'sex' => $calf->sex, 'method' => null, 'sire_id' => $calf->sire_id];
            }
        }

        return $latest;
    }

    public static function calfMethod(array $calf, array $breedingHistory): ?string
    {
        if (filled($calf['method'])) {
            return $calf['method'];
        }
        $birth = Carbon::parse($calf['date']);
        $methods = collect($breedingHistory)->filter(function ($record) use ($birth, $calf) {
            if (filled($calf['sire_id']) && strcasecmp((string) $record['sire_id'], (string) $calf['sire_id']) !== 0) {
                return false;
            }
            $days = Carbon::parse($record['date'])->diffInDays($birth, false);

            return $days >= 240 && $days <= 330;
        })->map(fn ($record) => self::breedingMethod($record['method']))->filter()->unique();

        return $methods->count() === 1 ? $methods->first() : null;
    }

    public static function breedingMethod(?string $method): ?string
    {
        return match (strtolower(trim((string) $method))) {
            'ai', 'artificial insemination' => 'AI',
            'n', 'nm', 'natural', 'natural mating' => 'NM',
            default => filled($method) ? $method : null,
        };
    }

    public static function services(array $row): array
    {
        $health = [];
        $other = [];
        foreach ($row['_visit_services'][$row['monitoring_date'] ?? ''] ?? [] as $entry) {
            $name = $entry['name'] ?? '';
            $key = match (true) {
                preg_match('/ear.?tag/i', $name) === 1 => 'ear_tagging',
                preg_match('/bolus/i', $name) === 1 => 'bolus',
                preg_match('/fl[ua]sh/i', $name) === 1 => 'flashing',
                default => null,
            };
            if ($key) {
                $value = $entry['value'] ?? null;
                $display = blank($value) || strcasecmp(trim((string) $value), 'Done') === 0
                    || ($key === 'flashing' && $value === $name) ? true : $value;
                if (! isset($other[$key]) || ($entry['date'] ?? '') >= $other[$key]['date']) {
                    $other[$key] = ['value' => $display, 'date' => $entry['date'] ?? ''];
                }
            } elseif ($name !== '' && ($entry['category'] ?? 'health_nutrition') === 'health_nutrition') {
                $health[] = [
                    'value' => $name.(filled($entry['dosage'] ?? null) ? ' ('.$entry['dosage'].' mL)' : ''),
                    'date' => $entry['date'] ?? null,
                ];
            }
        }

        return [$health, $other];
    }
}
