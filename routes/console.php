<?php

use App\Http\Controllers\CooperativeImportController;
use Illuminate\Support\Facades\Artisan;

Artisan::command('cbed:fill-missing-inventory {file} {--cooperative=} {--apply}', function () {
    $path = realpath($this->argument('file'));
    $cooperativeId = (int) $this->option('cooperative');
    if (! $path || ! \Illuminate\Support\Facades\DB::table('cooperatives')->where('id', $cooperativeId)->exists()) {
        $this->error('Provide an existing workbook and --cooperative=<id>.');
        return 1;
    }
    $result = app(CooperativeImportController::class)->fillMissingInventorySheet($path, $cooperativeId, (bool) $this->option('apply'));
    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
})->purpose('Preview or fill blank fields on existing animals without inserting records or replacing populated fields.');
