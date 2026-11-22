<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id');
            $table->string('actor_name');
            $table->string('actor_role', 32);
            $table->string('action', 32);
            $table->string('route');
            $table->string('target')->nullable();
            $table->unsignedSmallInteger('status');
            $table->boolean('validation_failed')->default(false);
            $table->timestamp('occurred_at');
            $table->index(['actor_role', 'id']);
            $table->index(['action', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
