<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bcs_records', function (Blueprint $table) {
            $table->foreignId('health_record_id')->nullable()->unique()->constrained('health_records')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bcs_records', function (Blueprint $table) {
            $table->dropForeign(['health_record_id']);
            $table->dropUnique(['health_record_id']);
            $table->dropColumn('health_record_id');
        });
    }
};
