<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CooperativeSpreadsheetController extends Controller
{
    private const HEADERS = ['No.', 'Cooperative Name', 'Coop Type', 'Province', 'Municipality', 'Barangay'];

    public function export()
    {
        $sheet = new Spreadsheet();
        $page = $sheet->getActiveSheet();
        $page->setTitle('Cooperatives');
        $page->fromArray(self::HEADERS, null, 'A1');
        $page->getStyle('A1:F1')->getFont()->setBold(true);

        $row = 2;
        DB::table('cooperatives')->orderBy('id')->get()->each(function ($coop) use ($page, &$row) {
            $page->fromArray([
                $coop->cooperative_code,
                $coop->cooperative_name,
                $coop->coop_type,
                $coop->province,
                $coop->municipality,
                $coop->barangay,
            ], null, 'A'.$row++);
        });

        foreach (range('A', 'F') as $column) {
            $page->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($sheet) {
            (new Xlsx($sheet))->save('php://output');
            $sheet->disconnectWorksheets();
        }, 'cooperatives.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:20480'],
        ]);

        $book = IOFactory::load($request->file('file')->getRealPath());
        $sheet = $book->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        $headerIndex = null;
        $headerMap = [];

        foreach (array_slice($rows, 0, 10, true) as $index => $values) {
            $map = [];
            foreach ($values as $column => $value) {
                $key = $this->normalizeHeader((string) $value);
                if ($key !== '') $map[$key] = $column;
            }
            if (isset($map['no'], $map['cooperative name'], $map['province'], $map['municipality'], $map['barangay'])) {
                $headerIndex = $index;
                $headerMap = $map;
                break;
            }
        }

        abort_if($headerIndex === null, 422, 'The file must include these headers: No., Cooperative Name, Province, Municipality, Barangay.');

        $imported = 0;
        DB::transaction(function () use ($rows, $headerIndex, $headerMap, &$imported) {
            foreach (array_slice($rows, $headerIndex + 1) as $values) {
                $get = fn (string $field) => trim((string) ($values[$headerMap[$field]] ?? ''));
                $code = $get('no');
                $name = $get('cooperative name');
                if ($code === '' && $name === '') continue;
                if ($code === '' || $name === '') {
                    abort(422, 'Every cooperative row must include both No. and Cooperative Name.');
                }

                $values = [
                    'cooperative_name' => $name,
                    'province' => $get('province') ?: null,
                    'municipality' => $get('municipality') ?: null,
                    'barangay' => $get('barangay') ?: null,
                    'updated_at' => now(),
                ];
                $coopTypeHeader = collect(['coop type', 'cooperative type', 'type'])
                    ->first(fn ($header) => isset($headerMap[$header]));
                if ($coopTypeHeader !== null) {
                    $values['coop_type'] = $get($coopTypeHeader) ?: null;
                }
                $existing = DB::table('cooperatives')->where('cooperative_code', $code)->first();
                if ($existing) {
                    DB::table('cooperatives')->where('id', $existing->id)->update($values);
                } else {
                    DB::table('cooperatives')->insert($values + ['cooperative_code' => $code, 'created_at' => now()]);
                }
                $imported++;
            }
        });

        $book->disconnectWorksheets();

        return back()->with('success', "Imported or updated {$imported} cooperative record(s).");
    }

    private function normalizeHeader(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower(trim($value)))));
    }
}
