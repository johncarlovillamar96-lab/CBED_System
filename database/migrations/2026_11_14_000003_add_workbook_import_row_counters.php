<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'rows_inserted',
        'rows_updated',
        'rows_skipped',
        'rows_rejected',
        'rows_unmapped',
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $column) {
            if (! Schema::hasColumn('cooperative_workbook_import_sheets', $column)) {
                Schema::table('cooperative_workbook_import_sheets', function (Blueprint $table) use ($column) {
                    $table->unsignedInteger($column)->default(0);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $column) {
            if (Schema::hasColumn('cooperative_workbook_import_sheets', $column)) {
                Schema::table('cooperative_workbook_import_sheets', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
