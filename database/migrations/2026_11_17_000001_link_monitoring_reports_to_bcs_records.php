<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitoring_report_details', function (Blueprint $table) {
            if (! Schema::hasColumn('monitoring_report_details', 'body_condition_score')) {
                $table->decimal('body_condition_score', 5, 2)->nullable();
            }
        });

        Schema::table('bcs_records', function (Blueprint $table) {
            if (! Schema::hasColumn('bcs_records', 'monitoring_report_detail_id')) {
                $table->foreignId('monitoring_report_detail_id')
                    ->nullable()
                    ->constrained('monitoring_report_details')
                    ->nullOnDelete();
                $table->unique('monitoring_report_detail_id', 'bcs_monitoring_detail_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bcs_records', function (Blueprint $table) {
            if (Schema::hasColumn('bcs_records', 'monitoring_report_detail_id')) {
                $table->dropConstrainedForeignId('monitoring_report_detail_id');
            }
        });

        Schema::table('monitoring_report_details', function (Blueprint $table) {
            if (Schema::hasColumn('monitoring_report_details', 'body_condition_score')) {
                $table->dropColumn('body_condition_score');
            }
        });
    }
};
