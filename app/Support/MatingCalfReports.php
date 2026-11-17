<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class MatingCalfReports
{
    public static function annotate(Collection $records, Collection $calves, ?Collection $inventoryCalves = null): Collection
    {
        $birthsByDam = $calves->groupBy('dam_id');
        $positiveByDam = $records->filter(fn ($record) => self::positive($record))->groupBy('carabao_id');
        $birthsByMotherId = ($inventoryCalves ?? collect())
            ->filter(fn ($calf) => ! in_array(strtolower(trim((string) ($calf->mother_id ?? ''))), ['', '-', '--', 'n/a', 'na', 'none', 'unknown', '0'], true))
            ->groupBy(fn ($calf) => strtolower(trim((string) $calf->mother_id)));
        $today = Carbon::today(config('app.timezone', 'UTC'));

        return $records->map(function ($record) use ($birthsByDam, $positiveByDam, $birthsByMotherId, $today) {
            $record->calf_drop_reported = false;
            $record->calf_drop_report_date = null;
            $record->pregnancy_followup_complete = false;
            if (! self::positive($record) || ! $record->carabao_id) return $record;
            $start = self::start($record);
            if (! $start) return $record;

            $days = (int) Carbon::parse($start)->startOfDay()->diffInDays($today, false);

            // A calf from a later confirmed pregnancy must not close this service's report.
            $next = $positiveByDam->get($record->carabao_id, collect())
                ->map(fn ($service) => self::start($service))
                ->filter(fn ($date) => $date && $date > $start)->sort()->first();
            $inventoryBirths = collect([$record->animal_registry_id ?? null, $record->registry_number ?? null])
                ->filter()->unique()->flatMap(fn ($id) => $birthsByMotherId->get(strtolower(trim((string) $id)), collect()));
            $birth = $birthsByDam->get($record->carabao_id, collect())->concat($inventoryBirths)
                ->filter(fn ($calf) => $calf->birth_date && $calf->birth_date > $start
                    && (! $next || $calf->birth_date < $next))
                ->sortBy('birth_date')->first();
            if ($birth) {
                $record->calf_drop_reported = true;
                $record->calf_drop_report_date = $birth->birth_date;
                $record->pregnancy_followup_complete = $days > 300;
            }

            return $record;
        });
    }

    private static function positive(object $record): bool
    {
        return in_array(strtolower(trim((string) $record->pregnancy_result)), ['+', 'p', 'positive', 'pregnant', 'yes'], true);
    }

    private static function start(object $record): ?string
    {
        return $record->mating_date ?: $record->pregnancy_check_date;
    }
}
