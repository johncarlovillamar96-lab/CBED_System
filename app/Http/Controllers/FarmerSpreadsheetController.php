<?php

namespace App\Http\Controllers;

use App\Support\FarmerImportAddress;
use App\Support\ImportAcquisition;
use App\Support\SpreadsheetWorksheetReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FarmerSpreadsheetController extends Controller
{
    public function template()
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Farmers');
        $sheet->fromArray([
            'First Name', 'Middle Name', 'Last Name', 'Birthday', 'Sex', 'Cell Number',
            'Civil Status', 'Occupation', 'Position', 'Spouse Name', 'Spouse Occupation',
            ...array_map('ucfirst', array_keys(FarmerImportAddress::HEADERS)),
            'Acquisition Date', 'Acquisition Cost',
        ]);
        $sheet->getStyle('A1:R1')->getFont()->setBold(true);

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, 'farmers-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function import(Request $request)
    {
        $validated = $request->validate([
            'cooperative_id' => ['required', 'integer', 'exists:cooperatives,id'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,xlsm', 'max:20480'],
        ]);

        abort_unless(
            Schema::hasColumns('farmers', ['cooperative_sequence', 'cooperative_id', ...array_keys(FarmerImportAddress::HEADERS), ...ImportAcquisition::FIELDS]),
            422,
            'Run the latest database migrations before importing farmers.'
        );

        $book = IOFactory::load($request->file('file')->getRealPath());
        $inventorySheet = $book->getSheetByName('BD_ALL');
        $sheet = $inventorySheet ?? $book->getActiveSheet();
        $rows = $inventorySheet ? SpreadsheetWorksheetReader::rows($sheet) : $sheet->toArray(null, true, true, false);
        [$headerIndex, $headers] = $this->findHeaderRow($rows);
        abort_if($headerIndex === null, 422, 'Could not find farmer name headers. Use Farmer Name, or separate first_name and last_name columns.');

        $records = [];
        foreach (array_slice($rows, $headerIndex + 1) as $values) {
            $get = fn (array $keys) => $this->firstValue($values, $headers, $keys);
            $fullName = $get(['farmer name', 'name of farmer', 'farmer recipient', 'name']);
            $first = $get(['first name', 'firstname']);
            $middle = $get(['middle name', 'middlename']);
            $last = $get(['last name', 'lastname', 'surname']);

            if ($fullName === '' && $first === '' && $last === '') continue;
            if ($first === '' && $last === '' && $fullName !== '') {
                [$first, $middle, $last] = $this->splitName($fullName);
            }
            if ($first === '' && $last === '') continue;

            $records[] = [
                'first_name' => $first,
                'middle_name' => $middle ?: null,
                'last_name' => $last,
                'birthday' => $inventorySheet ? null : $this->date($get(['birthday', 'birth date', 'date of birth'])),
                'sex' => $inventorySheet ? null : ($get(['sex', 'gender']) ?: null),
                'cell_number' => $get(['cell number', 'contact number', 'phone', 'mobile']) ?: null,
                'civil_status' => $get(['civil status']) ?: null,
                'occupation' => $get(['occupation']) ?: null,
                'position' => $get(['position']) ?: null,
                'spouse_name' => $get(['spouse name']) ?: null,
                'spouse_occupation' => $get(['spouse occupation']) ?: null,
                '_sort_name' => trim($last.' '.$first.' '.$middle),
                ...FarmerImportAddress::fromHeaders($get),
                'acquisition_date' => $this->date($get(ImportAcquisition::DATE_HEADERS)),
                'acquisition_cost' => ImportAcquisition::cost($get(ImportAcquisition::COST_HEADERS)),
            ];
        }

        abort_if(! count($records), 422, 'No farmer names were found in the selected workbook.');
        usort($records, fn ($a, $b) => strnatcasecmp($this->normalize($a['_sort_name']), $this->normalize($b['_sort_name'])));

        $cooperativeId = (int) $validated['cooperative_id'];
        $created = 0;
        $skipped = 0;
        $updated = 0;
        $firstCode = null;
        $lastCode = null;

        DB::transaction(function () use ($records, $cooperativeId, &$created, &$skipped, &$updated, &$firstCode, &$lastCode) {
            DB::table('cooperatives')->where('id', $cooperativeId)->lockForUpdate()->first();
            $sequence = (int) DB::table('farmers')->where('cooperative_id', $cooperativeId)->max('cooperative_sequence');
            $existingRows = DB::table('farmers')->where('cooperative_id', $cooperativeId)->get(['id', 'first_name', 'middle_name', 'last_name', 'farmer_code', 'cooperative_sequence', ...array_keys(FarmerImportAddress::HEADERS), ...ImportAcquisition::FIELDS]);
            $existingNames = [];
            foreach ($existingRows as $farmer) {
                $sequence = max($sequence, (int) ($farmer->cooperative_sequence ?? 0));
                $prefix = $cooperativeId.'-';
                if (str_starts_with((string) $farmer->farmer_code, $prefix)) {
                    $suffix = substr((string) $farmer->farmer_code, strlen($prefix));
                    if (ctype_digit($suffix)) $sequence = max($sequence, (int) $suffix);
                }
                $existingNames[$this->normalize(trim($farmer->last_name.' '.$farmer->first_name.' '.$farmer->middle_name))] = $farmer;
                $existingNames[$this->normalize(trim($farmer->first_name.' '.$farmer->middle_name.' '.$farmer->last_name))] = $farmer;
            }

            foreach ($records as $record) {
                $sortName = $record['_sort_name'];
                unset($record['_sort_name']);
                $key = $this->normalize($sortName);
                if (isset($existingNames[$key])) {
                    $farmer = $existingNames[$key];
                    $changes = [];
                    foreach (array_keys(FarmerImportAddress::HEADERS) as $field) {
                        if ($record[$field] !== null && $record[$field] !== $farmer->$field) {
                            $changes[$field] = $record[$field];
                        }
                    }
                    $changes += ImportAcquisition::updates(array_intersect_key($record, array_flip(ImportAcquisition::FIELDS)), $farmer);
                    if ($changes) {
                        DB::table('farmers')->where('id', $farmer->id)->update($changes + ['updated_at' => now()]);
                        foreach ($changes as $field => $value) $farmer->$field = $value;
                        $updated++;
                    } else {
                        $skipped++;
                    }
                    continue;
                }

                $sequence++;
                $code = $cooperativeId.'-'.$sequence;
                $record['cooperative_id'] = $cooperativeId;
                $record['cooperative_sequence'] = $sequence;
                $record['farmer_code'] = $code;
                $record['created_at'] = now();
                $record['updated_at'] = now();
                $record['id'] = DB::table('farmers')->insertGetId($record);
                $existingNames[$key] = (object) $record;
                $created++;
                $firstCode ??= $code;
                $lastCode = $code;
            }
        });

        $book->disconnectWorksheets();

        $message = "Imported {$created} farmer(s) in A–Z order; {$skipped} duplicate(s) skipped.";
        if ($firstCode) $message .= " New Farmer IDs: {$firstCode}".($lastCode !== $firstCode ? " to {$lastCode}." : '.');

        if ($updated) $message .= " Updated address or acquisition details for {$updated} existing farmer record(s).";

        return response()->json(['success' => true, 'count' => $created, 'skipped' => $skipped, 'updated' => $updated, 'message' => $message]);
    }

    private function findHeaderRow(array $rows): array
    {
        foreach (array_slice($rows, 0, 10, true) as $index => $values) {
            $headers = [];
            foreach ($values as $column => $value) {
                $header = $this->normalizeHeader((string) $value);
                if ($header !== '') $headers[$header] = $column;
            }
            $hasFullName = isset($headers['farmer name']) || isset($headers['name of farmer']) || isset($headers['farmer recipient']) || isset($headers['name']);
            $hasParts = (isset($headers['first name']) || isset($headers['firstname'])) && (isset($headers['last name']) || isset($headers['lastname']) || isset($headers['surname']));
            if ($hasFullName || $hasParts) return [$index, $headers];
        }
        return [null, []];
    }

    private function firstValue(array $values, array $headers, array $keys): string
    {
        foreach ($keys as $key) {
            $normalized = $this->normalizeHeader($key);
            if (isset($headers[$normalized])) return trim((string) ($values[$headers[$normalized]] ?? ''));
        }
        return '';
    }

    private function splitName(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        if (str_contains($name, ',')) {
            [$last, $given] = array_map('trim', explode(',', $name, 2));
            $parts = preg_split('/\s+/', $given, -1, PREG_SPLIT_NO_EMPTY);
            return [$parts[0] ?? '', isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : '', $last];
        }
        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
        if (count($parts) === 1) return ['', '', $parts[0]];
        return [$parts[0], count($parts) > 2 ? implode(' ', array_slice($parts, 1, -1)) : '', $parts[count($parts) - 1]];
    }

    private function normalizeHeader(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower(trim($value)))));
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower(trim($value)))));
    }

    private function date(string $value): ?string
    {
        if ($value === '') return null;
        if (is_numeric($value)) return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        try { return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d'); } catch (\Throwable) { return null; }
    }
}
