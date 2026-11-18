<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class MilkProductionRecords
{
    public static function query(): Builder
    {
        return DB::table('milk_records as record')
            ->leftJoin('carabaos as animal', 'animal.id', '=', 'record.carabao_id')
            ->leftJoin('farmers as farmer', 'farmer.id', '=', DB::raw('COALESCE(record.farmer_id, animal.farmer_id)'))
            ->leftJoin('cooperatives as cooperative', 'cooperative.id', '=', DB::raw('COALESCE(record.cooperative_id, farmer.cooperative_id, animal.cooperative_id)'));
    }
}
