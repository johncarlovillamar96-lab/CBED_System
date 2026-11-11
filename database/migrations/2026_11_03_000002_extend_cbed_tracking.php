<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->foreignId('cooperative_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->unsignedInteger('cooperative_sequence')->nullable()->after('cooperative_id');
            $table->unique(['cooperative_id', 'cooperative_sequence'], 'farmers_coop_sequence_unique');
        });

        Schema::table('carabaos', function (Blueprint $table) {
            $table->string('registry_number')->nullable()->unique();
            $table->string('bolus_id')->nullable()->index();
            $table->string('tag_number')->nullable()->index();
            $table->string('reproductive_status')->nullable()->index();
            $table->string('lactation_status')->nullable()->index();
            $table->unsignedSmallInteger('parity')->nullable();
            $table->date('death_date')->nullable();
            $table->string('exit_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('carabaos', function (Blueprint $table) {
            $table->dropColumn(['registry_number', 'bolus_id', 'tag_number', 'reproductive_status', 'lactation_status', 'parity', 'death_date', 'exit_reason']);
        });

        Schema::table('farmers', function (Blueprint $table) {
            $table->dropUnique('farmers_coop_sequence_unique');
            $table->dropConstrainedForeignId('cooperative_id');
            $table->dropColumn('cooperative_sequence');
        });
    }
};
