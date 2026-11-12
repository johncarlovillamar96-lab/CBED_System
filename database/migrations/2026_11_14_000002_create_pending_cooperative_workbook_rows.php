<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pending_cooperative_workbook_rows')) {
            Schema::create('pending_cooperative_workbook_rows', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('identifier_id');
                $table->unsignedBigInteger('batch_file_id')->nullable();
                $table->unsignedBigInteger('workbook_import_id')->nullable();
                $table->unsignedBigInteger('workbook_import_sheet_id')->nullable();
                $table->string('target_table', 100);
                $table->unsignedInteger('source_row');
                $table->json('source_data');
                $table->string('status', 30)->default('pending');
                $table->timestamps();
                $table->unique(['workbook_import_sheet_id', 'source_row'], 'pending_coop_workbook_row_unique');
                $table->index(['identifier_id', 'status'], 'pending_coop_workbook_identifier_status_idx');
                $table->foreign('identifier_id', 'pending_coop_rows_identifier_fk')
                    ->references('id')->on('unmapped_cooperative_identifiers')->cascadeOnDelete();
                $table->foreign('batch_file_id', 'pending_coop_rows_batch_file_fk')
                    ->references('id')->on('cooperative_workbook_batch_files')->nullOnDelete();
                $table->foreign('workbook_import_id', 'pending_coop_rows_import_fk')
                    ->references('id')->on('cooperative_workbook_imports')->nullOnDelete();
            });
        }

        $hasSheetForeignKey = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', 'pending_cooperative_workbook_rows')
            ->where('CONSTRAINT_NAME', 'pending_coop_rows_sheet_fk')
            ->exists();
        if (! $hasSheetForeignKey) {
            Schema::table('pending_cooperative_workbook_rows', function (Blueprint $table) {
                $table->foreign('workbook_import_sheet_id', 'pending_coop_rows_sheet_fk')
                    ->references('id')->on('cooperative_workbook_import_sheets')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_cooperative_workbook_rows');
    }
};
