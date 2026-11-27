<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_submissions', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('submission_id');
            $table->string('payload_hash', 64);
            $table->foreignId('report_id')->nullable()->constrained('monitoring_reports')->nullOnDelete();
            $table->primary(['user_id', 'submission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_submissions');
    }
};
