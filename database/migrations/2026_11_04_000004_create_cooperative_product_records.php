<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cooperative_product_records')) return;

        Schema::create('cooperative_product_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->nullable()->constrained('cooperatives')->nullOnDelete();
            $table->string('product_type', 30);
            $table->date('actual_date')->nullable();
            $table->date('reported_date')->nullable();
            $table->decimal('volume_kg', 12, 2)->nullable();
            $table->decimal('other_source_volume_kg', 12, 2)->nullable();
            $table->decimal('price_per_kg', 12, 2)->nullable();
            $table->decimal('value_php', 14, 2)->nullable();
            $table->json('source_data')->nullable();
            $table->timestamps();
            $table->index(['cooperative_id', 'product_type', 'actual_date'], 'coop_product_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooperative_product_records');
    }
};
