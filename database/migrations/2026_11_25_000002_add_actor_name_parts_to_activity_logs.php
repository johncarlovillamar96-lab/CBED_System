<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('actor_first_name', 127)->nullable();
            $table->string('actor_last_name', 127)->nullable();
        });

        DB::table('users')->select('id', 'name', 'first_name', 'last_name')
            ->where(fn ($query) => $query->whereNotNull('first_name')->orWhereNotNull('last_name'))
            ->chunkById(200, function ($users) {
                foreach ($users as $user) {
                    DB::table('activity_logs')->where('actor_id', $user->id)->where('actor_name', $user->name)
                        ->update(['actor_first_name' => $user->first_name, 'actor_last_name' => $user->last_name]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn(['actor_first_name', 'actor_last_name']);
        });
    }
};
