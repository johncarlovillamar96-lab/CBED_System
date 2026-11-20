<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class CalfBreedSeries
{
    public const BY_BREED = [
        'RV' => '2',
        'ITMB' => '2',
        'ITBM' => '2',
        'BMB' => '2',
        'BRB' => '2',
        'BRMB' => '2',
        'SP' => '2',
        'CB50' => '5',
        'CB75' => '6',
        'CB87' => '7',
        'CB93' => '8',
    ];

    public static function forBreed(?string $breed): ?string
    {
        $code = strtoupper(trim($breed ?? ''));

        return match (true) {
            isset(self::BY_BREED[$code]) => self::BY_BREED[$code],
            in_array($code, self::CB50_DAM_CODES, true) => '5',
            in_array($code, self::SERIES_TWO_DAM_CODES, true) => '2',
            default => null,
        };
    }

    public const CB50_DAM_CODES = ['CB50', '5-CB50', '5'];
    public const SERIES_TWO_DAM_CODES = ['2', '2-ITMB', '2-ITBM'];
    public const CALF_BREED_BY_SIRE_SERIES_FOR_SERIES_TWO_DAM = [
        '5' => 'CB75',
        '6' => 'CB87',
        '7' => 'CB93',
        '8' => 'RV',
    ];

    public static function isVerifiedSeriesTwoSire(?string $breed): bool
    {
        $code = strtoupper(trim($breed ?? ''));

        return self::forBreed($code) === '2' || in_array($code, ['2', 'CARABAO', ''], true);
    }

    public static function resolveParentage(?string $damBreed, ?string $sireId, ?string $breed = null): array
    {
        $breed ??= $damBreed;
        $damBreed = strtoupper(trim((string) $damBreed));
        $sireCode = strtoupper(trim((string) $sireId));
        abort_if(preg_match('/^[01349]/', $sireCode), 422, 'A numeric sire ID must start with 2, 5, 6, 7, or 8.');
        $sireSeries = preg_match('/^[25678]/', $sireCode) ? $sireCode[0] : null;
        $cb50Dam = in_array($damBreed, self::CB50_DAM_CODES, true);
        $seriesTwoDam = self::forBreed($damBreed) === '2';
        if ($sireCode === 'UNKNOWN' && $seriesTwoDam) {
            $breed = 'CB75';
        } elseif ($cb50Dam || $seriesTwoDam) {
            $cb50SireCode = in_array($sireCode, self::CB50_DAM_CODES, true);
            $bull = $cb50SireCode || in_array($sireCode, ['', 'UNKNOWN'], true)
                ? null
                : DB::table('semen_bulls')->whereRaw('UPPER(TRIM(bull_id)) = ?', [$sireCode])->first(['bull_id', 'breed']);
            if ($cb50Dam) {
                $seriesTwo = $sireSeries !== null ? $sireSeries === '2' : ($sireCode === 'RV'
                    || ($bull !== null && self::isVerifiedSeriesTwoSire($bull->breed)));
                $breed = $seriesTwo ? 'CB75' : 'CB50';
                $sireId = $bull->bull_id ?? ($seriesTwo || ($sireSeries !== null && ! $cb50SireCode) ? trim($sireId) : 'CB50');
            } else {
                $resolvedSireSeries = $sireSeries ?? ($cb50SireCode ? '5' : self::forBreed($bull->breed ?? null));
                $crossedBreed = self::CALF_BREED_BY_SIRE_SERIES_FOR_SERIES_TWO_DAM[$resolvedSireSeries] ?? null;
                if ($crossedBreed !== null) {
                    $breed = $crossedBreed;
                    $sireId = $bull->bull_id ?? ($cb50SireCode ? 'CB50' : trim($sireId));
                }
            }
        }
        if ($sireCode === 'UNKNOWN') {
            $sireId = 'UNKNOWN';
        }
        return [$breed, $sireId];
    }

    public static function semenBulls(): array
    {
        return DB::table('semen_bulls')->whereNotNull('bull_id')->get(['bull_id', 'breed'])
            ->map(fn ($bull) => [
                'id' => $bull->bull_id,
                'series_two' => self::isVerifiedSeriesTwoSire($bull->breed),
                'breed_series' => self::forBreed($bull->breed),
            ])->all();
    }
}
