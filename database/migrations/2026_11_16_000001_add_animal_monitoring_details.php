<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitoring_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('monitoring_reports', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('cooperative_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('monitoring_reports', 'remarks')) {
                $table->text('remarks')->nullable();
            }
        });

        Schema::create('monitoring_report_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitoring_report_id')->constrained('monitoring_reports')->cascadeOnDelete();
            $table->foreignId('carabao_id')->nullable()->constrained('carabaos')->nullOnDelete();
            $table->string('animal_database_id')->nullable();
            $table->string('bolus_id')->nullable();
            $table->string('registry_number')->nullable();
            $table->string('tag_number')->nullable();
            $table->string('animal_name')->nullable();
            $table->string('farmer_name')->nullable();
            $table->string('sex', 20)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('breed')->nullable();
            $table->string('availability_status', 20)->default('available');
            $table->string('unavailability_reason')->nullable();
            $table->string('monitoring_specification')->nullable();
            $table->string('destination_module')->nullable();
            $table->unsignedBigInteger('destination_record_id')->nullable();
            $table->unsignedBigInteger('destination_inventory_id')->nullable();
            $table->string('monitoring_status', 20)->default('pending');
            $table->json('monitoring_data')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['monitoring_report_id', 'carabao_id'], 'monitoring_report_animal_unique');
            $table->index(['monitoring_specification', 'monitoring_status'], 'monitoring_detail_spec_status_index');
        });

        Schema::table('calves', function (Blueprint $table) {
            if (! Schema::hasColumn('calves', 'monitoring_date')) {
                $table->date('monitoring_date')->nullable();
            }
            if (! Schema::hasColumn('calves', 'birth_outcome')) {
                $table->string('birth_outcome', 30)->default('Live Birth');
            }
            if (! Schema::hasColumn('calves', 'animal_status')) {
                $table->string('animal_status', 30)->default('Live');
            }
            if (! Schema::hasColumn('calves', 'calf_findings')) {
                $table->text('calf_findings')->nullable();
            }
        });

        Schema::table('health_records', function (Blueprint $table) {
            if (! Schema::hasColumn('health_records', 'record_category')) {
                $table->string('record_category', 40)->default('health_nutrition')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('health_records', function (Blueprint $table) {
            if (Schema::hasColumn('health_records', 'record_category')) {
                $table->dropColumn('record_category');
            }
        });
        Schema::table('calves', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['monitoring_date', 'birth_outcome', 'animal_status', 'calf_findings'],
                fn ($column) => Schema::hasColumn('calves', $column)
            ));
            if ($columns) $table->dropColumn($columns);
        });
        Schema::dropIfExists('monitoring_report_details');
        Schema::table('monitoring_reports', function (Blueprint $table) {
            if (Schema::hasColumn('monitoring_reports', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
            if (Schema::hasColumn('monitoring_reports', 'remarks')) {
                $table->dropColumn('remarks');
            }
        });
    }
};
