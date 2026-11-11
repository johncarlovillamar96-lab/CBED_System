<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carabaos', function (Blueprint $table) {
            $table->dropUnique('carabaos_registry_number_unique');
            $table->unique(['cooperative_id', 'registry_number'], 'carabaos_coop_registry_unique');
        });

        foreach (['mating_records', 'health_records', 'nature_of_exits', 'mtd_records'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (! Schema::hasColumn($name, 'cooperative_id')) {
                    $table->foreignId('cooperative_id')->nullable()->constrained('cooperatives')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['mating_records', 'health_records', 'nature_of_exits', 'mtd_records'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (Schema::hasColumn($name, 'cooperative_id')) {
                    $table->dropConstrainedForeignId('cooperative_id');
                }
            });
        }
        Schema::table('carabaos', function (Blueprint $table) {
            $table->dropUnique('carabaos_coop_registry_unique');
            $table->unique('registry_number', 'carabaos_registry_number_unique');
        });
    }
};
