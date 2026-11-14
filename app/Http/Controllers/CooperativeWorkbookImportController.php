<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Support\SpreadsheetWorksheetReader;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class CooperativeWorkbookImportController extends Controller
{
    private ?array $cooperativeIdentifierIndex = null;
    private ?array $availableImportSheetCounterColumns = null;

    private const IMPORT_SHEET_COUNTER_COLUMNS = [
        'rows_inserted',
        'rows_updated',
        'rows_skipped',
        'rows_rejected',
        'rows_unmapped',
    ];

    private const SHEETS = [
        ['key' => 'bd_all', 'label' => 'BD_ALL', 'module' => 'Carabao Inventory', 'table' => 'carabaos', 'aliases' => ['bdall']],
        ['key' => 'bd_out', 'label' => 'BD_Out', 'module' => 'Nature of Exit', 'table' => 'nature_of_exits', 'aliases' => ['bdout']],
        ['key' => 'bcs', 'label' => 'BCS', 'module' => 'Body Condition Score', 'table' => 'bcs_records', 'aliases' => ['bcs']],
        ['key' => 'fhpr_breeding', 'label' => 'FHPR_Breeding', 'module' => 'FHPR Summary', 'table' => 'fhpr_records', 'aliases' => ['fhprbreeding']],
        ['key' => 'mating', 'label' => 'Mating', 'module' => 'Mating Attempts', 'table' => 'mating_records', 'aliases' => ['mating', 'matingattempts']],
        ['key' => 'mating_2', 'label' => 'Mating (2)', 'module' => 'Mating Attempts', 'table' => 'mating_records', 'aliases' => ['mating2']],
        ['key' => 'health_nutrition', 'label' => 'Health Nutrition', 'module' => 'Health & Nutrition', 'table' => 'health_records', 'aliases' => ['healthnutrition']],
        ['key' => 'milk', 'label' => 'Milk', 'module' => 'Milk Production', 'table' => 'milk_records', 'aliases' => ['milk', 'milkproduction']],
        ['key' => 'milk_2', 'label' => 'MILK (2)', 'module' => 'Milk Production', 'table' => 'milk_records', 'aliases' => ['milk2']],
        ['key' => 'meat', 'label' => 'Meat', 'module' => 'Meat Production', 'table' => 'cooperative_product_records', 'aliases' => ['meat', 'meatproduction']],
        ['key' => 'manure', 'label' => 'MANURE BASED-PRODUCTS', 'module' => 'Manure-Based Products', 'table' => 'cooperative_product_records', 'aliases' => ['manurebasedproducts']],
        ['key' => 'mtd', 'label' => 'MTD', 'module' => 'Milk Test Day MTD', 'table' => 'mtd_records', 'aliases' => ['mtd', 'milktestdaymtd']],
    ];

    public function create()
    {
        $historyTablesReady = Schema::hasTable('cooperative_workbook_imports')
            && Schema::hasTable('cooperative_workbook_import_sheets');
        if (! $historyTablesReady) {
            return view('imports.cooperative-workbook', [
                'cooperatives' => DB::table('cooperatives')->orderBy('cooperative_name')->get(),
                'imports' => collect(),
                'sheets' => self::SHEETS,
                'latestByCooperative' => [],
                'batches' => collect(),
                'pendingIdentifiers' => collect(),
                'historyTablesReady' => false,
                'bulkTablesReady' => false,
            ]);
        }

        $cooperatives = DB::table('cooperatives')->orderBy('cooperative_name')->get();
        $imports = DB::table('cooperative_workbook_imports as imports')
            ->leftJoin('cooperatives', 'cooperatives.id', '=', 'imports.cooperative_id')
            ->select('imports.*', 'cooperatives.cooperative_name')
            ->orderByDesc('imports.id')
            ->limit(30)
            ->get();

        $sheetsByImport = DB::table('cooperative_workbook_import_sheets')
            ->whereIn('workbook_import_id', $imports->pluck('id'))
            ->orderBy('id')
            ->get()
            ->groupBy('workbook_import_id');

        foreach ($imports as $import) {
            $import->sheets = $sheetsByImport->get($import->id, collect());
        }

        $batches = Schema::hasTable('cooperative_workbook_batches')
            ? DB::table('cooperative_workbook_batches')->orderByDesc('id')->limit(10)->get()
            : collect();
        if ($batches->isNotEmpty()) {
            $filesByBatch = DB::table('cooperative_workbook_batch_files as files')
                ->leftJoin('cooperatives', 'cooperatives.id', '=', 'files.cooperative_id')
                ->whereIn('files.batch_id', $batches->pluck('id'))
                ->orderBy('files.id')
                ->get([
                    'files.id', 'files.batch_id', 'files.original_filename', 'files.file_size',
                    'files.status', 'files.match_status', 'files.cooperative_id',
                    'files.match_evidence', 'files.missing_sheets', 'files.error_message',
                    'files.workbook_import_id', 'files.updated_at', 'cooperatives.cooperative_name',
                ])
                ->groupBy('batch_id');
            foreach ($batches as $batch) {
                $batch->files = $filesByBatch->get($batch->id, collect());
                foreach ($batch->files as $file) {
                    $file->retryable = $file->status === 'failed'
                        || ($file->status === 'processing'
                            && \Illuminate\Support\Carbon::parse($file->updated_at)->lte(now()->subMinutes(10)));
                }
            }
        }
        $pendingIdentifiers = collect();
        if (Schema::hasTable('unmapped_cooperative_identifiers')) {
            $pendingIdentifiers = DB::table('unmapped_cooperative_identifiers')
                ->where('status', 'pending')
                ->orderBy('source_label')
                ->limit(100)
                ->get();
            foreach ($pendingIdentifiers as $identifier) {
                $identifier->record_count = Schema::hasTable('unmapped_cooperative_records')
                    ? DB::table('unmapped_cooperative_records')->where('identifier_id', $identifier->id)->where('status', 'pending')->count()
                    : 0;
                $identifier->row_count = Schema::hasTable('pending_cooperative_workbook_rows')
                    ? DB::table('pending_cooperative_workbook_rows')->where('identifier_id', $identifier->id)->where('status', 'pending')->count()
                    : 0;
            }
        }

        $latestByCooperative = [];
        DB::table('cooperative_workbook_import_sheets as sheets')
            ->join('cooperative_workbook_imports as imports', 'imports.id', '=', 'sheets.workbook_import_id')
            ->whereNotNull('imports.cooperative_id')
            ->orderByDesc('sheets.id')
            ->limit(2000)
            ->get(['imports.cooperative_id', 'sheets.sheet_name', 'sheets.target_module', 'sheets.status', 'sheets.rows_imported', 'sheets.message'])
            ->each(function ($row) use (&$latestByCooperative) {
                foreach (self::SHEETS as $definition) {
                    if ($this->normalizeSheetName($definition['label']) !== $this->normalizeSheetName($row->sheet_name)) {
                        continue;
                    }

                    $latestByCooperative[$row->cooperative_id][$definition['key']] ??= $row;
                    break;
                }
            });

        return view('imports.cooperative-workbook', [
            'cooperatives' => $cooperatives,
            'imports' => $imports,
            'sheets' => self::SHEETS,
            'latestByCooperative' => $latestByCooperative,
            'batches' => $batches,
            'pendingIdentifiers' => $pendingIdentifiers,
            'historyTablesReady' => true,
            'bulkTablesReady' => Schema::hasTable('cooperative_workbook_batches')
                && Schema::hasTable('cooperative_workbook_batch_files'),
        ]);
    }

    public function import(Request $request)
    {
        abort_unless(
            Schema::hasTable('cooperative_workbook_imports')
                && Schema::hasTable('cooperative_workbook_import_sheets'),
            503,
            'Cooperative workbook import tables are missing. Run php artisan migrate, then reload this page.'
        );

        $data = $request->validate([
            'cooperative_id' => ['required', 'exists:cooperatives,id'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,xlsm', 'max:51200'],
        ]);
        return $this->processWorkbook((int) $data['cooperative_id'], $request->file('file'));
    }

    public function importSheet(Request $request, int $cooperativeId, string $sheetKey)
    {
        abort_unless(
            Schema::hasTable('cooperative_workbook_imports')
                && Schema::hasTable('cooperative_workbook_import_sheets'),
            503,
            'Cooperative workbook import tables are missing. Run php artisan migrate, then reload this page.'
        );
        abort_unless(DB::table('cooperatives')->where('id', $cooperativeId)->exists(), 404);
        $definition = collect(self::SHEETS)->firstWhere('key', $sheetKey);
        abort_unless($definition, 404);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,xlsm', 'max:51200'],
        ]);

        return $this->processWorkbook($cooperativeId, $data['file'], $sheetKey);
    }

    public function createBatch(Request $request)
    {
        $this->assertBulkTablesReady();
        $batchId = DB::table('cooperative_workbook_batches')->insertGetId([
            'uploaded_by' => $request->user()?->id,
            'status' => 'staging',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['batch_id' => $batchId]);
    }

    public function stageBatchFile(Request $request, int $batchId)
    {
        $this->assertBulkTablesReady();
        $batch = DB::table('cooperative_workbook_batches')->where('id', $batchId)->first();
        abort_unless($batch && $batch->status === 'staging', 404, 'This batch is no longer accepting files.');
        abort_if(
            DB::table('cooperative_workbook_batch_files')->where('batch_id', $batchId)->count() >= 100,
            422,
            'A batch can contain at most 100 workbooks. Use another batch for additional files.'
        );
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,xlsm', 'max:51200'],
        ]);
        $file = $data['file'];
        $hash = hash_file('sha256', $file->getRealPath());
        $duplicate = DB::table('cooperative_workbook_batch_files')
            ->where('batch_id', $batchId)
            ->where('file_hash', $hash)
            ->first();
        if ($duplicate) {
            return response()->json([
                'duplicate' => true,
                'file' => $this->bulkFileResponse($duplicate),
            ]);
        }

        $storedPath = $file->storeAs(
            'cooperative-workbook-batches/'.$batchId,
            Str::uuid().'-'.Str::limit(Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)), 150, '').'.'.$file->getClientOriginalExtension(),
            'local'
        );
        if (! $storedPath) {
            throw new \RuntimeException('The uploaded workbook could not be stored.');
        }

        try {
            $preview = $this->inspectBulkWorkbook(Storage::disk('local')->path($storedPath), $file->getClientOriginalName());
        } catch (Throwable $exception) {
            Log::warning('Bulk cooperative workbook preview failed.', [
                'batch_id' => $batchId,
                'filename' => $file->getClientOriginalName(),
                'exception' => $exception->getMessage(),
            ]);
            $preview = [
                'valid' => false,
                'match_status' => 'unknown',
                'cooperative_id' => null,
                'evidence' => [],
                'missing_sheets' => array_column(self::SHEETS, 'label'),
                'sheets' => [],
                'error' => 'The workbook could not be previewed. Verify that it is a valid, readable Excel file.',
            ];
        }

        try {
            $fileId = DB::table('cooperative_workbook_batch_files')->insertGetId([
                'batch_id' => $batchId,
                'original_filename' => Str::limit($file->getClientOriginalName(), 255, ''),
                'file_hash' => $hash,
                'file_path' => $storedPath,
                'file_size' => $file->getSize(),
                'status' => $preview['valid'] ? 'ready' : 'invalid',
                'match_status' => $preview['match_status'],
                'cooperative_id' => $preview['cooperative_id'],
                'match_evidence' => json_encode($preview['evidence'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'missing_sheets' => json_encode($preview['missing_sheets'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'sheet_summary' => json_encode($preview['sheets'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'error_message' => $preview['error'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPath);
            throw $exception;
        }

        DB::table('cooperative_workbook_batches')->where('id', $batchId)->increment('files_total');
        $staged = DB::table('cooperative_workbook_batch_files')->where('id', $fileId)->first();

        return response()->json(['duplicate' => false, 'file' => $this->bulkFileResponse($staged)]);
    }

    public function confirmBatch(Request $request, int $batchId)
    {
        $this->assertBulkTablesReady();
        $mappings = $request->validate([
            'cooperative_ids' => ['required', 'array'],
            'cooperative_ids.*' => ['required', 'integer', 'exists:cooperatives,id'],
        ])['cooperative_ids'];

        return DB::transaction(function () use ($batchId, $mappings) {
            $batch = DB::table('cooperative_workbook_batches')->where('id', $batchId)->lockForUpdate()->first();
            abort_unless($batch && $batch->status === 'staging', 404, 'This batch is not ready for confirmation.');
            $files = DB::table('cooperative_workbook_batch_files')
                ->where('batch_id', $batchId)
                ->where('status', 'ready')
                ->lockForUpdate()
                ->get();
            abort_if($files->isEmpty(), 422, 'This batch has no valid workbooks ready to import.');

            foreach ($files as $file) {
                $cooperativeId = $mappings[$file->id] ?? $file->cooperative_id;
                abort_if(! $cooperativeId, 422, "Select a cooperative for {$file->original_filename}.");
                DB::table('cooperative_workbook_batch_files')->where('id', $file->id)->update([
                    'cooperative_id' => $cooperativeId,
                    'status' => 'queued',
                    'updated_at' => now(),
                ]);
            }

            DB::table('cooperative_workbook_batches')->where('id', $batchId)->update([
                'status' => 'processing',
                'confirmed_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json(['batch_id' => $batchId, 'status' => 'processing']);
        });
    }

    public function importBatchFile(int $batchId, int $fileId)
    {
        $this->assertBulkTablesReady();
        $file = DB::table('cooperative_workbook_batch_files')
            ->where('id', $fileId)
            ->where('batch_id', $batchId)
            ->first();
        abort_unless($file && in_array($file->status, ['queued', 'failed'], true), 409, 'This workbook is not ready to process.');
        abort_unless(DB::table('cooperative_workbook_batches')->where('id', $batchId)->where('status', 'processing')->exists(), 409);
        abort_unless($file->cooperative_id && Storage::disk('local')->exists($file->file_path), 422, 'The staged workbook or cooperative mapping is unavailable.');

        $updated = DB::table('cooperative_workbook_batch_files')
            ->where('id', $fileId)
            ->whereIn('status', ['queued', 'failed'])
            ->update([
                'status' => 'processing',
                'error_message' => null,
                'updated_at' => now(),
            ]);
        abort_unless($updated, 409, 'This workbook is already being processed.');

        $uploadedFile = new UploadedFile(
            Storage::disk('local')->path($file->file_path),
            $file->original_filename,
            null,
            null,
            true
        );

        return $this->processWorkbook((int) $file->cooperative_id, $uploadedFile, null, $fileId);
    }

    public function retryBatchFile(int $batchId, int $fileId)
    {
        $this->assertBulkTablesReady();
        $updated = DB::table('cooperative_workbook_batch_files')
            ->where('id', $fileId)
            ->where('batch_id', $batchId)
            ->where(function ($query) {
                $query->where('status', 'failed')
                    ->orWhere(function ($stale) {
                        $stale->where('status', 'processing')
                            ->where('updated_at', '<=', now()->subMinutes(10));
                    });
            })
            ->update(['status' => 'queued', 'error_message' => null, 'updated_at' => now()]);
        abort_unless($updated, 409, 'Only a failed or stale workbook can be retried.');

        DB::table('cooperative_workbook_batches')->where('id', $batchId)->update([
            'status' => 'processing',
            'completed_at' => null,
            'updated_at' => now(),
        ]);
        $this->updateBatchStatus($batchId);

        return response()->json(['status' => 'queued']);
    }

    public function removeBatchFile(int $batchId, int $fileId)
    {
        $this->assertBulkTablesReady();
        $file = DB::table('cooperative_workbook_batch_files')
            ->where('id', $fileId)
            ->where('batch_id', $batchId)
            ->first();
        abort_unless($file && in_array($file->status, ['ready', 'invalid'], true), 409, 'Only a staged workbook can be removed.');
        abort_unless(DB::table('cooperative_workbook_batches')->where('id', $batchId)->where('status', 'staging')->exists(), 409);

        Storage::disk('local')->delete($file->file_path);
        DB::table('cooperative_workbook_batch_files')->where('id', $fileId)->delete();
        DB::table('cooperative_workbook_batches')->where('id', $batchId)->decrement('files_total');

        return response()->json(['status' => 'removed']);
    }

    public function mapUnmappedIdentifier(Request $request, int $identifierId)
    {
        abort_unless(Schema::hasTable('unmapped_cooperative_identifiers'), 503);
        $data = $request->validate([
            'cooperative_id' => ['required', 'integer', 'exists:cooperatives,id'],
        ]);
        [$updatedRecords, $sourceLabel] = DB::transaction(function () use ($identifierId, $data) {
            $identifier = DB::table('unmapped_cooperative_identifiers')
                ->where('id', $identifierId)
                ->lockForUpdate()
                ->first();
            abort_unless($identifier && $identifier->status === 'pending', 404, 'This cooperative identifier is not pending review.');

            DB::table('unmapped_cooperative_identifiers')->where('id', $identifierId)->update([
                'status' => 'mapped',
                'mapped_cooperative_id' => $data['cooperative_id'],
                'updated_at' => now(),
            ]);

            $links = DB::table('unmapped_cooperative_records')
                ->where('identifier_id', $identifierId)
                ->where('status', 'pending')
                ->get(['id', 'record_table', 'record_id']);
            $allowedTables = [
                'carabaos', 'bcs_records', 'cooperative_product_records', 'fhpr_records',
                'health_records', 'mating_records', 'milk_records', 'mtd_records', 'nature_of_exits',
            ];
            $recordIdsByTable = $links->groupBy('record_table');
            $updated = 0;
            foreach ($recordIdsByTable as $table => $records) {
                if (! in_array($table, $allowedTables, true)
                    || ! Schema::hasTable($table)
                    || ! Schema::hasColumn($table, 'cooperative_id')) {
                    continue;
                }
                foreach ($records->pluck('record_id')->chunk(500) as $recordIdChunk) {
                    $updated += DB::table($table)
                        ->whereIn('id', $recordIdChunk)
                        ->update(['cooperative_id' => $data['cooperative_id'], 'updated_at' => now()]);
                }
            }
            DB::table('unmapped_cooperative_records')
                ->where('identifier_id', $identifierId)
                ->where('status', 'pending')
                ->update(['status' => 'mapped', 'updated_at' => now()]);

            return [$updated, $identifier->source_label];
        });

        return redirect()->route('imports.cooperative-workbook.create')
            ->with('success', "Mapped {$sourceLabel} to the selected cooperative and updated {$updatedRecords} stored record(s). Pending workbook rows can be re-imported after mapping.");
    }

    public function cancelBatch(int $batchId)
    {
        $this->assertBulkTablesReady();
        $batch = DB::table('cooperative_workbook_batches')->where('id', $batchId)->first();
        abort_unless($batch && $batch->status === 'staging', 409, 'Only an unconfirmed batch can be cancelled.');

        $files = DB::table('cooperative_workbook_batch_files')->where('batch_id', $batchId)->get();
        foreach ($files as $file) {
            Storage::disk('local')->delete($file->file_path);
        }
        DB::table('cooperative_workbook_batches')->where('id', $batchId)->delete();

        return response()->json(['status' => 'cancelled']);
    }

    private function assertBulkTablesReady(): void
    {
        abort_unless(
            Schema::hasTable('cooperative_workbook_batches')
                && Schema::hasTable('cooperative_workbook_batch_files'),
            503,
            'Bulk workbook import tables are missing. Run php artisan migrate, then reload this page.'
        );
    }

    private function inspectBulkWorkbook(string $path, string $filename): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $allNames = $reader->listWorksheetNames($path);
        $knownSheets = [];
        foreach (self::SHEETS as $definition) {
            foreach ($allNames as $sheetName) {
                if (in_array($this->normalizeSheetName($sheetName), $definition['aliases'], true)) {
                    $knownSheets[] = $sheetName;
                    break;
                }
            }
        }
        if ($knownSheets === []) {
            return [
                'valid' => false,
                'match_status' => 'unknown',
                'cooperative_id' => null,
                'evidence' => [],
                'missing_sheets' => array_column(self::SHEETS, 'label'),
                'sheets' => [],
                'error' => 'No approved import worksheets were found.',
            ];
        }

        $reader->setLoadSheetsOnly($knownSheets);
        $workbook = $reader->load($path);
        $foundSheets = [];
        $identifiers = [];
        $summary = [];
        foreach ($workbook->getWorksheetIterator() as $worksheet) {
            $normalizedTitle = $this->normalizeSheetName($worksheet->getTitle());
            $definition = collect(self::SHEETS)->first(function ($item) use ($normalizedTitle) {
                return in_array($normalizedTitle, $item['aliases'], true);
            });
            if (! $definition) {
                continue;
            }
            $foundSheets[$definition['key']] = $worksheet->getTitle();
            $summary[] = $this->previewWorksheet($worksheet, $definition);
            if (! in_array($definition['key'], ['bd_all', 'milk', 'milk_2'], true)) {
                continue;
            }
            $rows = SpreadsheetWorksheetReader::rows($worksheet, 1, 30);
            $headerIndex = null;
            $identifierColumns = [];
            foreach ($rows as $rowIndex => $row) {
                foreach ($row as $columnIndex => $header) {
                    $name = $this->normalizeHeaderName((string) $header);
                    if (in_array($name, ['cooperative_code', 'coop_code', 'association_code', 'herd_code'], true)) {
                        $identifierColumns[$columnIndex] = 'code';
                    } elseif (in_array($name, [
                        'association_coop_institution', 'name_of_association_coop_institution',
                        'association_coop_module', 'cooperative_name', 'coop_name',
                        'association_name', 'name_of_cooperative', 'name_of_coop_association',
                    ], true)) {
                        $identifierColumns[$columnIndex] = 'name';
                    }
                }
                if ($identifierColumns !== []) {
                    $headerIndex = $rowIndex;
                    break;
                }
            }
            if ($headerIndex === null) {
                continue;
            }
            foreach (array_slice($rows, $headerIndex + 1) as $row) {
                foreach ($identifierColumns as $columnIndex => $type) {
                    $value = trim((string) ($row[$columnIndex] ?? ''));
                    if ($value !== '' && ! in_array($value, $identifiers[$type] ?? [], true)) {
                        $identifiers[$type][] = $value;
                    }
                }
            }
        }
        $workbook->disconnectWorksheets();

        $cooperatives = DB::table('cooperatives')->get(['id', 'cooperative_name', 'cooperative_code']);
        $matched = [];
        foreach ($cooperatives as $cooperative) {
            foreach ($identifiers['code'] ?? [] as $code) {
                if ($this->normalizeIdentifier($code) === $this->normalizeIdentifier((string) $cooperative->cooperative_code)) {
                    $matched[(int) $cooperative->id] = ['via' => 'workbook_code', 'value' => $code];
                }
            }
            foreach ($identifiers['name'] ?? [] as $name) {
                if ($this->normalizeIdentifier($name) === $this->normalizeIdentifier((string) $cooperative->cooperative_name)) {
                    $matched[(int) $cooperative->id] = ['via' => 'workbook_name', 'value' => $name];
                }
            }
        }
        $matchStatus = count($matched) === 1 ? 'matched' : (count($matched) > 1 ? 'ambiguous' : 'unknown');
        $cooperativeId = count($matched) === 1 ? (int) array_key_first($matched) : null;
        $missing = array_values(array_map(
            fn ($definition) => $definition['label'],
            array_filter(self::SHEETS, fn ($definition) => ! isset($foundSheets[$definition['key']]))
        ));

        return [
            'valid' => true,
            'match_status' => $matchStatus,
            'cooperative_id' => $cooperativeId,
            'evidence' => [
                'workbook_codes' => array_slice($identifiers['code'] ?? [], 0, 20),
                'workbook_names' => array_slice($identifiers['name'] ?? [], 0, 20),
                'candidates' => array_values(array_map(function ($id) use ($cooperatives, $matched) {
                    $cooperative = $cooperatives->firstWhere('id', $id);
                    return [
                        'id' => $id,
                        'name' => $cooperative?->cooperative_name,
                        'evidence' => $matched[$id] ?? null,
                    ];
                }, array_keys($matched))),
                'filename' => $filename,
            ],
            'missing_sheets' => $missing,
            'sheets' => $summary,
            'error' => null,
        ];
    }

    private function previewWorksheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $worksheet, array $definition): array
    {
        $table = $definition['table'];
        $columns = Schema::hasTable($table) ? Schema::getColumnListing($table) : [];
        $lastRow = $worksheet->getHighestDataRow();
        $firstRows = SpreadsheetWorksheetReader::rows($worksheet, 1, min(25, $lastRow));
        $headerIndex = null;
        $fieldMap = [];

        if ($definition['key'] === 'mtd') {
            $headerIndex = 1;
            $fieldMap = [1 => 'registry_number'];
        } else {
            foreach ($firstRows as $index => $candidate) {
                $candidateMap = [];
                foreach ($candidate as $columnIndex => $header) {
                    $field = $this->fieldForHeader((string) $header, $columns, $definition['key'], (int) $columnIndex);
                    if ($field !== null) {
                        $candidateMap[$columnIndex] = $field;
                    }
                }
                if (count(array_unique($candidateMap)) >= 2) {
                    $headerIndex = $index;
                    $fieldMap = $candidateMap;
                    break;
                }
            }
        }

        $rowsDetected = 0;
        $validRows = 0;
        $invalidRows = 0;
        $duplicateRows = 0;
        $seenRows = [];
        if ($headerIndex !== null) {
            $start = $headerIndex + 2;
            for ($offset = $start; $offset <= $lastRow; $offset += 500) {
                $chunk = SpreadsheetWorksheetReader::rows($worksheet, $offset, min($lastRow, $offset + 499));
                foreach ($chunk as $values) {
                    if (collect($values)->every(fn ($value) => $value === null || trim((string) $value) === '')) {
                        continue;
                    }
                    $rowsDetected++;
                    $hasMappedData = false;
                    foreach ($fieldMap as $columnIndex => $field) {
                        if (isset($values[$columnIndex]) && trim((string) $values[$columnIndex]) !== '') {
                            $hasMappedData = true;
                            break;
                        }
                    }
                    $isValid = $hasMappedData;
                    $requiredDate = [
                        'mating' => 'mating_date',
                        'mating_2' => 'mating_date',
                        'health_nutrition' => 'monitoring_date',
                        'milk' => 'record_date',
                        'milk_2' => 'record_date',
                    ][$definition['key']] ?? null;
                    if ($requiredDate && ! in_array($requiredDate, $fieldMap, true)) {
                        $isValid = false;
                    }
                    if (in_array($definition['key'], ['bcs', 'fhpr_breeding'], true)) {
                        $isValid = $isValid && in_array('registry_number', $fieldMap, true);
                    }
                    if (in_array($definition['key'], ['milk', 'milk_2'], true)) {
                        foreach (['farmer_name', 'collection_period', 'total_liters', 'number_of_cows', 'price_per_kg', 'milk_traded'] as $requiredField) {
                            $isValid = $isValid && in_array($requiredField, $fieldMap, true);
                        }
                    }
                    if ($isValid) {
                        $validRows++;
                    } else {
                        $invalidRows++;
                    }
                    $signature = hash('sha256', json_encode(array_map(
                        fn ($value) => is_scalar($value) ? trim((string) $value) : '',
                        $values
                    )));
                    if (isset($seenRows[$signature])) {
                        $duplicateRows++;
                    } else {
                        $seenRows[$signature] = true;
                    }
                }
            }
        } else {
            $rowsDetected = max(0, $lastRow - 1);
            $invalidRows = $rowsDetected;
        }

        return [
            'key' => $definition['key'],
            'label' => $definition['label'],
            'worksheet' => $worksheet->getTitle(),
            'rows_detected' => $rowsDetected,
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'duplicate_rows' => $duplicateRows,
            'validation' => $headerIndex === null ? 'unrecognized headers' : 'structural validation',
        ];
    }

    private function normalizeIdentifier(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii(trim($value)))));
    }

    private function resolveCooperativeLabel(string $label): array
    {
        $normalized = $this->normalizeIdentifier($label);
        if ($normalized === '') {
            return [null, null];
        }
        if ($this->cooperativeIdentifierIndex === null) {
            $this->cooperativeIdentifierIndex = [];
            foreach (DB::table('cooperatives')->get(['id', 'cooperative_name', 'cooperative_code']) as $cooperative) {
                foreach ([$cooperative->cooperative_name, $cooperative->cooperative_code] as $value) {
                    $key = $this->normalizeIdentifier((string) $value);
                    if ($key !== '') {
                        $this->cooperativeIdentifierIndex[$key][] = (int) $cooperative->id;
                    }
                }
            }
            foreach ($this->cooperativeIdentifierIndex as &$ids) {
                $ids = array_values(array_unique($ids));
            }
            unset($ids);
        }

        $matches = $this->cooperativeIdentifierIndex[$normalized] ?? [];
        if (count($matches) === 1) {
            return [$matches[0], null];
        }
        if (! Schema::hasTable('unmapped_cooperative_identifiers')) {
            return [null, null];
        }

        $identifier = DB::table('unmapped_cooperative_identifiers')->where('normalized_label', $normalized)->first();
        if ($identifier && $identifier->status === 'mapped' && $identifier->mapped_cooperative_id) {
            return [(int) $identifier->mapped_cooperative_id, null];
        }
        if (! $identifier) {
            $id = DB::table('unmapped_cooperative_identifiers')->insertGetId([
                'source_label' => Str::limit(trim($label), 255, ''),
                'normalized_label' => $normalized,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [null, (int) $id];
        }

        return [null, (int) $identifier->id];
    }

    private function bulkFileResponse(object $file): array
    {
        return [
            'id' => $file->id,
            'filename' => $file->original_filename,
            'size' => (int) $file->file_size,
            'status' => $file->status,
            'match_status' => $file->match_status,
            'cooperative_id' => $file->cooperative_id ? (int) $file->cooperative_id : null,
            'evidence' => json_decode($file->match_evidence ?? '[]', true) ?: [],
            'missing_sheets' => json_decode($file->missing_sheets ?? '[]', true) ?: [],
            'sheets' => json_decode($file->sheet_summary ?? '[]', true) ?: [],
            'error' => $file->error_message,
        ];
    }

    public function clearHistory()
    {
        abort_unless(
            Schema::hasTable('cooperative_workbook_imports')
                && Schema::hasTable('cooperative_workbook_import_sheets'),
            503,
            'Cooperative workbook import tables are missing. Run php artisan migrate, then reload this page.'
        );

        $deleted = DB::table('cooperative_workbook_imports')->count();
        DB::table('cooperative_workbook_imports')->delete();

        return redirect()->route('imports.cooperative-workbook.create')
            ->with('success', "Cleared {$deleted} workbook progress record(s). Imported cooperative data was not changed.");
    }

    private function processWorkbook(int $cooperativeId, UploadedFile $file, ?string $selectedSheet = null, ?int $batchFileId = null)
    {
        $importId = DB::table('cooperative_workbook_imports')->insertGetId([
            'cooperative_id' => $cooperativeId,
            'file_name' => Str::limit($file->getClientOriginalName(), 255, ''),
            'status' => 'processing',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($batchFileId !== null) {
            DB::table('cooperative_workbook_batch_files')->where('id', $batchFileId)->update([
                'workbook_import_id' => $importId,
                'updated_at' => now(),
            ]);
        }

        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $allowedAliases = $selectedSheet === null
                ? collect(self::SHEETS)->flatMap(fn ($definition) => $definition['aliases'])->unique()->values()->all()
                : (collect(self::SHEETS)->firstWhere('key', $selectedSheet)['aliases'] ?? []);
            if ($selectedSheet === 'bd_all') {
                $allowedAliases[] = $this->normalizeSheetName('FHPR_Breeding');
            }
            $sheetNames = array_values(array_filter(
                $reader->listWorksheetNames($file->getRealPath()),
                fn ($name) => in_array($this->normalizeSheetName($name), $allowedAliases, true)
            ));
            if ($sheetNames === []) {
                throw new \RuntimeException('No approved import worksheets were found.');
            }
            if ($sheetNames !== []) {
                $reader->setLoadSheetsOnly($sheetNames);
            }
            $workbook = $reader->load($file->getRealPath());
        } catch (Throwable $exception) {
            Log::warning('Cooperative workbook could not be opened.', [
                'import_id' => $importId,
                'exception' => $exception->getMessage(),
            ]);
            DB::table('cooperative_workbook_imports')->where('id', $importId)->update([
                'status' => 'failed',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
            $this->recordSheet($importId, 'Workbook', null, 'failed', 0, 0, 'Could not open the workbook. Check that it is a valid Excel file.');
            if ($batchFileId !== null) {
                DB::table('cooperative_workbook_batch_files')->where('id', $batchFileId)->update([
                    'status' => 'failed',
                    'error_message' => 'Could not open the workbook. Check that it is a valid Excel file.',
                    'processed_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->updateBatchStatus((int) DB::table('cooperative_workbook_batch_files')->where('id', $batchFileId)->value('batch_id'));

                return response()->json(['status' => 'failed', 'import_id' => $importId], 422);
            }

            return redirect()->route('imports.cooperative-workbook.create')
                ->with('error', 'The workbook could not be opened. Check that it is a valid Excel file and try again.');
        }

        $rowsImported = 0;
        $foundSheets = [];
        foreach ($workbook->getWorksheetIterator() as $worksheet) {
            $foundSheets[$this->normalizeSheetName($worksheet->getTitle())] = $worksheet;
        }

        foreach (self::SHEETS as $definition) {
            if ($selectedSheet !== null && $definition['key'] !== $selectedSheet) {
                continue;
            }

            $worksheet = null;
            foreach ($definition['aliases'] as $alias) {
                if (isset($foundSheets[$alias])) {
                    $worksheet = $foundSheets[$alias];
                    break;
                }
            }

            if (! $worksheet) {
                $this->recordSheet($importId, $definition['label'], $definition['module'], 'missing', 0, 0, 'This worksheet was not found in the workbook.');
                continue;
            }

            $sheetId = DB::table('cooperative_workbook_import_sheets')->insertGetId([
                'workbook_import_id' => $importId,
                'sheet_name' => $worksheet->getTitle(),
                'target_module' => $definition['module'],
                'status' => 'processing',
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            try {
                $rowCounters = [
                    'rows_inserted' => 0,
                    'rows_updated' => 0,
                    'rows_skipped' => 0,
                    'rows_rejected' => 0,
                    'rows_unmapped' => 0,
                ];
                if ($definition['key'] === 'bd_all') {
                    $breedingWorksheet = $foundSheets[$this->normalizeSheetName('FHPR_Breeding')] ?? null;
                    $result = app(CooperativeImportController::class)->importInventoryWorksheet(
                        SpreadsheetWorksheetReader::rows($worksheet),
                        $breedingWorksheet ? SpreadsheetWorksheetReader::rows($breedingWorksheet) : null,
                        $cooperativeId,
                        function (array $record) use ($cooperativeId) {
                            $label = trim((string) ($record['association_coop_institution'] ?? ''));
                            if ($label === '') {
                                return ['cooperative_id' => $cooperativeId, 'identifier_id' => null];
                            }
                            [$rowCooperativeId, $identifierId] = $this->resolveCooperativeLabel($label);

                            return ['cooperative_id' => $rowCooperativeId, 'identifier_id' => $identifierId];
                        },
                        function (array $record, ?int $identifierId) use ($batchFileId, $importId, $sheetId) {
                            if ($identifierId !== null) {
                                $this->savePendingWorkbookRow(
                                    $identifierId,
                                    $batchFileId,
                                    $importId,
                                    $sheetId,
                                    'carabaos',
                                    (int) ($record['source_row'] ?? 0),
                                    $record
                                );
                            }
                        }
                    );
                    $rowsSeen = $result['rows'];
                    $rowsImported = $result['animals'];
                    $message = "{$result['animals']} animals processed; {$result['farmers']} new farmers added and {$result['matched']} existing farmers matched. Existing animal history was preserved.";
                    if (($result['unmapped'] ?? 0) > 0) {
                        $message .= " {$result['unmapped']} row(s) were held pending cooperative verification.";
                    }
                    $rowCounters = [
                        'rows_inserted' => $result['inserted'] ?? 0,
                        'rows_updated' => $result['updated'] ?? 0,
                        'rows_skipped' => $result['skipped'] ?? 0,
                        'rows_rejected' => max(0, ($result['skipped'] ?? 0) - ($result['unmapped'] ?? 0)),
                        'rows_unmapped' => $result['unmapped'] ?? 0,
                    ];
                } else {
                    [$rowsSeen, $rowsImported, $message, $rowCounters] = $this->importRecords(
                        $worksheet,
                        $definition['table'],
                        $definition['key'],
                        $cooperativeId,
                        $sheetId,
                        $batchFileId,
                        $importId
                    );
                }

                $sheetUpdate = [
                    'status' => $rowsImported > 0 ? 'completed' : 'empty',
                    'rows_seen' => $rowsSeen,
                    'rows_imported' => $rowsImported,
                    ...$rowCounters,
                    'message' => $message,
                    'completed_at' => now(),
                    'updated_at' => now(),
                ];
                DB::table('cooperative_workbook_import_sheets')->where('id', $sheetId)->update(
                    array_intersect_key($sheetUpdate, array_flip([
                        'status', 'rows_seen', 'rows_imported', 'message', 'completed_at', 'updated_at',
                        ...$this->availableImportSheetCounterColumns(),
                    ]))
                );
            } catch (Throwable $exception) {
                Log::error('Cooperative workbook sheet import failed.', [
                    'import_id' => $importId,
                    'sheet' => $worksheet->getTitle(),
                    'exception' => $exception->getMessage(),
                ]);
                DB::table('cooperative_workbook_import_sheets')->where('id', $sheetId)->update([
                    'status' => 'failed',
                    'message' => Str::limit($exception->getMessage(), 1000),
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $workbook->disconnectWorksheets();
        $failed = DB::table('cooperative_workbook_import_sheets')
            ->where('workbook_import_id', $importId)
            ->where('status', 'failed')
            ->exists();
        $emptyOrMissing = $selectedSheet !== null && DB::table('cooperative_workbook_import_sheets')
            ->where('workbook_import_id', $importId)
            ->whereIn('status', ['empty', 'missing'])
            ->exists();
        $failureDetails = DB::table('cooperative_workbook_import_sheets')
            ->where('workbook_import_id', $importId)
            ->where('status', 'failed')
            ->value('message');
        DB::table('cooperative_workbook_imports')->where('id', $importId)->update([
            'status' => ($failed || $emptyOrMissing) ? 'completed_with_errors' : 'completed',
            'completed_at' => now(),
            'updated_at' => now(),
        ]);
        if ($batchFileId !== null) {
            $importStatus = ($failed || $emptyOrMissing) ? 'completed_with_errors' : 'completed';
            DB::table('cooperative_workbook_batch_files')->where('id', $batchFileId)->update([
                'status' => $importStatus === 'completed' ? 'completed' : 'failed',
                'error_message' => $failureDetails,
                'processed_at' => now(),
                'updated_at' => now(),
            ]);
            $this->updateBatchStatus((int) DB::table('cooperative_workbook_batch_files')->where('id', $batchFileId)->value('batch_id'));
            $sheetResultColumns = [
                'sheet_name', 'target_module', 'status', 'rows_seen', 'rows_imported',
                'message', ...$this->availableImportSheetCounterColumns(),
            ];
            $sheetResults = DB::table('cooperative_workbook_import_sheets')
                ->where('workbook_import_id', $importId)
                ->orderBy('id')
                ->get($sheetResultColumns);

            return response()->json([
                'status' => $importStatus,
                'import_id' => $importId,
                'sheets' => $sheetResults,
            ]);
        }

        return redirect()->route('imports.cooperative-workbook.create')
            ->with(($failed || $emptyOrMissing) ? 'error' : 'success', $failed
                ? 'Import failed: '.($failureDetails ?: 'Review the import progress for details.')
                : ($emptyOrMissing
                    ? ($selectedSheet === 'bd_all'
                        ? 'No animal data was imported. Verify that BD_ALL has Farmer/Recipient and Name of Animal columns with animal rows beneath them.'
                        : 'No worksheet rows were imported. Check that the selected worksheet contains data.')
                : ($selectedSheet === 'bd_all'
                    ? "BD_ALL import finished: {$rowsImported} animal row(s) added or updated."
                    : 'Workbook import finished. Review each worksheet result below.')));
    }

    private function updateBatchStatus(int $batchId): void
    {
        $files = DB::table('cooperative_workbook_batch_files')->where('batch_id', $batchId);
        $filesCompleted = (clone $files)->where('status', 'completed')->count();
        $filesFailed = (clone $files)->where('status', 'failed')->count();
        $remaining = (clone $files)->whereIn('status', ['queued', 'processing'])->exists();
        DB::table('cooperative_workbook_batches')->where('id', $batchId)->update([
            'files_completed' => $filesCompleted,
            'files_failed' => $filesFailed,
            'status' => $remaining ? 'processing' : 'completed',
            'completed_at' => $remaining ? null : now(),
            'updated_at' => now(),
        ]);
    }

    private function importRecords(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $source,
        string $table,
        string $sheetKey,
        int $cooperativeId,
        int $sheetId,
        ?int $batchFileId,
        int $importId
    ): array
    {
        abort_unless(Schema::hasTable($table), 422, "Destination table '{$table}' is not available.");
        if ($sheetKey === 'mtd') {
            return $this->importMtdRecords($source, $table, $cooperativeId);
        }

        $headerRows = SpreadsheetWorksheetReader::rows($source, 1, min(25, $source->getHighestDataRow()));
        $columns = Schema::getColumnListing($table);
        $headerIndex = null;
        $fieldMap = [];

        foreach ($headerRows as $index => $candidate) {
            $candidateMap = [];
            foreach ($candidate as $columnIndex => $header) {
                $field = $this->fieldForHeader((string) $header, $columns, $sheetKey, (int) $columnIndex);
                if ($field !== null) {
                    $candidateMap[$columnIndex] = $field;
                }
            }
            if (count(array_unique($candidateMap)) >= 2) {
                $headerIndex = $index;
                $fieldMap = $candidateMap;
                break;
            }
        }

        abort_if($headerIndex === null, 422, 'Could not identify the column headers in this worksheet.');

        $requiredDate = [
            'mating' => 'mating_date',
            'mating_2' => 'mating_date',
            'health_nutrition' => 'monitoring_date',
            'milk' => 'record_date',
            'milk_2' => 'record_date',
        ][$sheetKey] ?? null;
        abort_if($requiredDate && ! in_array($requiredDate, $fieldMap, true), 422, "The worksheet is missing a recognized {$requiredDate} column.");

        $farmersByName = [];
        $recordsByKey = [];
        $rowsSeen = 0;
        $imported = 0;
        $inserted = 0;
        $updated = 0;
        $rowsUnmapped = 0;
        $skipped = 0;
        $skipReasons = [];
        $skipRow = function (string $reason) use (&$skipped, &$skipReasons): void {
            $skipped++;
            $skipReasons[$reason] = ($skipReasons[$reason] ?? 0) + 1;
        };
        foreach ($this->worksheetDataRows($source, $headerIndex + 2) as [$rowIndex, $values]) {
            if (collect($values)->every(fn ($value) => $value === null || trim((string) $value) === '')) {
                continue;
            }
            $rowsSeen++;

            $data = [];
            $sourceData = [];
            foreach ($fieldMap as $columnIndex => $field) {
                $value = $values[$columnIndex] ?? null;
                if ($value === null || trim((string) $value) === '') {
                    continue;
                }
                if (in_array($field, $columns, true)) {
                    $databaseValue = $this->databaseValue($value, $field);
                    if ($field === 'collection_period') {
                        $databaseValue = $this->normalizeCollectionPeriod($value);
                    }
                    $data[$field] = $databaseValue;
                }
            }

            foreach ($headerRows[$headerIndex] as $columnIndex => $header) {
                $value = $values[$columnIndex] ?? null;
                $normalizedHeader = $this->normalizeHeaderName((string) $header);
                if ($value !== null && trim((string) $value) !== '') {
                    $sourceKey = (string) $header;
                    if (array_key_exists($sourceKey, $sourceData)) {
                        $sourceKey .= ' ['.$columnIndex.']';
                    }
                    $sourceData[$sourceKey] = $value;
                }
            }
            foreach (array_slice($values, count($headerRows[$headerIndex]), null, true) as $columnIndex => $value) {
                if ($value !== null && trim((string) $value) !== '') {
                    $sourceData['column_'.$columnIndex] = $value;
                }
            }

            $rowCooperativeId = $cooperativeId;
            $unmappedIdentifierId = null;
            $sourceCooperativeLabel = $this->sourceDataValue($sourceData, [
                'association_coop_institution', 'name_of_association_coop_institution',
                'association_coop_module', 'cooperative_name', 'coop_name', 'association_name',
                'name_of_coop_association', 'name_of_cooperative',
            ]);
            $sourceCooperativeCode = $this->sourceDataValue($sourceData, [
                'herd_code', 'cooperative_code', 'coop_code', 'association_code',
            ]);
            if ($sourceCooperativeCode !== null) {
                [$matchedCodeId, $unmappedIdentifierId] = $this->resolveCooperativeLabel($sourceCooperativeCode);
                if ($matchedCodeId !== null) {
                    $rowCooperativeId = $matchedCodeId;
                }
            }
            if ($sourceCooperativeLabel !== null) {
                [$matchedLabelId, $labelIdentifierId] = $this->resolveCooperativeLabel($sourceCooperativeLabel);
                if ($matchedLabelId !== null || $rowCooperativeId === null) {
                    $rowCooperativeId = $matchedLabelId;
                    $unmappedIdentifierId = $labelIdentifierId;
                }
                $sourceData['_cooperative_source_label'] = $sourceCooperativeLabel;
            } elseif ($sourceCooperativeCode !== null) {
                $sourceData['_cooperative_source_label'] = $sourceCooperativeCode;
            }

            if ($sheetKey === 'bd_out') {
                if (in_array('registry_number', $columns, true) && empty($data['registry_number'])) {
                    $data['registry_number'] = $this->sourceDataValue($sourceData, [
                        'register_id_no', 'register_no', 'registry_no', 'registry_number',
                        'registry_id', 'registry_id_no', 'registration_no', 'animal_id',
                    ]);
                }
                if (in_array('nature_of_exit', $columns, true) && empty($data['nature_of_exit'])) {
                    $data['nature_of_exit'] = $this->sourceDataValue($sourceData, [
                        'nature_of_exit', 'nature_for_exit', 'reason_of_exit', 'reason_for_exit',
                        'exit_reason', 'nature_of_disposal',
                    ]);
                }
                if (in_array('exit_date', $columns, true) && empty($data['exit_date'])) {
                    $exitDate = $this->sourceDataValue($sourceData, [
                        'date_of_exit', 'date_of_disposal_death', 'exit_date', 'date_of_disposal',
                    ]);
                    if ($exitDate !== null) {
                        $data['exit_date'] = $this->databaseValue($exitDate, 'exit_date');
                    }
                }
                if (empty($data['carabao_id'])) {
                    $identifier = trim((string) ($data['registry_number'] ?? ''));
                    $data['carabao_id'] = $rowCooperativeId && $identifier !== ''
                        ? $this->findCarabao($rowCooperativeId, $identifier)?->id
                        : null;
                }
            }

            if ($data === []) {
                $skipRow('no mapped data');
                continue;
            }
            if ($sourceCooperativeLabel !== null) {
                $data['_source_cooperative_label'] = $sourceCooperativeLabel;
            }

            if (in_array('cooperative_id', $columns, true)) {
                $data['cooperative_id'] = $rowCooperativeId;
            }
            if ($rowCooperativeId === null) {
                foreach (['carabao_id', 'farmer_id'] as $relationshipField) {
                    if (in_array($relationshipField, $columns, true)) {
                        $data[$relationshipField] = null;
                    }
                }
            }
            if (in_array($sheetKey, ['meat', 'manure'], true) && in_array('product_type', $columns, true)) {
                $data['product_type'] = $sheetKey;
            }
            if (in_array('source_data', $columns, true)) {
                $data['source_data'] = json_encode($sourceData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            if (in_array('farmer_id', $columns, true) && ! empty($data['farmer_name'])) {
                if ($rowCooperativeId !== null && ! array_key_exists($rowCooperativeId, $farmersByName)) {
                    $farmersByName[$rowCooperativeId] = $this->farmerIndex($rowCooperativeId);
                }
                $cooperativeFarmers = $rowCooperativeId !== null ? ($farmersByName[$rowCooperativeId] ?? []) : [];
                $farmerIds = $cooperativeFarmers[$this->normalizePersonName($data['farmer_name'])] ?? [];
                if (count($farmerIds) === 1) {
                    $data['farmer_id'] = $farmerIds[0];
                }
            }

            if (in_array('registry_number', $columns, true)) {
                $registry = trim((string) ($data['registry_number'] ?? ''));
                if ($registry !== '') {
                    $data['registry_number'] = $registry;
                    $carabao = $rowCooperativeId ? $this->findCarabao($rowCooperativeId, $registry) : null;
                    if ($carabao && in_array('carabao_id', $columns, true)) {
                        $data['carabao_id'] = $carabao->id;
                    }
                }
            }

            if (in_array('carabao_id', $columns, true) && empty($data['carabao_id'])) {
                $identifier = trim((string) ($data['registry_number'] ?? ''));
                if ($identifier !== '' && $rowCooperativeId !== null) {
                    $carabao = $this->findCarabao($rowCooperativeId, $identifier);
                    $data['carabao_id'] = $carabao?->id;
                }
            }

            if ($sheetKey === 'fhpr_breeding' && empty($data['registry_number'])) {
                $skipRow('missing registry number');
                continue;
            }
            if ($sheetKey === 'bd_out' && empty($data['carabao_id'])) {
                if ($unmappedIdentifierId !== null) {
                    $this->savePendingWorkbookRow(
                        $unmappedIdentifierId,
                        $batchFileId,
                        $importId,
                        $sheetId,
                        $table,
                        (int) $rowIndex,
                        $sourceData
                    );
                }
                $skipRow('no identified animal');
                continue;
            }
            if (in_array($sheetKey, ['mating', 'mating_2', 'health_nutrition'], true) && empty($data['carabao_id'])) {
                if ($unmappedIdentifierId !== null) {
                    $this->savePendingWorkbookRow(
                        $unmappedIdentifierId,
                        $batchFileId,
                        $importId,
                        $sheetId,
                        $table,
                        (int) $rowIndex,
                        $sourceData
                    );
                }
                $skipRow('no identified animal');
                continue;
            }
            if ($sheetKey === 'mtd' && empty($data['test_date'])) {
                $skipRow('missing test date');
                continue;
            }
            if ($sheetKey === 'bcs' && (empty($data['registry_number']) || empty($data['body_condition_score']))) {
                $skipRow('missing registry number or BCS');
                continue;
            }
            if (in_array($sheetKey, ['meat', 'manure'], true)) {
                $hasProductData = ! empty($data['actual_date']) || ! empty($data['reported_date']);
                foreach (['volume_kg', 'other_source_volume_kg', 'price_per_kg', 'value_php'] as $field) {
                    if (isset($data[$field]) && (float) $data[$field] !== 0.0) {
                        $hasProductData = true;
                        break;
                    }
                }
                if (! $hasProductData) {
                    $skipRow('no production data');
                    continue;
                }
            }
            $hasPregnancyCheckWithoutServiceDate = in_array($sheetKey, ['mating', 'mating_2'], true)
                && ! empty($data['registry_number'])
                && ! empty($data['pregnancy_check_date'])
                && ! empty($data['pregnancy_result']);
            if ($requiredDate && empty($data[$requiredDate]) && ! $hasPregnancyCheckWithoutServiceDate) {
                $skipRow('missing required date');
                continue;
            }
            if (in_array($sheetKey, ['milk', 'milk_2'], true)) {
                $requiredMilkFields = ['farmer_name', 'collection_period', 'total_liters', 'number_of_cows', 'price_per_kg', 'milk_traded'];
                if (collect($requiredMilkFields)->contains(fn ($field) => ! isset($data[$field]) || $data[$field] === '')) {
                    $skipRow('missing or invalid milk production data');
                    continue;
                }
            }

            $recordKey = $this->recordKey($sheetKey, $data);
            if ($recordKey !== null && array_key_exists($recordKey, $recordsByKey)) {
                $skipRow('duplicate worksheet record');
            }
            if ($unmappedIdentifierId !== null) {
                $rowsUnmapped++;
                $data['_unmapped_identifier_id'] = $unmappedIdentifierId;
            }
            $recordsByKey[$recordKey ?? 'row-'.$rowsSeen] = $data;
            if (count($recordsByKey) >= 500) {
                $chunkCounts = $this->persistRecordChunk($recordsByKey, $table, $sheetKey);
                $inserted += $chunkCounts['inserted'];
                $updated += $chunkCounts['updated'];
                $imported += $chunkCounts['inserted'] + $chunkCounts['updated'];
                $recordsByKey = [];
            }
        }

        $chunkCounts = $this->persistRecordChunk($recordsByKey, $table, $sheetKey);
        $inserted += $chunkCounts['inserted'];
        $updated += $chunkCounts['updated'];
        $imported += $chunkCounts['inserted'] + $chunkCounts['updated'];

        $message = "{$imported} row(s) added or updated; existing records absent from the workbook were left intact.";
        if ($skipped > 0) {
            $details = collect($skipReasons)
                ->map(fn ($count, $reason) => "{$count} {$reason}")
                ->implode(', ');
            $message .= " {$skipped} row(s) skipped".($details !== '' ? ": {$details}." : '.');
        }

        $rejected = max(0, $skipped - ($skipReasons['duplicate worksheet record'] ?? 0));

        return [
            $rowsSeen,
            $imported,
            $message,
            [
                'rows_inserted' => $inserted,
                'rows_updated' => $updated,
                'rows_skipped' => $skipped,
                'rows_rejected' => $rejected,
                'rows_unmapped' => $rowsUnmapped,
            ],
        ];
    }

    private function persistRecordChunk(array $records, string $table, string $sheetKey): array
    {
        return DB::transaction(function () use ($records, $table, $sheetKey) {
            $inserted = 0;
            $updated = 0;
            foreach ($records as $record) {
                $unmappedIdentifierId = $record['_unmapped_identifier_id'] ?? null;
                $sourceCooperativeLabel = $record['_source_cooperative_label'] ?? null;
                unset($record['_unmapped_identifier_id']);
                unset($record['_source_cooperative_label']);
                $record['updated_at'] = now();
                $recordCooperativeId = isset($record['cooperative_id']) ? (int) $record['cooperative_id'] : null;
                $match = $this->matchingRecord($table, $sheetKey, $record, $recordCooperativeId, $sourceCooperativeLabel);
                if ($match) {
                    DB::table($table)->where('id', $match->id)->update($record);
                    $recordId = (int) $match->id;
                    $updated++;
                } else {
                    $record['created_at'] = now();
                    $recordId = (int) DB::table($table)->insertGetId($record);
                    $inserted++;
                }
                if ($unmappedIdentifierId !== null && Schema::hasTable('unmapped_cooperative_records')) {
                    DB::table('unmapped_cooperative_records')->insertOrIgnore([
                        'identifier_id' => $unmappedIdentifierId,
                        'record_table' => $table,
                        'record_id' => $recordId,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                if ($sheetKey === 'fhpr_breeding' && ! empty($record['registry_number']) && ! empty($record['status_updated'])) {
                    DB::table('carabaos')
                        ->where('cooperative_id', $recordCooperativeId)
                        ->where('registry_number', $record['registry_number'])
                        ->update(['classification' => $record['status_updated'], 'updated_at' => now()]);
                }
            }

            return ['inserted' => $inserted, 'updated' => $updated];
        });
    }

    private function worksheetDataRows(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $worksheet, int $startRow): \Generator
    {
        $lastRow = $worksheet->getHighestDataRow();
        for ($chunkStart = $startRow; $chunkStart <= $lastRow; $chunkStart += 500) {
            $chunkEnd = min($lastRow, $chunkStart + 499);
            foreach (SpreadsheetWorksheetReader::rows($worksheet, $chunkStart, $chunkEnd) as $offset => $values) {
                yield [$chunkStart + $offset, $values];
            }
        }
    }

    private function importMtdRecords(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $worksheet,
        string $table,
        int $cooperativeId
    ): array
    {
        abort_if($worksheet->getHighestDataRow() < 3, 422, 'The MTD worksheet does not contain its two header rows and data.');

        $columns = Schema::getColumnListing($table);
        $recordsByKey = [];
        $rowsSeen = 0;
        $skipped = 0;
        $rejectedRows = 0;
        $imported = 0;
        $inserted = 0;
        $updated = 0;
        foreach ($this->worksheetDataRows($worksheet, 3) as [, $values]) {
            if (collect($values)->every(fn ($value) => $value === null || trim((string) $value) === '')) {
                continue;
            }
            $rowsSeen++;

            $registry = trim((string) ($values[1] ?? ''));
            if ($registry === '') {
                $skipped++;
                $rejectedRows++;
                continue;
            }

            $dryoffDate = $this->databaseValue($values[6] ?? null, 'dryoff_date');
            $carabaoId = $this->findCarabao($cooperativeId, $registry)?->id;
            if (! $carabaoId) {
                $skipped++;
                $rejectedRows++;
                continue;
            }
            for ($columnIndex = 7; $columnIndex < count($values); $columnIndex += 4) {
                $testDate = $this->databaseValue($values[$columnIndex] ?? null, 'test_date');
                if ($testDate === null) {
                    continue;
                }

                $testDay = intdiv($columnIndex - 7, 4) + 1;
                $record = [
                    'cooperative_id' => $cooperativeId,
                    'carabao_id' => $carabaoId,
                    'registry_number' => $registry,
                    'test_date' => $testDate,
                    'dryoff_date' => $dryoffDate,
                    'test_day' => $testDay,
                    'days_in_milk' => $this->databaseValue($values[$columnIndex + 1] ?? null, 'days_in_milk'),
                    'mtd_value' => $this->databaseValue($values[$columnIndex + 2] ?? null, 'mtd_value'),
                    'milk_yield_liters' => $this->databaseValue($values[$columnIndex + 3] ?? null, 'milk_yield_liters'),
                    'remarks' => 'TD'.$testDay,
                ];
                if (in_array('source_data', $columns, true)) {
                    $record['source_data'] = json_encode([
                        'year' => $values[0] ?? null,
                        'registry_number' => $registry,
                        'doc' => $values[2] ?? null,
                        'tmp' => $values[3] ?? null,
                        '305d_milk' => $values[4] ?? null,
                        'll' => $values[5] ?? null,
                        'dryoff_date' => $values[6] ?? null,
                        'test_day' => $testDay,
                        'dot' => $values[$columnIndex] ?? null,
                        'dim' => $values[$columnIndex + 1] ?? null,
                        'mtd' => $values[$columnIndex + 2] ?? null,
                        'yield' => $values[$columnIndex + 3] ?? null,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }

                $record = array_intersect_key($record, array_flip($columns));
                $key = $this->recordKey('mtd', $record);
                if (array_key_exists($key, $recordsByKey)) {
                    $skipped++;
                }
                $recordsByKey[$key] = $record;
                if (count($recordsByKey) >= 500) {
                    $chunkCounts = $this->persistRecordChunk($recordsByKey, $table, 'mtd');
                    $inserted += $chunkCounts['inserted'];
                    $updated += $chunkCounts['updated'];
                    $imported += $chunkCounts['inserted'] + $chunkCounts['updated'];
                    $recordsByKey = [];
                }
            }
        }

        $chunkCounts = $this->persistRecordChunk($recordsByKey, $table, 'mtd');
        $inserted += $chunkCounts['inserted'];
        $updated += $chunkCounts['updated'];
        $imported += $chunkCounts['inserted'] + $chunkCounts['updated'];

        $message = "{$imported} milk test record(s) added or updated; duplicate worksheet rows were reconciled and existing history was retained.";
        if ($skipped > 0) {
            $message .= " {$skipped} duplicate or unidentified row(s) were skipped.";
        }

        return [
            $rowsSeen,
            $imported,
            $message,
            [
                'rows_inserted' => $inserted,
                'rows_updated' => $updated,
                'rows_skipped' => $skipped,
                'rows_rejected' => $rejectedRows,
                'rows_unmapped' => 0,
            ],
        ];
    }

    private function farmerIndex(int $cooperativeId): array
    {
        $index = [];
        foreach (DB::table('farmers')->where('cooperative_id', $cooperativeId)->get() as $farmer) {
            $first = trim(implode(' ', array_filter([
                $farmer->first_name ?? '',
                $farmer->middle_name ?? '',
            ])));
            $last = trim((string) ($farmer->last_name ?? ''));
            foreach ([$first.' '.$last, $last.', '.$first, $last] as $name) {
                foreach ($this->personNameVariants($name) as $variant) {
                    if ($variant !== '') {
                        $index[$variant][] = (int) $farmer->id;
                    }
                }
            }
        }

        foreach ($index as &$farmerIds) {
            $farmerIds = array_values(array_unique($farmerIds));
        }
        unset($farmerIds);

        return $index;
    }

    private function personNameVariants(string $name): array
    {
        $variants = [$this->normalizePersonName($name)];
        $parts = explode(',', $name, 2);
        if (count($parts) === 2) {
            $variants[] = $this->normalizePersonName(trim($parts[1]).' '.trim($parts[0]));
        }

        return array_values(array_unique(array_filter($variants)));
    }

    private function normalizePersonName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii(trim($name))))));
    }

    private function recordKey(string $sheetKey, array $record): ?string
    {
        $identity = match ($sheetKey) {
            'fhpr_breeding' => ['cooperative_id', 'registry_number'],
            'mating', 'mating_2' => [
                'cooperative_id', 'registry_number',
                ! empty($record['mating_date']) ? 'mating_date' : 'pregnancy_check_date',
                'bull_id', 'mating_type', 'technician', 'pregnancy_result',
            ],
            'health_nutrition' => ['cooperative_id', 'registry_number', 'monitoring_date', 'health_status'],
            'milk', 'milk_2' => [
                'cooperative_id', 'record_date', 'collection_period', 'registry_number', 'farmer_id',
                'farmer_name', '_source_cooperative_label',
            ],
            'bcs' => ['cooperative_id', 'registry_number', 'record_date', 'body_condition_score'],
            'mtd' => ['cooperative_id', 'registry_number', 'test_date', 'dryoff_date', 'test_day'],
            'meat', 'manure' => ['cooperative_id', 'product_type', 'actual_date', 'volume_kg'],
            'bd_out' => ['cooperative_id', 'registry_number', 'exit_date', 'nature_of_exit'],
            default => [],
        };
        $values = [];
        foreach ($identity as $field) {
            $value = $record[$field] ?? null;
            if ($field === 'farmer_name') {
                $value = $this->normalizePersonName((string) $value);
            }
            $values[] = is_scalar($value) ? (string) $value : '';
        }
        if ($identity === [] || count(array_filter($values, fn ($value) => $value !== '')) < 2) {
            return null;
        }

        return hash('sha256', json_encode($values));
    }

    private function savePendingWorkbookRow(
        int $identifierId,
        ?int $batchFileId,
        int $importId,
        int $sheetId,
        string $table,
        int $sourceRow,
        array $sourceData
    ): void {
        if (! Schema::hasTable('pending_cooperative_workbook_rows')) {
            return;
        }

        DB::table('pending_cooperative_workbook_rows')->insertOrIgnore([
            'identifier_id' => $identifierId,
            'batch_file_id' => $batchFileId,
            'workbook_import_id' => $importId,
            'workbook_import_sheet_id' => $sheetId,
            'target_table' => $table,
            'source_row' => $sourceRow,
            'source_data' => json_encode($sourceData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function sourceDataValue(array $sourceData, array $headerNames): ?string
    {
        foreach ($sourceData as $header => $value) {
            $normalizedHeader = $this->normalizeHeaderName((string) $header);
            if (in_array($normalizedHeader, $headerNames, true) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function normalizeHeaderName(string $header): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '_', Str::lower(Str::ascii(trim($header)))), '_');
    }

    private function fieldForHeader(string $header, array $columns, string $sheetKey, int $columnIndex = 0): ?string
    {
        $name = $this->normalizeHeaderName($header);
        if ($name === '' || in_array($name, ['id', 'created_at', 'updated_at'], true)) {
            return null;
        }

        if (in_array($sheetKey, ['mating', 'mating_2'], true)) {
            $animalRegisterColumn = $sheetKey === 'mating' ? 3 : 4;
            $animalBullColumn = $sheetKey === 'mating' ? 5 : 6;
            if ($name === 'register_no' && $columnIndex !== $animalRegisterColumn) {
                return null;
            }
            if ($name === 'bull_id_no' && $columnIndex !== $animalBullColumn) {
                return null;
            }
            if ($name === 'tag_no') {
                return null;
            }
        }

        $aliases = [
            'register_id_no' => 'registry_number',
            'register_no' => 'registry_number',
            'registry_no' => 'registry_number',
            'registry_id' => 'registry_number',
            'cow_registry_number' => 'registry_number',
            'registration_no' => 'registry_number',
            'id_number' => $sheetKey === 'milk' ? 'registry_number' : 'id_number',
            'tag_no' => 'tag_number',
            'bolus_id_rfid' => 'bolus_id',
            'carabao_id' => 'registry_number',
            'date_of_mating' => 'mating_date',
            'breeding_date' => 'mating_date',
            'date_of_ai' => 'mating_date',
            'mating_date' => 'mating_date',
            'date_of_monitoring' => 'monitoring_date',
            'date_conducted' => 'monitoring_date',
            'health_date' => 'monitoring_date',
            'date_of_exit' => 'exit_date',
            'date_of_disposal_death' => 'exit_date',
            'nature_for_exit' => 'nature_of_exit',
            'reason_of_exit' => 'remarks',
            'date_of_production' => 'record_date',
            'production_date' => 'record_date',
            'milk_production_date' => 'record_date',
            'actual_date_of_collection' => in_array($sheetKey, ['meat', 'manure'], true) ? 'actual_date' : 'record_date',
            'name_of_coop_association' => null,
            'cooperative_code' => null,
            'coop_code' => null,
            'herd_code' => null,
            'date_monitored' => 'record_date',
            'reported_date' => 'reported_date',
            'date_reported' => 'reported_date',
            'date_of_test' => 'test_date',
            'test_day_date' => 'test_date',
            'milk_yield' => 'milk_yield_liters',
            'milk_yield_liters' => 'milk_yield_liters',
            'milk_volume' => 'total_liters',
            'am' => in_array($sheetKey, ['milk', 'milk_2'], true) ? 'collection_period' : 'morning_liters',
            'am_pm' => 'collection_period',
            'collection_period_am' => 'collection_period',
            'total_milk' => 'total_liters',
            'total_milk_production' => 'total_liters',
            'total_milk_production_kg' => 'total_liters',
            'total' => 'total_liters',
            'morning_milk' => 'morning_liters',
            'morning_yield' => 'morning_liters',
            'pm' => in_array($sheetKey, ['milk', 'milk_2'], true) ? 'collection_period' : 'afternoon_liters',
            'no_of_cows' => 'number_of_cows',
            'price_per_kg_raw_milk' => 'price_per_kg',
            'price_per_kilogram' => 'price_per_kg',
            'milk_traded_kg' => 'milk_traded',
            'evening_liters' => 'afternoon_liters',
            'afternoon_milk' => 'afternoon_liters',
            'afternoon_yield' => 'afternoon_liters',
            'evening_yield' => 'afternoon_liters',
            'fat' => 'fat_percentage',
            'fat_percent' => 'fat_percentage',
            'snf' => 'snf_percentage',
            'snf_percent' => 'snf_percentage',
            'health' => 'health_status',
            'condition' => 'health_status',
            'treatment_medication' => 'treatment',
            'pregnancy_check_result' => 'pregnancy_result',
            'result' => 'pregnancy_result',
            'actual_date_of_pd' => 'pregnancy_check_date',
            'expected_date_of_calving' => 'expected_calving_date',
            'pregnant' => in_array($sheetKey, ['mating', 'mating_2'], true) ? 'pregnancy_result' : 'pregnancy_status',
            'bull_id_no' => 'bull_id',
            'ai_or_n' => 'mating_type',
            'type_of_mating' => 'mating_type',
            'name_of_farmer' => 'farmer_name',
            'owner_s_name' => 'farmer_name',
            'name' => 'farmer_name',
            'registry_id_no' => 'registry_number',
            'dob' => 'date_of_birth',
            'type' => 'animal_type',
            'bcs' => $sheetKey === 'bcs' ? 'body_condition_score' : null,
            'body_condition' => $sheetKey === 'bcs' ? 'body_condition_score' : null,
            'date_of_first_service_heifer' => 'first_service_date',
            'date_of_1st_ai_post_calving' => 'first_ai_date',
            'date_of_last_ai' => 'last_ai_date',
            'previous_calving_date_mm_dd_yy' => 'previous_calving_date',
            'present_calving_mm_dd_yy' => 'present_calving_date',
            'no_of_a_i' => 'ai_count',
            'type_of_estrus' => 'estrus_type',
            'name_bull_id' => 'bull_name_or_id',
            'next_heat' => 'next_heat_date',
            'second_heat_cycle' => 'second_heat_cycle_date',
            'expected_date_of_pd_90_days' => 'expected_pd_date',
            'actual_date_of_pd' => in_array($sheetKey, ['mating', 'mating_2'], true)
                ? 'pregnancy_check_date'
                : 'actual_pd_date',
            'gestation_length_day' => 'gestation_length_days',
            'days_open_day' => 'days_open',
            'volume_of_prod_n_kg' => 'volume_kg',
            'volume_of_prodn_kg' => 'volume_kg',
            'volume_of_prod_n_other_source_kg' => 'other_source_volume_kg',
            'volume_of_prodn_other_source_kg' => 'other_source_volume_kg',
            'farm_gate_price_per_kg_php' => 'price_per_kg',
            'price_per_kg_php' => 'price_per_kg',
            'value_of_meat_produced_php' => 'value_php',
            'value_of_manure_based_produced_php' => 'value_php',
            'farmer_recipient' => 'farmer_name',
            'status' => match ($sheetKey) {
                'health_nutrition' => 'health_status',
                'mating', 'mating_2' => 'pregnancy_result',
                default => 'status_updated',
            },
            'farmer' => 'farmer_name',
            'farmer_name' => 'farmer_name',
            'animal_id' => 'registry_number',
            'animal_registry_number' => 'registry_number',
            'date' => [
                'mating' => 'mating_date',
                'mating_2' => 'mating_date',
                'health_nutrition' => 'monitoring_date',
                'bd_out' => 'exit_date',
                'milk' => 'record_date',
                'milk_2' => 'record_date',
                'bcs' => 'record_date',
                'meat' => 'actual_date',
                'manure' => 'actual_date',
            ][$sheetKey] ?? 'date',
        ];

        $field = $aliases[$name] ?? $name;
        if (! is_string($field) || $field === 'date') {
            return null;
        }

        return in_array($field, $columns, true) ? $field : null;
    }

    private function matchingRecord(
        string $table,
        string $sheetKey,
        array $record,
        ?int $cooperativeId,
        ?string $sourceCooperativeLabel = null
    ): ?object
    {
        if (in_array($sheetKey, ['meat', 'manure'], true)) {
            $dateField = ! empty($record['actual_date']) ? 'actual_date' : (! empty($record['reported_date']) ? 'reported_date' : null);
            if ($dateField === null) {
                return null;
            }

            $query = DB::table($table)->where('cooperative_id', $cooperativeId)->where('product_type', $sheetKey);
            $query->where($dateField, $record[$dateField]);
            foreach (['volume_kg', 'other_source_volume_kg', 'price_per_kg', 'value_php'] as $field) {
                if (isset($record[$field])) {
                    $query->where($field, $record[$field]);
                }
            }

            return $query->first();
        }

        $registry = $record['registry_number'] ?? null;
        $dateField = [
            'mating' => 'mating_date',
            'mating_2' => 'mating_date',
            'health_nutrition' => 'monitoring_date',
            'bd_out' => 'exit_date',
            'milk' => 'record_date',
            'milk_2' => 'record_date',
            'mtd' => 'test_date',
            'bcs' => 'record_date',
        ][$sheetKey] ?? null;

        if ($sheetKey === 'fhpr_breeding' && $registry) {
            return DB::table($table)->where('cooperative_id', $cooperativeId)->where('registry_number', $registry)->first();
        }
        if ($sheetKey === 'bd_out' && $registry) {
            $query = DB::table($table)->where('cooperative_id', $cooperativeId)->where('registry_number', $registry);
            if (! empty($record['exit_date'])) {
                $query->where('exit_date', $record['exit_date']);
            }
            foreach (['nature_of_exit', 'remarks'] as $discriminator) {
                if (! empty($record[$discriminator])) {
                    $query->where($discriminator, $record[$discriminator]);
                }
            }

            return $query->first();
        }
        if (in_array($sheetKey, ['milk', 'milk_2'], true) && ! empty($record['record_date'])) {
            $query = DB::table($table)->where('cooperative_id', $cooperativeId)
                ->where('record_date', $record['record_date']);
            if (! empty($record['collection_period'])) {
                $query->where(function ($periodQuery) use ($record) {
                    $periodQuery->where('collection_period', $record['collection_period'])
                        ->orWhereNull('collection_period');
                });
            }
            if ($cooperativeId === null && $sourceCooperativeLabel !== null) {
                $query->where(function ($sourceQuery) use ($sourceCooperativeLabel) {
                    foreach ([
                        '$._cooperative_source_label',
                        '$."Association/Coop/Module"',
                        '$."Association/Coop/Institution"',
                        '$."Name of Association/Coop/Institution"',
                        '$.cooperative_name',
                        '$.coop_name',
                        '$.association_name',
                    ] as $path) {
                        $sourceQuery->orWhereRaw(
                            "JSON_UNQUOTE(JSON_EXTRACT(source_data, ?)) = ?",
                            [$path, $sourceCooperativeLabel]
                        );
                    }
                });
            }
            if ($registry) {
                $query->where('registry_number', $registry);
            } elseif (! empty($record['farmer_id'])) {
                $query->where('farmer_id', $record['farmer_id']);
            } elseif (! empty($record['farmer_name'])) {
                $query->where('farmer_name', $record['farmer_name']);
            } else {
                return null;
            }

            return $query->first();
        }
        if (in_array($sheetKey, ['mating', 'mating_2'], true) && $registry && ! empty($record['mating_date'])) {
            $query = DB::table($table)->where('cooperative_id', $cooperativeId)
                ->where('registry_number', $registry)
                ->where('mating_date', $record['mating_date']);
            foreach (['bull_id', 'mating_type', 'technician', 'pregnancy_result', 'pregnancy_check_date'] as $discriminator) {
                if (! empty($record[$discriminator])) {
                    $query->where($discriminator, $record[$discriminator]);
                }
            }

            return $query->first();
        }
        if (in_array($sheetKey, ['mating', 'mating_2'], true) && $registry && ! empty($record['pregnancy_check_date'])) {
            $query = DB::table($table)->where('cooperative_id', $cooperativeId)
                ->where('registry_number', $registry)
                ->whereNull('mating_date')
                ->where('pregnancy_check_date', $record['pregnancy_check_date']);
            if (! empty($record['pregnancy_result'])) {
                $query->where('pregnancy_result', $record['pregnancy_result']);
            }

            return $query->first();
        }
        if ($sheetKey === 'bcs' && $registry) {
            $query = DB::table($table)->where('cooperative_id', $cooperativeId)
                ->where('registry_number', $registry);
            if (! empty($record['record_date'])) {
                $query->where('record_date', $record['record_date']);
            }
            if (isset($record['body_condition_score'])) {
                $query->where('body_condition_score', $record['body_condition_score']);
            }

            return $query->first();
        }
        if (! $registry || ! $dateField || empty($record[$dateField])) {
            return null;
        }

        $query = DB::table($table)->where('cooperative_id', $cooperativeId)
            ->where('registry_number', $registry)
            ->where($dateField, $record[$dateField]);

        if ($sheetKey === 'mtd') {
            foreach (['test_day', 'dryoff_date'] as $discriminator) {
                if (! empty($record[$discriminator])) {
                    $query->where($discriminator, $record[$discriminator]);
                }
            }
        }
        foreach (['nature_of_exit', 'health_status'] as $discriminator) {
            if (isset($record[$discriminator])) {
                $query->where($discriminator, $record[$discriminator]);
            }
        }

        return $query->first();
    }

    private function findCarabao(int $cooperativeId, string $identifier): ?object
    {
        $query = DB::table('carabaos')->where('cooperative_id', $cooperativeId)
            ->where(function ($builder) use ($identifier) {
                $builder->where('registry_number', $identifier)
                    ->orWhere('animal_id', $identifier)
                    ->orWhere('tag_number', $identifier)
                    ->orWhere('bolus_id', $identifier);
                if (ctype_digit($identifier)) {
                    $builder->orWhere('id', (int) $identifier);
                }
            });

        return $query->first();
    }

    private function databaseValue(mixed $value, string $field): mixed
    {
        if (str_ends_with($field, '_date') || in_array($field, ['date_of_birth', 'record_date', 'test_date'], true)) {
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d');
            }
            if (is_numeric($value)) {
                if ((float) $value > 20000 && (float) $value < 80000) {
                    return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
                }

                return null;
            }
            try {
                return \Carbon\Carbon::parse((string) $value)->format('Y-m-d');
            } catch (Throwable) {
                return null;
            }
        }

        if (in_array($field, [
            'ai_count', 'days_in_milk', 'days_pregnant', 'days_to_first_service',
            'days_open', 'gestation_length_days', 'parity', 'test_day',
        ], true) && is_numeric($value) && (float) $value < 0) {
            return null;
        }

        if (in_array($field, ['total_liters', 'number_of_cows', 'price_per_kg', 'milk_traded'], true)) {
            $numeric = is_string($value)
                ? preg_replace('/[,\s₱]/u', '', trim($value))
                : $value;
            if (! is_numeric($numeric) || (float) $numeric < 0) {
                return null;
            }
            if ($field === 'number_of_cows') {
                return floor((float) $numeric) === (float) $numeric ? (int) $numeric : null;
            }

            return (string) $numeric;
        }

        return is_string($value) ? trim($value) : $value;
    }

    private function normalizeCollectionPeriod(mixed $value): ?string
    {
        $period = Str::upper(trim((string) $value));
        if (in_array($period, ['AM', '1', 'MORNING'], true)) {
            return 'AM';
        }
        if (in_array($period, ['PM', '2', 'AFTERNOON', 'EVENING'], true)) {
            return 'PM';
        }

        return null;
    }

    private function normalizeSheetName(string $name): string
    {
        return Str::lower(preg_replace('/[^a-z0-9]/i', '', $name));
    }

    private function recordSheet(
        int $importId,
        string $sheetName,
        ?string $module,
        string $status,
        int $rowsSeen,
        int $rowsImported,
        string $message
    ): void {
        DB::table('cooperative_workbook_import_sheets')->insert([
            'workbook_import_id' => $importId,
            'sheet_name' => Str::limit($sheetName, 255, ''),
            'target_module' => $module,
            'status' => $status,
            'rows_seen' => $rowsSeen,
            'rows_imported' => $rowsImported,
            'message' => $message,
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function availableImportSheetCounterColumns(): array
    {
        if ($this->availableImportSheetCounterColumns === null) {
            $this->availableImportSheetCounterColumns = array_values(array_filter(
                self::IMPORT_SHEET_COUNTER_COLUMNS,
                fn (string $column): bool => Schema::hasColumn('cooperative_workbook_import_sheets', $column)
            ));
        }

        return $this->availableImportSheetCounterColumns;
    }
}
