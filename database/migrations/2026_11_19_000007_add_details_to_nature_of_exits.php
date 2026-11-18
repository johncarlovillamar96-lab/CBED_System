<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DETAILS = ['reason_of_exit', 'entrustment', 'ownership_status', 'disposed_to', 'disposal_province', 'disposal_municipality', 'disposal_barangay'];

    public function up(): void
    {
        Schema::table('nature_of_exits', function (Blueprint $table) {
            foreach (self::DETAILS as $field) $table->string($field)->nullable();
            $table->boolean('details_entered')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('nature_of_exits', fn (Blueprint $table) => $table->dropColumn([...self::DETAILS, 'details_entered']));
    }
};
