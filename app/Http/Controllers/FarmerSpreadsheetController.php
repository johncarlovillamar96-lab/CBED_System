<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;

class FarmerSpreadsheetController extends Controller
{
    public function import(Request $request)
    {
        $validated = $request->validate([
            'cooperative_id' => ['required', 'integer', 'exists:cooperatives,id'],
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
        ]);

        abort_unless(
            Schema::hasColumn('farmers', 'cooperative_sequence') && Schema::hasColumn('farmers', 'cooperative_id'),
            422,
            'Run the latest database migrations before importing farmers.'
        );

        $book = IOFactory::load($request->file('file')->getRealPath());
        $sheet = $book->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
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
                'birthday' => $this->date($get(['birthday', 'birth date', 'date of birth'])),
                'sex' => $get(['sex', 'gender']) ?: null,
                'cell_number' => $get(['cell number', 'contact number', 'phone', 'mobile']) ?: null,
                'civil_status' => $get(['civil status']) ?: null,
                'occupation' => $get(['occupation']) ?: null,
                'position' => $get(['position']) ?: null,
                'spouse_name' => $get(['spouse name']) ?: null,
                'spouse_occupation' => $get(['spouse occupation']) ?: null,
                '_sort_name' => trim($last.' '.$first.' '.$middle),
            ];
        }

        abort_if(! count($records), 422, 'No farmer names were found in the selected workbook.');
        usort($records, fn ($a, $b) => strnatcasecmp($this->normalize($a['_sort_name']), $this->normalize($b['_sort_name'])));

        $cooperativeId = (int) $validated['cooperative_id'];
        $created = 0;
        $skipped = 0;
        $firstCode = null;
        $lastCode = null;

        DB::transaction(function () use ($records, $cooperativeId, &$created, &$skipped, &$firstCode, &$lastCode) {
            $sequence = (int) DB::table('farmers')->where('cooperative_id', $cooperativeId)->max('cooperative_sequence');
            $existingRows = DB::table('farmers')->where('cooperative_id', $cooperativeId)->get(['first_name', 'middle_name', 'last_name', 'farmer_code', 'cooperative_sequence']);
            $existingNames = [];
            foreach ($existingRows as $farmer) {
                $sequence = max($sequence, (int) ($farmer->cooperative_sequence ?? 0));
                $prefix = $cooperativeId.'-';
                if (str_starts_with((string) $farmer->farmer_code, $prefix)) {
                    $suffix = substr((string) $farmer->farmer_code, strlen($prefix));
                    if (ctype_digit($suffix)) $sequence = max($sequence, (int) $suffix);
                }
                $existingNames[$this->normalize(trim($farmer->last_name.' '.$farmer->first_name.' '.$farmer->middle_name))] = true;
                $existingNames[$this->normalize(trim($farmer->first_name.' '.$farmer->middle_name.' '.$farmer->last_name))] = true;
            }

            foreach ($records as $record) {
                $sortName = $record['_sort_name'];
                unset($record['_sort_name']);
                $key = $this->normalize($sortName);
                if (isset($existingNames[$key])) {
                    $skipped++;
                    continue;
                }

                $sequence++;
                $code = $cooperativeId.'-'.$sequence;
                $record['cooperative_id'] = $cooperativeId;
                $record['cooperative_sequence'] = $sequence;
                $record['farmer_code'] = $code;
                $record['created_at'] = now();
                $record['updated_at'] = now();
                DB::table('farmers')->insert($record);
                $existingNames[$key] = true;
                $created++;
                $firstCode ??= $code;
                $lastCode = $code;
            }
        });

        $book->disconnectWorksheets();

        $message = "Imported {$created} farmer(s) in A–Z order; {$skipped} duplicate(s) skipped.";
        if ($firstCode) $message .= " New Farmer IDs: {$firstCode}".($lastCode !== $firstCode ? " to {$lastCode}." : '.');

        return response()->json(['success' => true, 'count' => $created, 'skipped' => $skipped, 'message' => $message]);
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
