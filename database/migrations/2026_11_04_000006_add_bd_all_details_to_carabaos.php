<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $columns = [
        'birth_place', 'breeder', 'association_coop_institution',
        'barangay', 'municipality', 'province', 'district', 'region',
    ];

    public function up(): void
    {
        Schema::table('carabaos', function (Blueprint $table) {
            foreach ($this->columns as $column) {
                if (! Schema::hasColumn('carabaos', $column)) {
                    $table->string($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('carabaos', function (Blueprint $table) {
            foreach ($this->columns as $column) {
                if (Schema::hasColumn('carabaos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
