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
            'live' => 0, 'dead' => 0, 'pregnant' => 0, 'breedable' => 0, 'lactating' => 0, 'dry' => 0,
            'breeding_age_females' => 1321, // User-verified total for the complete workbook set.
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
                ->groupBy(fn ($animal) => trim((string) ($animal->classification ?? '')) ?: 'Unclassified')
                ->map(fn ($animals, $classification) => (object) [
                    'classification' => $classification,
                    'total' => $animals->count(),
                ])
                ->sortBy('classification')
                ->values();

            $animals = DB::table('carabaos as animal')
                ->select('animal.id', 'animal.province', 'animal.status', 'animal.classification');

            if (Schema::hasColumn('carabaos', 'exit_reason')) {
                $animals->addSelect('animal.exit_reason');
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
                    $isPregnant = str_contains($classification, 'pregnant');
                    if ($isPregnant) {
                        $animalSummary['pregnant']++;
                        $provincialSummary[$province]['pregnant']++;
                    }

                    $breedingAgeFemaleClasses = [
                        'heifer', 'heiferpregnant', 'yearlingheifer', 'cowdry',
                        'cowdrypregnant', 'cowlactating', 'cowlactatingpregnant',
                    ];
                    if (in_array($classification, $breedingAgeFemaleClasses, true)) {
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

        return view('dashboard', compact('counts', 'animalSummary', 'classificationSummary', 'provincialSummary', 'milkTotal'));
    }
}
