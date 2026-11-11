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
        }

        return view('dashboard', compact('counts', 'animalSummary', 'milkTotal'));
    }
}
