<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('semen_bulls', 'semen_used')) {
            Schema::table('semen_bulls', function (Blueprint $table) {
                $table->unsignedInteger('semen_used')->default(0)->after('status');
            });
        }

        if (! Schema::hasColumn('mating_records', 'semen_bull_id')) {
            Schema::table('mating_records', function (Blueprint $table) {
                $table->foreignId('semen_bull_id')->nullable()->after('bull_id')
                    ->constrained('semen_bulls')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mating_records', 'semen_bull_id')) {
            Schema::table('mating_records', function (Blueprint $table) {
                $table->dropConstrainedForeignId('semen_bull_id');
            });
        }

        if (Schema::hasColumn('semen_bulls', 'semen_used')) {
            Schema::table('semen_bulls', function (Blueprint $table) {
                $table->dropColumn('semen_used');
            });
        }
    }
};
