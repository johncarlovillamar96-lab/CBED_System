<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OverdueCalfNotifications
{
    public static function annotate(Collection $records): Collection
    {
        $latest = $records->filter(fn ($r) => trim((string) $r->pregnancy_result) !== '')
            ->sortByDesc(fn ($r) => sprintf('%s|%s|%012d', $r->pregnancy_check_date ?? '', $r->mating_date ?? '', $r->id))
            ->unique('carabao_id')->pluck('id', 'carabao_id');
        $today = Carbon::today('Asia/Singapore');
        return $records->map(function ($record) use ($latest, $today) {
            $positive = in_array(strtolower(trim((string) $record->pregnancy_result)), ['+', 'p', 'positive', 'pregnant', 'yes'], true);
            $record->pregnancy_days = $positive && $record->mating_date
                ? max(0, (int) Carbon::parse($record->mating_date, 'Asia/Singapore')->startOfDay()->diffInDays($today, false)) : 0;
            $record->calf_drop_overdue = $latest->get($record->carabao_id) === $record->id
                && $record->pregnancy_days > 300 && ! $record->calf_drop_reported
                && strtolower(trim((string) ($record->inventory_status ?? 'Live'))) === 'live'
                && ! InventoryClassification::isExcluded($record->inventory_status ?? null, $record->inventory_exit ?? null);
            return $record;
        });
    }

    public static function groups(): Collection
    {
        foreach (['mating_records', 'carabaos', 'calves', 'farmers', 'cooperatives'] as $table) {
            if (! Schema::hasTable($table)) return collect();
        }
        $records = DB::table('mating_records as mating')
            ->join('carabaos as animal', 'animal.id', '=', 'mating.carabao_id')
            ->leftJoin('farmers as farmer', 'farmer.id', '=', 'animal.farmer_id')
            ->leftJoin('cooperatives as cooperative', 'cooperative.id', '=', DB::raw('COALESCE(mating.cooperative_id, animal.cooperative_id, farmer.cooperative_id)'))
            ->select('mating.*', 'animal.animal_id as animal_registry_id', 'animal.status as inventory_status', 'animal.nature_of_exit as inventory_exit', 'cooperative.cooperative_name')
            ->selectRaw('COALESCE(mating.cooperative_id, animal.cooperative_id, farmer.cooperative_id) as cooperative_id')->get();
        $records = MatingCalfReports::annotate($records,
            DB::table('calves')->whereIn('dam_id', $records->pluck('carabao_id'))->get(['dam_id', 'birth_date']),
            DB::table('carabaos')->whereNotNull('dam_id')->get(['dam_id as mother_id', 'birth_date']));
        return self::annotate($records)->where('calf_drop_overdue', true)->groupBy('cooperative_id')
            ->map(fn ($animals) => [
                'cooperative_id' => $animals->first()->cooperative_id,
                'cooperative_name' => $animals->first()->cooperative_name ?: 'Unassigned cooperative',
                'count' => $animals->count(),
                'animals' => $animals->sortByDesc('pregnancy_days')->map(fn ($r) => [
                    'animal_id' => $r->animal_registry_id ?: $r->registry_number,
                    'days' => $r->pregnancy_days,
                ])->values()->all(),
            ])->sortBy('cooperative_name')->values();
    }
}
