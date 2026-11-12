<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooperative_workbook_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('staging');
            $table->unsignedInteger('files_total')->default(0);
            $table->unsignedInteger('files_completed')->default(0);
            $table->unsignedInteger('files_failed')->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cooperative_workbook_batch_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('cooperative_workbook_batches')->cascadeOnDelete();
            $table->foreignId('cooperative_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workbook_import_id')->nullable()->constrained('cooperative_workbook_imports')->nullOnDelete();
            $table->string('original_filename');
            $table->string('file_hash', 64);
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('status', 40)->default('staged');
            $table->string('match_status', 40)->default('needs_review');
            $table->json('match_evidence')->nullable();
            $table->json('missing_sheets')->nullable();
            $table->json('sheet_summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['batch_id', 'file_hash'], 'coop_workbook_batch_file_hash_unique');
            $table->index(['batch_id', 'status'], 'coop_workbook_batch_file_status_idx');
        });

        Schema::create('unmapped_cooperative_identifiers', function (Blueprint $table) {
            $table->id();
            $table->string('source_label');
            $table->string('normalized_label')->unique();
            $table->string('status', 30)->default('pending');
            $table->foreignId('mapped_cooperative_id')->nullable()->constrained('cooperatives')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('unmapped_cooperative_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('identifier_id')->constrained('unmapped_cooperative_identifiers')->cascadeOnDelete();
            $table->string('record_table', 100);
            $table->unsignedBigInteger('record_id');
            $table->foreignId('workbook_import_sheet_id')->nullable()->constrained('cooperative_workbook_import_sheets')->nullOnDelete();
            $table->string('status', 30)->default('pending');
            $table->timestamps();
            $table->unique(['identifier_id', 'record_table', 'record_id'], 'unmapped_coop_record_unique');
            $table->index(['record_table', 'record_id'], 'unmapped_coop_record_target_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('unmapped_cooperative_records');
        Schema::dropIfExists('unmapped_cooperative_identifiers');
        Schema::dropIfExists('cooperative_workbook_batch_files');
        Schema::dropIfExists('cooperative_workbook_batches');
    }
};
