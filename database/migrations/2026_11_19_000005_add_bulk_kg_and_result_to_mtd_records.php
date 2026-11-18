<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mtd_records', function (Blueprint $table) {
            $table->decimal('bulk_kg', 8, 2)->nullable();
            $table->string('bulk_result', 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mtd_records', function (Blueprint $table) {
            $table->dropColumn(['bulk_kg', 'bulk_result']);
        });
    }
};
