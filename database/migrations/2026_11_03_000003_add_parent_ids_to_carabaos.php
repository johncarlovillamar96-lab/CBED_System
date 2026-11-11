<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carabaos', function (Blueprint $table) {
            if (! Schema::hasColumn('carabaos', 'sire_id')) {
                $table->string('sire_id')->nullable();
            }
            if (! Schema::hasColumn('carabaos', 'sire_name')) {
                $table->string('sire_name')->nullable();
            }
            if (! Schema::hasColumn('carabaos', 'dam_id')) {
                $table->string('dam_id')->nullable();
            }
            if (! Schema::hasColumn('carabaos', 'dam_name')) {
                $table->string('dam_name')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('carabaos', function (Blueprint $table) {
            $columns = ['sire_id', 'sire_name', 'dam_id', 'dam_name'];
            $existing = array_values(array_filter($columns, fn ($column) => Schema::hasColumn('carabaos', $column)));
            if ($existing) {
                $table->dropColumn($existing);
            }
        });
    }
};
