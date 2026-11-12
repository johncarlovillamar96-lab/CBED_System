<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['milk_records', 'nature_of_exits'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'registry_number')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('registry_number')->nullable()->index();
                });
            }
        }

        if (! Schema::hasTable('cooperative_workbook_imports')) {
            Schema::create('cooperative_workbook_imports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cooperative_id')->nullable()->constrained()->nullOnDelete();
                $table->string('file_name');
                $table->string('status', 40)->default('processing');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['cooperative_id', 'created_at'], 'coop_workbook_imports_coop_created_idx');
            });
        }

        if (! Schema::hasTable('cooperative_workbook_import_sheets')) {
            Schema::create('cooperative_workbook_import_sheets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workbook_import_id')->constrained('cooperative_workbook_imports')->cascadeOnDelete();
                $table->string('sheet_name');
                $table->string('target_module')->nullable();
                $table->string('status', 40)->default('pending');
                $table->unsignedInteger('rows_seen')->default(0);
                $table->unsignedInteger('rows_imported')->default(0);
                $table->text('message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['workbook_import_id', 'status'], 'coop_workbook_sheets_import_status_idx');
            });
        }
    }

    public function down(): void
    {
        // Keep repair-created tables and data intact when rolling back unrelated migrations.
    }
};
