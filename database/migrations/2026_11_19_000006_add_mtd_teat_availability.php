<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carabaos', function (Blueprint $table) {
            $table->json('mtd_missing_teats')->nullable();
        });
        Schema::table('mtd_records', function (Blueprint $table) {
            $table->json('missing_teats')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mtd_records', fn (Blueprint $table) => $table->dropColumn('missing_teats'));
        Schema::table('carabaos', fn (Blueprint $table) => $table->dropColumn('mtd_missing_teats'));
    }
};
