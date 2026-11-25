<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calves', function (Blueprint $table) {
            $table->unsignedBigInteger('dam_id')->nullable()->change();
            $table->date('birth_date')->nullable()->change();
            $table->json('workbook_details')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('calves')->whereNull('dam_id')->orWhereNull('birth_date')->exists()) {
            throw new RuntimeException('Resolve incomplete calf records before reverting this migration.');
        }
        Schema::table('calves', function (Blueprint $table) {
            $table->unsignedBigInteger('dam_id')->nullable(false)->change();
            $table->date('birth_date')->nullable(false)->change();
            $table->dropColumn('workbook_details');
        });
    }
};
