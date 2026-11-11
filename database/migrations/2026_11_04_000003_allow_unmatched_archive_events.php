<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE mating_records MODIFY carabao_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE health_records MODIFY carabao_id BIGINT UNSIGNED NULL');

        Schema::table('mating_records', function (Blueprint $table) {
            if (! Schema::hasColumn('mating_records', 'registry_number')) {
                $table->string('registry_number')->nullable()->index();
            }
        });
        Schema::table('health_records', function (Blueprint $table) {
            if (! Schema::hasColumn('health_records', 'registry_number')) {
                $table->string('registry_number')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('mating_records', function (Blueprint $table) {
            if (Schema::hasColumn('mating_records', 'registry_number')) $table->dropColumn('registry_number');
        });
        Schema::table('health_records', function (Blueprint $table) {
            if (Schema::hasColumn('health_records', 'registry_number')) $table->dropColumn('registry_number');
        });
        DB::statement('ALTER TABLE mating_records MODIFY carabao_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE health_records MODIFY carabao_id BIGINT UNSIGNED NOT NULL');
    }
};
