<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->nullable()->constrained('cooperatives')->nullOnDelete();
            $table->string('cooperative_name');
            $table->string('province')->nullable();
            $table->string('municipality')->nullable();
            $table->string('barangay')->nullable();
            $table->date('report_date');
            $table->string('monitored_by')->nullable();
            $table->string('attested_by')->nullable();
            $table->string('noted_by')->nullable();
            $table->json('report_data');
            $table->timestamps();
            $table->index(['cooperative_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_reports');
    }
};
