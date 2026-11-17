<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class FhprLactation
{
    public static function start(object $record): ?Carbon
    {
        $source = json_decode((string) ($record->source_data ?? ''), true) ?: [];
        if (! empty($source['fhpr_lactation_start_date'])) {
            return Carbon::parse($source['fhpr_lactation_start_date'], 'Asia/Singapore')->startOfDay();
        }
        $days = $record->days_in_milk ?? null;
        if (! is_numeric($days) || $days < 0) return null;

        // Preserve the worksheet's start for an already completed imported lactation.
        $originalDryOff = $source['Dry Off Date'] ?? $source['dry_off_date'] ?? null;
        $end = Carbon::today('Asia/Singapore');
        if ($originalDryOff !== null && trim((string) $originalDryOff) !== '') {
            try {
                $end = is_numeric($originalDryOff)
                    ? Carbon::instance(Date::excelToDateTimeObject((float) $originalDryOff))->setTimezone('Asia/Singapore')
                    : Carbon::parse($originalDryOff, 'Asia/Singapore');
            } catch (\Throwable) {}
        }

        return $end->startOfDay()->subDays((int) $days);
    }

    public static function days(Carbon $start, ?string $dryOffDate): int
    {
        $end = $dryOffDate ? Carbon::parse($dryOffDate, 'Asia/Singapore')->startOfDay() : Carbon::today('Asia/Singapore');
        return (int) $start->diffInDays($end, false);
    }
}
