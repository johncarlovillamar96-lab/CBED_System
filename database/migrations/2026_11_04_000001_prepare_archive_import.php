<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('milk_records', function (Blueprint $table) {
            if (! Schema::hasColumn('milk_records', 'farmer_id')) {
                $table->foreignId('farmer_id')->nullable()->after('carabao_id')->constrained('farmers')->nullOnDelete();
            }
        });
        DB::statement('ALTER TABLE milk_records MODIFY carabao_id BIGINT UNSIGNED NULL');

        Schema::table('mtd_records', function (Blueprint $table) {
            if (! Schema::hasColumn('mtd_records', 'registry_number')) {
                $table->string('registry_number')->nullable()->index();
            }
            if (! Schema::hasColumn('mtd_records', 'dryoff_date')) {
                $table->date('dryoff_date')->nullable();
            }
            if (! Schema::hasColumn('mtd_records', 'test_day')) {
                $table->unsignedTinyInteger('test_day')->nullable();
            }
            if (! Schema::hasColumn('mtd_records', 'days_in_milk')) {
                $table->unsignedSmallInteger('days_in_milk')->nullable();
            }
            if (! Schema::hasColumn('mtd_records', 'mtd_value')) {
                $table->decimal('mtd_value', 8, 2)->nullable();
            }
        });
        DB::statement('ALTER TABLE mtd_records MODIFY carabao_id BIGINT UNSIGNED NULL');

        if (! Schema::hasTable('fhpr_records')) {
            Schema::create('fhpr_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('carabao_id')->nullable()->constrained('carabaos')->nullOnDelete();
                $table->foreignId('cooperative_id')->nullable()->constrained('cooperatives')->nullOnDelete();
                $table->string('farmer_name')->nullable();
                $table->string('registry_number')->nullable()->index();
                $table->string('animal_type')->nullable();
                $table->string('tag_number')->nullable();
                $table->date('date_of_birth')->nullable();
                $table->string('status_updated')->nullable();
                $table->decimal('body_condition_score', 5, 2)->nullable();
                $table->date('previous_calving_date')->nullable();
                $table->date('present_calving_date')->nullable();
                $table->decimal('calving_interval_months', 8, 2)->nullable();
                $table->unsignedSmallInteger('days_in_milk')->nullable();
                $table->date('dry_off_date')->nullable();
                $table->date('first_service_date')->nullable();
                $table->date('first_ai_date')->nullable();
                $table->date('last_ai_date')->nullable();
                $table->unsignedSmallInteger('ai_count')->nullable();
                $table->string('pregnancy_status')->nullable();
                $table->unsignedSmallInteger('days_pregnant')->nullable();
                $table->string('ai_or_n')->nullable();
                $table->string('estrus_type')->nullable();
                $table->string('sire_used')->nullable();
                $table->unsignedSmallInteger('days_to_first_service')->nullable();
                $table->unsignedSmallInteger('days_open')->nullable();
                $table->string('mating_type')->nullable();
                $table->date('bull_in_date')->nullable();
                $table->string('bull_name_or_id')->nullable();
                $table->date('next_heat_date')->nullable();
                $table->date('second_heat_cycle_date')->nullable();
                $table->date('expected_pd_date')->nullable();
                $table->date('actual_pd_date')->nullable();
                $table->date('expected_calving_date')->nullable();
                $table->unsignedSmallInteger('gestation_length_days')->nullable();
                $table->unsignedSmallInteger('parity')->nullable();
                $table->string('action_taken')->nullable();
                $table->text('remarks')->nullable();
                $table->json('source_data')->nullable();
                $table->timestamps();
                $table->unique(['cooperative_id', 'registry_number'], 'fhpr_coop_registry_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fhpr_records');
        DB::statement('ALTER TABLE milk_records MODIFY carabao_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE mtd_records MODIFY carabao_id BIGINT UNSIGNED NOT NULL');
    }
};
