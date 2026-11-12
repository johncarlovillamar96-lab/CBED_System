<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index()
    {
        $tables = [
            'farmers',
            'cooperatives',
            'carabaos',
            'mating_records',
            'calves',
            'milk_records',
            'health_records',
        ];

        $counts = [];

        foreach ($tables as $table) {
            $counts[$table] = Schema::hasTable($table)
                ? DB::table($table)->count()
                : 0;
        }
        $milkTotal = Schema::hasTable('milk_records') ? (float) DB::table('milk_records')->sum('total_liters') : 0;

        $animalSummary = [
            'live' => 0, 'dead' => 0, 'pregnant' => 0, 'lactating' => 0, 'dry' => 0,
            'parity' => 0, 'animals' => 0, 'parity_0' => 0, 'parity_1' => 0,
            'parity_2' => 0, 'parity_3' => 0, 'parity_4_plus' => 0,
        ];
        $provincialSummary = [];
        if (Schema::hasTable('carabaos')) {
            $animalSummary['animals'] = DB::table('carabaos')->count();
            $animalSummary['live'] = DB::table('carabaos')->where('status', 'Live')->count();
            $animalSummary['dead'] = DB::table('carabaos')->where('status', 'Dead')->count();
            $animalSummary['pregnant'] = DB::table('carabaos')->whereRaw('LOWER(reproductive_status) LIKE ?', ['%pregnant%'])->count();
            $animalSummary['lactating'] = DB::table('carabaos')->whereRaw('LOWER(lactation_status) LIKE ?', ['%lactat%'])->count();
            $animalSummary['dry'] = DB::table('carabaos')->whereRaw('LOWER(lactation_status) LIKE ?', ['%dry%'])->count();
            $animalSummary['parity'] = (int) DB::table('carabaos')->sum('parity');
            foreach ([0, 1, 2, 3] as $parity) {
                $animalSummary['parity_'.$parity] = DB::table('carabaos')->where('parity', $parity)->count();
            }
            $animalSummary['parity_4_plus'] = DB::table('carabaos')->where('parity', '>=', 4)->count();

            $animals = DB::table('carabaos as animal')
                ->select('animal.province', 'animal.status', 'animal.classification');

            if (Schema::hasColumn('carabaos', 'exit_reason')) {
                $animals->addSelect('animal.exit_reason');
            }

            if (Schema::hasTable('fhpr_records') && Schema::hasColumn('fhpr_records', 'days_in_milk')) {
                $latestFhprIds = DB::table('fhpr_records')
                    ->select('carabao_id')
                    ->selectRaw('MAX(id) as id')
                    ->groupBy('carabao_id');
                $daysInMilk = DB::table('fhpr_records as record')
                    ->joinSub($latestFhprIds, 'latest', 'latest.id', '=', 'record.id')
                    ->select('record.carabao_id', 'record.days_in_milk');

                $animals->leftJoinSub($daysInMilk, 'fhpr', 'fhpr.carabao_id', '=', 'animal.id')
                    ->addSelect('fhpr.days_in_milk');
            }

            foreach ($animals->get() as $animal) {
                $province = trim((string) ($animal->province ?? '')) ?: 'Unknown Province';
                $provincialSummary[$province] ??= [
                    'province' => $province,
                    'live' => 0,
                    'dead_or_out' => 0,
                    'breedable' => 0,
                    'pregnant' => 0,
                    'lactating' => 0,
                ];

                $status = strtolower(trim((string) ($animal->status ?? '')));
                $exitReason = trim((string) ($animal->exit_reason ?? ''));
                $isLive = $status === 'live' || ($status === '' && $exitReason === '');
                if ($isLive) {
                    $provincialSummary[$province]['live']++;
                }
                if (in_array($status, ['dead', 'out'], true)) {
                    $provincialSummary[$province]['dead_or_out']++;
                }

                if ($isLive) {
                    $classification = preg_replace('/[\s_-]+/', '', strtolower(trim((string) ($animal->classification ?? ''))));
                    $daysInMilk = (int) ($animal->days_in_milk ?? 0);
                    if ($classification === 'heifer'
                        || $classification === 'cowdry'
                        || ($classification === 'cowlactating' && $daysInMilk >= 25 && $daysInMilk <= 45)) {
                        $provincialSummary[$province]['breedable']++;
                    }
                    if (in_array($classification, ['heiferpregnant', 'cowdrypregnant', 'cowlactatingpregnant'], true)) {
                        $provincialSummary[$province]['pregnant']++;
                    }
                    if (in_array($classification, ['cowlactating', 'cowlactatingpregnant'], true)) {
                        $provincialSummary[$province]['lactating']++;
                    }
                }
            }

            ksort($provincialSummary, SORT_NATURAL | SORT_FLAG_CASE);
            $provincialSummary = array_values($provincialSummary);
        }

        return view('dashboard', compact('counts', 'animalSummary', 'provincialSummary', 'milkTotal'));
    }
}
