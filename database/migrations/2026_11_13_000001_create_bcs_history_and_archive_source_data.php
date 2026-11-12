<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bcs_records')) {
            Schema::create('bcs_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cooperative_id')->nullable()->constrained('cooperatives')->nullOnDelete();
                $table->foreignId('carabao_id')->nullable()->constrained('carabaos')->nullOnDelete();
                $table->string('registry_number')->nullable();
                $table->date('record_date')->nullable();
                $table->decimal('body_condition_score', 5, 2)->nullable();
                $table->json('source_data')->nullable();
                $table->timestamps();
                $table->index(['cooperative_id', 'registry_number', 'record_date'], 'bcs_coop_registry_date_index');
            });
        }

        foreach (['mating_records', 'health_records', 'milk_records', 'mtd_records'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'source_data')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->json('source_data')->nullable();
                });
            }
        }

        if (! Schema::hasColumn('milk_records', 'farmer_name')) {
            Schema::table('milk_records', function (Blueprint $table) {
                $table->string('farmer_name')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bcs_records');

        foreach (['mating_records', 'health_records', 'milk_records', 'mtd_records'] as $tableName) {
            if (Schema::hasColumn($tableName, 'source_data')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('source_data');
                });
            }
        }

        if (Schema::hasColumn('milk_records', 'farmer_name')) {
            Schema::table('milk_records', function (Blueprint $table) {
                $table->dropColumn('farmer_name');
            });
        }
    }
};
