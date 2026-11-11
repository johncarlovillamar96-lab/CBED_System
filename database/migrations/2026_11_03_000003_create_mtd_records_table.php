<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mtd_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carabao_id')->constrained('carabaos')->cascadeOnDelete();
            $table->date('test_date');
            $table->decimal('milk_yield_liters', 8, 2)->nullable();
            $table->decimal('fat_percentage', 5, 2)->nullable();
            $table->decimal('snf_percentage', 5, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index(['carabao_id', 'test_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mtd_records');
    }
};
