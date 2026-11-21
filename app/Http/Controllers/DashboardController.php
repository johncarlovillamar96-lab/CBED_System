<?php

namespace App\Http\Controllers;

use App\Support\InventoryClassification;
use App\Support\MilkProductionRecords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request)
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
        $currentYear = now('Asia/Manila')->year;
        $milkYear = $currentYear;
        $milkYears = collect([$currentYear]);
        $cooperativeMilk = collect();
        if (Schema::hasTable('milk_records')) {
            $milkYears = DB::table('milk_records')
                ->whereNotNull('record_date')
                ->whereNotNull('total_liters')
                ->selectRaw('YEAR(record_date) as production_year')
                ->distinct()
                ->pluck('production_year')
                ->map(fn ($year) => (int) $year)
                ->push($currentYear)
                ->unique()
                ->sortDesc()
                ->values();

            $requestedYear = $request->query('milk_year');
            if (in_array($requestedYear, $milkYears->map(fn ($year) => (string) $year)->all(), true)) {
                $milkYear = (int) $requestedYear;
            }

            $cooperativeMilk = MilkProductionRecords::query()
                ->where('record.record_date', '>=', sprintf('%04d-01-01', $milkYear))
                ->where('record.record_date', '<', sprintf('%04d-01-01', $milkYear + 1))
                ->whereNotNull('record.total_liters')
                ->select('cooperative.cooperative_name')
                ->selectRaw('SUM(record.total_liters) as total_liters')
                ->groupBy('cooperative.id', 'cooperative.cooperative_name')
                ->orderByRaw('CASE WHEN cooperative.id IS NULL THEN 1 ELSE 0 END')
                ->orderBy('cooperative.cooperative_name')
                ->get();
        }
        $milkTotal = $cooperativeMilk->sum('total_liters');

        $animalSummary = [
            'live' => 0, 'dead' => 0, 'pregnant' => 0, 'breedable' => 0, 'lactating' => 0, 'dry' => 0,
            'parity' => 0, 'animals' => 0, 'parity_0' => 0, 'parity_1' => 0,
            'parity_2' => 0, 'parity_3' => 0, 'parity_4_plus' => 0,
        ];
        $classificationSummary = [];
        $provincialSummary = [];
        if (Schema::hasTable('carabaos')) {
            $animalSummary['animals'] = DB::table('carabaos')->count();
            $animalSummary['live'] = DB::table('carabaos')->where('status', 'Live')->count();
            $animalSummary['dead'] = DB::table('carabaos')->where('status', 'Dead')->count();
            $animalSummary['lactating'] = DB::table('carabaos')->whereRaw('LOWER(lactation_status) LIKE ?', ['%lactat%'])->count();
            $animalSummary['dry'] = DB::table('carabaos')->whereRaw('LOWER(lactation_status) LIKE ?', ['%dry%'])->count();
            $animalSummary['parity'] = (int) DB::table('carabaos')->sum('parity');
            foreach ([0, 1, 2, 3] as $parity) {
                $animalSummary['parity_'.$parity] = DB::table('carabaos')->where('parity', $parity)->count();
            }
            $animalSummary['parity_4_plus'] = DB::table('carabaos')->where('parity', '>=', 4)->count();

            $classificationSummary = DB::table('carabaos')
                ->get(['classification'])
                ->map(fn ($animal) => trim((string) $animal->classification))
                ->reject(fn ($classification) => $classification === '' || strcasecmp($classification, 'Unclassified') === 0)
                ->groupBy(fn ($classification) => $classification)
                ->map(fn ($animals, $classification) => (object) [
                    'classification' => $classification,
                    'total' => $animals->count(),
                ])
                ->sortBy('classification')
                ->values();

            $animals = InventoryClassification::breedingInventoryQuery()->addSelect('animal.province');

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
                $isLive = InventoryClassification::isLiveInInventorySummary($animal);
                if ($isLive) {
                    $provincialSummary[$province]['live']++;
                }
                if (in_array($status, ['dead', 'out'], true)) {
                    $provincialSummary[$province]['dead_or_out']++;
                }

                if ($isLive) {
                    $classification = preg_replace('/[\s_-]+/', '', strtolower(trim((string) ($animal->classification ?? ''))));
                    $isPregnant = str_contains($classification, 'pregnant');
                    if ($isPregnant) {
                        $animalSummary['pregnant']++;
                        $provincialSummary[$province]['pregnant']++;
                    }

                    if (InventoryClassification::isLiveBreedableFemale($animal)) {
                        $animalSummary['breedable']++;
                        $provincialSummary[$province]['breedable']++;
                    }
                    if (in_array($classification, ['cowlactating', 'cowlactatingpregnant'], true)) {
                        $provincialSummary[$province]['lactating']++;
                    }
                }
            }

            ksort($provincialSummary, SORT_NATURAL | SORT_FLAG_CASE);
            $provincialSummary = array_values($provincialSummary);
        }

        return view('dashboard', compact(
            'counts', 'animalSummary', 'classificationSummary', 'provincialSummary',
            'milkTotal', 'milkYear', 'milkYears', 'cooperativeMilk',
        ));
    }
}
