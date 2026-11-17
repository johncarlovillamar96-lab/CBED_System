<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carabaos', function (Blueprint $table) {
            $table->string('nature_of_exit')->nullable();
            $table->string('ownership_status')->nullable();
            $table->string('disposed_to')->nullable();
            $table->string('place_of_disposal')->nullable();
            $table->decimal('value_of_disposed_animals', 12, 2)->nullable();
            $table->text('remarks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('carabaos', function (Blueprint $table) {
            $table->dropColumn(['nature_of_exit', 'ownership_status', 'disposed_to',
                'place_of_disposal', 'value_of_disposed_animals', 'remarks']);
        });
    }
};
