<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carabaos', function (Blueprint $table) {
            // MySQL may rely on the composite unique index for the cooperative FK.
            // Add a leading-column index before dropping that uniqueness constraint.
            $table->index('cooperative_id', 'carabaos_cooperative_id_index');
            $table->dropUnique('carabaos_coop_registry_unique');
            $table->index(['cooperative_id', 'registry_number'], 'carabaos_coop_registry_index');
        });
    }

    public function down(): void
    {
        Schema::table('carabaos', function (Blueprint $table) {
            $table->dropIndex('carabaos_coop_registry_index');
            $table->unique(['cooperative_id', 'registry_number'], 'carabaos_coop_registry_unique');
            $table->dropIndex('carabaos_cooperative_id_index');
        });
    }
};
