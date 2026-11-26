<?php

namespace App\Support;

use Carbon\Carbon;

class AnimalClassification
{
    public static function classify(array $signals): array
    {
        if (in_array(self::normalize((string) ($signals['sex'] ?? '')), ['m', 'male'], true)) {
            $saved = self::normalize((string) ($signals['inventory_classification'] ?? ''));

            return [
                'classification' => match ($saved) {
                    'malecalf' => 'Male Calf',
                    'youngbull' => 'Young Bull',
                    'bull' => 'Bull',
                    default => 'Male',
                },
                'pregnancy' => 'Not Applicable',
                'last_calving_date' => null,
                'dry_off_date' => null,
                'dim' => null,
                'dry_off_review' => false,
            ];
        }

        $asOf = Carbon::parse($signals['as_of'] ?? now()->toDateString())->startOfDay();
        $birthDate = self::validDate($signals['birth_date'] ?? null);
        $calvingDate = self::validDate($signals['last_calving_date'] ?? null);
        $dryOffDate = self::validDate($signals['dry_off_date'] ?? null);
        $pregnancyDate = self::validDate($signals['pregnancy_date'] ?? null);
        $saved = self::normalize($signals['inventory_classification'] ?? '');

        $pregnancyPredatesCalving = $calvingDate && (! $pregnancyDate || $pregnancyDate->lessThan($calvingDate));
        $pregnancy = $pregnancyPredatesCalving
            ? 'Unknown / Not Checked'
            : self::pregnancy($signals['pregnancy_result'] ?? null, $saved);
        $pregnant = $pregnancy === 'Pregnant';

        $savedBase = match ($saved) {
            'heifer', 'heiferpregnant' => 'Heifer',
            'cowdry', 'cowdrypregnant' => 'CowDry',
            'cowlactating', 'cowlactatingpregnant' => 'CowLactating',
            'femalecalf' => 'Female Calf',
            'yearlingheifer' => 'Yearling Heifer',
            default => null,
        };

        if ($calvingDate) {
            $isDry = $dryOffDate && $dryOffDate->greaterThanOrEqualTo($calvingDate);
            $base = $isDry ? 'CowDry' : 'CowLactating';
            $dim = $isDry ? null : max(0, $calvingDate->diffInDays($asOf, false));
        } elseif ($savedBase && in_array($savedBase, ['CowDry', 'CowLactating'], true)) {
            $isDry = $savedBase === 'CowDry' || $dryOffDate !== null;
            $base = $isDry ? 'CowDry' : 'CowLactating';
            $dim = null;
        } elseif ($savedBase) {
            $base = $savedBase;
            $dim = null;
        } elseif ($birthDate) {
            $age = $birthDate->diffInYears($asOf);
            $base = $age < 1 ? 'Female Calf' : ($age < 2 ? 'Yearling Heifer' : 'Heifer');
            $dim = null;
        } else {
            $base = match ($saved) {
                'heifer', 'heiferpregnant' => 'Heifer',
                'cowdry', 'cowdrypregnant' => 'CowDry',
                'cowlactating', 'cowlactatingpregnant' => 'CowLactating',
                'femalecalf' => 'Female Calf',
                default => 'Unknown / Needs Verification',
            };
            $dim = null;
        }

        if ($pregnant && in_array($base, ['Heifer', 'CowDry', 'CowLactating'], true)) {
            $base .= 'Pregnant';
        }

        $threshold = max(1, (int) ($signals['dryoff_review_dim'] ?? 280));

        return [
            'classification' => $base,
            'pregnancy' => $pregnancy,
            'last_calving_date' => $calvingDate?->toDateString(),
            'dry_off_date' => $dryOffDate?->toDateString(),
            'dim' => $dim,
            'dry_off_review' => $dim !== null && $dim >= $threshold,
        ];
    }

    private static function pregnancy(mixed $result, string $saved): string
    {
        $result = self::normalize((string) ($result ?? ''));
        if (in_array($result, ['positive', 'pregnant'], true)) return 'Pregnant';
        if (in_array($result, ['negative', 'notpregnant'], true)) return 'Not Pregnant';
        if ($result === 'inconclusive') return 'Inconclusive';
        if (str_contains($saved, 'pregnant')) return 'Pregnant';

        return 'Unknown / Not Checked';
    }

    private static function normalize(string $value): string
    {
        return preg_replace('/[\s_-]+/', '', mb_strtolower(trim($value))) ?? '';
    }

    private static function validDate(mixed $value): ?Carbon
    {
        if (! $value) return null;
        try {
            $date = Carbon::parse($value)->startOfDay();
            return $date->isFuture() ? null : $date;
        } catch (\Throwable) {
            return null;
        }
    }
}
