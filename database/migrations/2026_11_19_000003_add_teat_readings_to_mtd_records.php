<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mtd_records', function (Blueprint $table) {
            $table->foreignId('farmer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('collection_period', 2)->nullable();
            foreach (['right_front', 'right_back', 'left_front', 'left_back'] as $teat) {
                $table->decimal($teat, 8, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('mtd_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('farmer_id');
            $table->dropColumn(['collection_period', 'right_front', 'right_back', 'left_front', 'left_back']);
        });
    }
};
