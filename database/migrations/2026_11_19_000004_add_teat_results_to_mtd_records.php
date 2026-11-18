<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mtd_records', function (Blueprint $table) {
            foreach (['right_front_result', 'right_back_result', 'left_front_result', 'left_back_result'] as $field) {
                $table->string($field, 8)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('mtd_records', function (Blueprint $table) {
            $table->dropColumn(['right_front_result', 'right_back_result', 'left_front_result', 'left_back_result']);
        });
    }
};
