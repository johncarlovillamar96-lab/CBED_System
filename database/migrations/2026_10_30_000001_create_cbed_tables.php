<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('farmers')) {
            Schema::create('farmers', function (Blueprint $table) {
                $table->id();
                $table->string('farmer_code')->nullable()->unique();
                $table->string('last_name');
                $table->string('first_name');
                $table->string('middle_name')->nullable();
                $table->date('birthday')->nullable();
                $table->string('sex', 10)->nullable();
                $table->string('cell_number', 40)->nullable();
                $table->string('civil_status')->nullable();
                $table->string('occupation')->nullable();
                $table->string('position')->nullable();
                $table->string('spouse_name')->nullable();
                $table->string('spouse_occupation')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cooperatives')) {
            Schema::create('cooperatives', function (Blueprint $table) {
                $table->id();
                $table->string('cda_registration_no')->nullable();
                $table->string('cooperative_code')->unique();
                $table->string('cooperative_name');
                $table->string('accreditation_status')->nullable();
                $table->string('coop_type')->nullable();
                $table->string('category')->nullable();
                $table->date('date_established')->nullable();
                $table->string('region')->nullable();
                $table->string('province')->nullable();
                $table->string('municipality')->nullable();
                $table->string('barangay')->nullable();
                $table->year('year_enrolled')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('carabaos')) {
            Schema::create('carabaos', function (Blueprint $table) {
                $table->id();
                $table->string('animal_id')->unique();
                $table->foreignId('farmer_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('cooperative_id')->nullable()->constrained()->nullOnDelete();
                $table->string('animal_name')->nullable();
                $table->string('sex', 10)->nullable();
                $table->string('breed')->nullable();
                $table->string('classification')->nullable();
                $table->date('birth_date')->nullable();
                $table->string('color')->nullable();
                $table->string('entrustment')->nullable();
                $table->string('status')->default('Live');
                $table->decimal('acquisition_cost', 12, 2)->nullable();
                $table->date('acquisition_date')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mating_records')) {
            Schema::create('mating_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('carabao_id')->constrained()->cascadeOnDelete();
                $table->date('mating_date');
                $table->string('mating_type')->nullable();
                $table->string('bull_id')->nullable();
                $table->string('technician')->nullable();
                $table->date('return_heat_from')->nullable();
                $table->date('return_heat_to')->nullable();
                $table->date('pregnancy_check_date')->nullable();
                $table->string('pregnancy_result')->nullable();
                $table->date('expected_calving_date')->nullable();
                $table->date('actual_calving_date')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }

        foreach (['calves', 'milk_records', 'health_records', 'nature_of_exits', 'semen_bulls', 'semen_usage'] as $name) {
            if (Schema::hasTable($name)) {
                continue;
            }

            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                if ($name === 'calves') {
                    $table->string('calf_animal_id')->nullable()->unique();
                    $table->foreignId('dam_id')->constrained('carabaos')->cascadeOnDelete();
                    $table->string('sire_id')->nullable();
                    $table->date('birth_date');
                    $table->string('sex', 10)->nullable();
                    $table->decimal('birth_weight', 8, 2)->nullable();
                } elseif ($name === 'milk_records') {
                    $table->foreignId('carabao_id')->constrained()->cascadeOnDelete();
                    $table->foreignId('cooperative_id')->nullable()->constrained()->nullOnDelete();
                    $table->date('record_date');
                    $table->decimal('morning_liters', 8, 2)->default(0);
                    $table->decimal('afternoon_liters', 8, 2)->default(0);
                    $table->decimal('total_liters', 8, 2)->default(0);
                } elseif ($name === 'health_records') {
                    $table->foreignId('carabao_id')->constrained()->cascadeOnDelete();
                    $table->date('monitoring_date');
                    $table->string('health_status')->nullable();
                    $table->text('findings')->nullable();
                    $table->text('treatment')->nullable();
                    $table->date('follow_up_date')->nullable();
                } elseif ($name === 'nature_of_exits') {
                    $table->foreignId('carabao_id')->constrained()->cascadeOnDelete();
                    $table->date('exit_date')->nullable();
                    $table->string('nature_of_exit')->nullable();
                } elseif ($name === 'semen_bulls') {
                    $table->string('bull_id')->unique();
                    $table->string('bull_name')->nullable();
                    $table->string('breed')->nullable();
                    $table->string('status')->default('Active');
                } else {
                    $table->foreignId('semen_bull_id')->constrained('semen_bulls')->cascadeOnDelete();
                    $table->foreignId('carabao_id')->nullable()->constrained()->nullOnDelete();
                    $table->date('used_date');
                    $table->unsignedInteger('quantity_used')->default(1);
                    $table->string('used_by')->nullable();
                }
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['semen_usage', 'semen_bulls', 'nature_of_exits', 'health_records', 'milk_records', 'calves', 'mating_records', 'carabaos', 'cooperatives', 'farmers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};