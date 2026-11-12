<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nature_of_exits') && ! Schema::hasColumn('nature_of_exits', 'farmer_name')) {
            Schema::table('nature_of_exits', function (Blueprint $table) {
                $table->string('farmer_name')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nature_of_exits') && Schema::hasColumn('nature_of_exits', 'farmer_name')) {
            Schema::table('nature_of_exits', function (Blueprint $table) {
                $table->dropColumn('farmer_name');
            });
        }
    }
};
