<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('milk_records', function (Blueprint $table) {
            if (! Schema::hasColumn('milk_records', 'collection_period')) {
                $table->string('collection_period', 10)->nullable();
            }
            if (! Schema::hasColumn('milk_records', 'number_of_cows')) {
                $table->unsignedInteger('number_of_cows')->nullable();
            }
            if (! Schema::hasColumn('milk_records', 'price_per_kg')) {
                $table->decimal('price_per_kg', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('milk_records', 'milk_traded')) {
                $table->decimal('milk_traded', 12, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('milk_records', function (Blueprint $table) {
            foreach (['collection_period', 'number_of_cows', 'price_per_kg', 'milk_traded'] as $column) {
                if (Schema::hasColumn('milk_records', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
