<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE mating_records MODIFY mating_date DATE NULL');
    }

    public function down(): void
    {
        if (! DB::table('mating_records')->whereNull('mating_date')->exists()) {
            DB::statement('ALTER TABLE mating_records MODIFY mating_date DATE NOT NULL');
        }
    }
};
