<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CooperativeImportController extends Controller
{
    public function create()
    {
        return view('imports.cooperative', ['cooperatives' => DB::table('cooperatives')->orderBy('cooperative_name')->get()]);
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'cooperative_id' => ['required', 'exists:cooperatives,id'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,xlsm,csv,txt', 'max:20480'],
        ]);

        $path = $request->file('file')->store('imports');
        $absolute = storage_path('app/private/'.$path);
        if (! file_exists($absolute)) {
            $absolute = storage_path('app/'.$path);
        }
        $rows = $this->readInventory($absolute);
        $usable = collect($rows)->filter(fn ($row) => trim((string) ($row['farmer'] ?? '')) !== '' || trim((string) ($row['animal'] ?? '')) !== '')->values();

        $request->session()->put('cooperative_import', ['path' => $path, 'cooperative_id' => (int) $data['cooperative_id']]);

        return view('imports.cooperative-preview', [
            'cooperative' => DB::table('cooperatives')->find($data['cooperative_id']),
            'rows' => $usable->take(20),
            'totalRows' => $usable->count(),
            'farmerCount' => $usable->pluck('farmer')->filter()->map(fn ($n) => $this->normalize($n))->unique()->count(),
        ]);
    }

    public function import(Request $request)
    {
        $batch = $request->session()->get('cooperative_import');
        abort_unless($batch && $batch['path'], 422, 'Upload a workbook and preview it first.');
        $path = storage_path('app/private/'.$batch['path']);
        if (! file_exists($path)) {
            $path = storage_path('app/'.$batch['path']);
        }
        abort_unless(file_exists($path), 422, 'The uploaded workbook expired. Please upload it again.');

        $records = $this->readInventory($path);
        $result = DB::transaction(function () use ($records, $batch) {
            $coopId = $batch['cooperative_id'];
            $seq = (int) DB::table('farmers')->where('cooperative_id', $coopId)->max('cooperative_sequence');
            $farmers = 0;
            $animals = 0;
            $matched = 0;

            foreach ($records as $row) {
                $name = trim((string) ($row['farmer'] ?? ''));
                $animalName = trim((string) ($row['animal'] ?? ''));
                if ($name === '' && $animalName === '') continue;

                $farmer = null;
                if ($name !== '') {
                    $key = $this->normalize($name);
                    $matches = DB::table('farmers')->where('cooperative_id', $coopId)->get()->filter(function ($candidate) use ($key) {
                        $given = trim($candidate->first_name.' '.$candidate->middle_name);
                        $variants = [
                            $this->normalize(trim($given.' '.$candidate->last_name)),
                            $this->normalize(trim($candidate->last_name.' '.$given)),
                            $this->normalize($candidate->last_name),
                        ];
                        return in_array($key, $variants, true);
                    });
                    abort_if($matches->count() > 1, 422, "More than one existing farmer in this cooperative matches '{$name}'. Resolve the duplicate farmer records before importing.");
                    $farmer = $matches->first();
                    if (! $farmer) {
                        $seq++;
                        $farmerId = DB::table('farmers')->insertGetId([
                            'cooperative_id' => $coopId,
                            'cooperative_sequence' => $seq,
                            'farmer_code' => $coopId.'-'.$seq,
                            'first_name' => '',
                            'last_name' => $name,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                        $farmer = DB::table('farmers')->find($farmerId);
                        $farmers++;
                    } else {
                        $matched++;
                    }
                }

                $registry = $this->value($row, 'registry');
                $tag = $this->value($row, 'tag');
                $bolus = $this->value($row, 'bolus');
                if ($animalName === '' && ! $registry && ! $tag && ! $bolus) continue;
                $status = $this->status($row);
                $existing = null;
                if ($registry) {
                    $inCooperative = DB::table('carabaos')->where('registry_number', $registry)->where('cooperative_id', $coopId)->get();
                    $existing = $inCooperative->first(fn ($animal) => $animal->farmer_id === $farmer?->id && $animal->status === $status);
                    if (! $existing && $inCooperative->isNotEmpty()) {
                        $hasClosedEpisode = $inCooperative->contains(fn ($animal) => in_array(mb_strtolower($animal->status), ['out', 'dead'], true));
                        if (in_array(mb_strtolower($status), ['out', 'dead'], true) || $hasClosedEpisode) {
                            // A closed owner record and a different receiving owner are separate transfer episodes.
                            $existing = null;
                        } else {
                            // Multiple Live rows without a preceding Out/Dead row are treated as a duplicate listing.
                            $existing = $inCooperative->first(fn ($animal) => mb_strtolower($animal->status) === 'live');
                        }
                    }
                    if (! $existing && $inCooperative->isEmpty()) {
                        $global = DB::table('carabaos')->where('registry_number', $registry)->get();
                        if ($global->isNotEmpty()) {
                            $hasClosedEpisode = $global->contains(fn ($animal) => in_array(mb_strtolower($animal->status), ['out', 'dead'], true));
                            if (in_array(mb_strtolower($status), ['out', 'dead'], true) || $hasClosedEpisode) {
                                $existing = null;
                            } else {
                                $existing = $global->first(fn ($animal) => mb_strtolower($animal->status) === 'live');
                            }
                        }
                    }
                } else {
                    $query = DB::table('carabaos')->where('cooperative_id', $coopId);
                    if ($tag) $query->where('tag_number', $tag);
                    elseif ($bolus) $query->where('bolus_id', $bolus);
                    else $query->where('farmer_id', $farmer?->id)->whereRaw('LOWER(animal_name) = ?', [mb_strtolower($animalName)]);
                    $existing = $query->where('farmer_id', $farmer?->id)->where('status', $status)->first();
                }

                $animalData = [
                    'farmer_id' => $farmer?->id,
                    'cooperative_id' => $coopId,
                    'animal_name' => $animalName,
                    'registry_number' => $registry,
                    'tag_number' => $tag,
                    'bolus_id' => $bolus,
                    'sex' => $this->value($row, 'sex'),
                    'breed' => $this->value($row, 'breed'),
                    'sire_id' => $this->value($row, 'sire_id'),
                    'sire_name' => $this->value($row, 'sire_name'),
                    'dam_id' => $this->value($row, 'dam_id'),
                    'dam_name' => $this->value($row, 'dam_name'),
                    'birth_date' => $this->date($this->value($row, 'birthday')),
                    'birth_place' => $this->value($row, 'birth_place'),
                    'breeder' => $this->value($row, 'breeder'),
                    'association_coop_institution' => $this->value($row, 'association_coop_institution'),
                    'barangay' => $this->value($row, 'barangay'),
                    'municipality' => $this->value($row, 'municipality'),
                    'province' => $this->value($row, 'province'),
                    'district' => $this->value($row, 'district'),
                    'region' => $this->value($row, 'region') ?: 'Region III',
                    'acquisition_date' => $this->date($this->value($row, 'date_of_acquisition')),
                    'status' => $status,
                    'reproductive_status' => $this->value($row, 'reproductive'),
                    'lactation_status' => $this->value($row, 'lactation'),
                    'parity' => is_numeric($this->value($row, 'parity')) ? (int) $this->value($row, 'parity') : null,
                    'death_date' => $this->date($this->value($row, 'death_date')),
                    'exit_reason' => $this->value($row, 'exit_reason'),
                    'updated_at' => now(),
                ];
                if ($existing) {
                    DB::table('carabaos')->where('id', $existing->id)->update(array_filter($animalData, fn ($v) => $v !== null && $v !== ''));
                } else {
                    $baseAnimalId = $registry ?: ($tag ?: ($bolus ?: 'CB-'.$coopId.'-'.str()->uuid()));
                    $animalData['animal_id'] = DB::table('carabaos')->where('animal_id', $baseAnimalId)->exists()
                        ? 'CB-'.$coopId.'-'.$baseAnimalId.'-F'.($farmer?->id ?? '0').'-'.strtoupper(substr($status, 0, 1))
                        : $baseAnimalId;
                    $animalData['created_at'] = now();
                    DB::table('carabaos')->insert($animalData);
                }
                $animals++;
            }
            return compact('farmers', 'animals', 'matched');
        });

        @unlink($path);
        $request->session()->forget('cooperative_import');

        return redirect()->route('admin.carabao-inventory')->with('success', "Import complete: {$result['farmers']} new farmer(s), {$result['matched']} matched farmer(s), and {$result['animals']} animal row(s) added or updated.");
    }

    private function readInventory(string $path): array
    {
        $book = null;
        $breedingRows = null;
        if (in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['xlsx', 'xlsm'], true)) {
            $rows = $this->readOpenXmlSheet($path, 'BD_ALL');
            $breedingRows = $this->readOpenXmlSheet($path, 'FHPR_Breeding');
            abort_if(! count($rows), 422, 'Could not find the BD_ALL inventory sheet in this workbook.');
        } else {
            $book = IOFactory::load($path);
            $sheet = $book->getSheetByName('BD_ALL') ?: $book->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
            $breeding = $book->getSheetByName('FHPR_Breeding');
            $breedingRows = $breeding?->toArray(null, true, true, false);
        }
        $headerRow = null;
        foreach (array_slice($rows, 0, 10, true) as $index => $values) {
            $joined = strtolower(implode('|', array_map('strval', $values)));
            if (str_contains($joined, 'farmer/recipient') && str_contains($joined, 'name of animal')) { $headerRow = $index; break; }
        }
        if ($headerRow === null) abort(422, 'Could not find the BD_ALL inventory headers (Farmer/Recipient and Name of Animal).');
        $headers = array_map(fn ($v) => strtolower(trim((string) $v)), $rows[$headerRow]);
        $out = [];
        foreach (array_slice($rows, $headerRow + 1) as $values) {
            $width = max(count($headers), count($values));
            $r = array_combine(array_pad(array_slice($headers, 0, $width), $width, ''), array_pad(array_slice($values, 0, $width), $width, null));
            $get = fn (array $names) => collect($names)->map(fn ($n) => $r[$n] ?? null)->first(fn ($v) => $v !== null && $v !== '');
            $out[] = [
                'herd_code' => $get(['herd code']),
                'farmer' => $get(['farmer/recipient']), 'animal' => $get(['name of animal']),
                'registry' => $get(['registry no.', 'registry no', 'registry number']), 'tag' => $get(['tag no.', 'tag no', 'tag no.']),
                'bolus' => $get(['bolus id/rfid', 'bolus id']), 'sex' => $get(['sex']), 'breed' => $get(['breed']),
                'sire_id' => $get(['sire id', 'sire id no.', 'sire/bull id', 'sire/bull id no.', 'sire registry id no.']),
                'sire_name' => $get(['sire name', 'name of sire', 'bull name']),
                'dam_id' => $get(['dam id', 'dam id no.', 'dam registry id no.']),
                'dam_name' => $get(['dam name', 'name of dam']),
                'birthday' => $get(['birthday']),
                'birth_place' => $get(['birth place', 'birthday place', 'place of birth']),
                'breeder' => $get(['breeder', 'name of breeder']),
                'association_coop_institution' => $get(['association/coop/institution', 'association / coop / institution', 'name of association/coop/institution', 'association coop institution', 'association/cooperative/institution']),
                'barangay' => $get(['barangay', 'brgy']),
                'municipality' => $get(['municipality', 'city/municipality']),
                'province' => $get(['province']),
                'district' => $get(['district']),
                'region' => $get(['region']),
                'date_of_acquisition' => $get(['date of acquisition', 'acquisition date']),
                'inventory_status' => $get(['inventory status']),
                'exit_reason' => $get(['nature for exit', 'reason of exit']), 'death_date' => $get(['date of disposal/death']),
            ];
        }
        if ($breedingRows) {
            $heads = array_map(fn ($v) => strtolower(trim((string) $v)), $breedingRows[0] ?? []);
            $col = fn (array $names) => collect($names)->map(fn ($name) => array_search($name, $heads, true))->first(fn ($index) => $index !== false);
            $registryCol = $col(['register id no.', 'register id no', 'register id no.']);
            $pregnancyCol = $col(['pregnant (+)']);
            $statusCol = $col(['status updated']);
            $parityCol = $col(['parity #', 'parity']);
            if ($registryCol !== null) {
                $breedingByRegistry = [];
                foreach (array_slice($breedingRows, 1) as $values) {
                    $registry = trim((string) ($values[$registryCol] ?? ''));
                    if ($registry === '') continue;
                    $pregnancy = trim((string) ($pregnancyCol !== null ? ($values[$pregnancyCol] ?? '') : ''));
                    $status = trim((string) ($statusCol !== null ? ($values[$statusCol] ?? '') : ''));
                    $breedingByRegistry[$this->normalize($registry)] = [
                        'reproductive' => $pregnancy === '+' ? 'Pregnant' : ($pregnancy !== '' && is_numeric($pregnancy) && (float) $pregnancy > 0 ? 'Pregnant' : null),
                        'lactation' => str_contains(strtolower($status), 'lactat') ? 'Lactating' : (str_contains(strtolower($status), 'dry') ? 'Dry' : null),
                        'parity' => $parityCol !== null && is_numeric($values[$parityCol] ?? null) ? (int) $values[$parityCol] : null,
                    ];
                }
                foreach ($out as &$record) {
                    $key = $this->normalize($record['registry'] ?? '');
                    if ($key !== '' && isset($breedingByRegistry[$key])) {
                        $record['reproductive'] = $breedingByRegistry[$key]['reproductive'];
                        $record['lactation'] = $breedingByRegistry[$key]['lactation'];
                        $record['parity'] = $breedingByRegistry[$key]['parity'];
                    }
                }
                unset($record);
            }
        }
        $book?->disconnectWorksheets();
        return $out;
    }

    private function readOpenXmlSheet(string $path, string $sheetName): array
    {
        $zip = new \ZipArchive();
        abort_if($zip->open($path) !== true, 422, 'Could not open the Excel workbook.');

        $mainNs = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $relNs = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $packageNs = 'http://schemas.openxmlformats.org/package/2006/relationships';
        $workbook = new \DOMDocument();
        $workbook->loadXML($zip->getFromName('xl/workbook.xml'), LIBXML_NONET | LIBXML_COMPACT);
        $workbookXpath = new \DOMXPath($workbook);
        $workbookXpath->registerNamespace('m', $mainNs);
        $workbookXpath->registerNamespace('r', $relNs);
        $rels = new \DOMDocument();
        $rels->loadXML($zip->getFromName('xl/_rels/workbook.xml.rels'), LIBXML_NONET | LIBXML_COMPACT);
        $relsXpath = new \DOMXPath($rels);
        $relsXpath->registerNamespace('p', $packageNs);

        $targets = [];
        foreach ($relsXpath->query('/p:Relationships/p:Relationship') as $relationship) {
            $targets[$relationship->getAttribute('Id')] = $relationship->getAttribute('Target');
        }

        $sheetPath = null;
        foreach ($workbookXpath->query('/m:workbook/m:sheets/m:sheet') as $sheetNode) {
            if ($sheetNode->getAttribute('name') === $sheetName) {
                $target = $targets[$sheetNode->getAttributeNS($relNs, 'id')] ?? null;
                if ($target) $sheetPath = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
                break;
            }
        }
        if (! $sheetPath || $zip->getFromName($sheetPath) === false) {
            $zip->close();
            return [];
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $sharedDoc = new \DOMDocument();
            $sharedDoc->loadXML($sharedXml, LIBXML_NONET | LIBXML_COMPACT);
            $sharedXpath = new \DOMXPath($sharedDoc);
            $sharedXpath->registerNamespace('m', $mainNs);
            foreach ($sharedXpath->query('/m:sst/m:si') as $item) $sharedStrings[] = $item->textContent;
        }

        $sheetDoc = new \DOMDocument();
        $sheetDoc->loadXML($zip->getFromName($sheetPath), LIBXML_NONET | LIBXML_COMPACT);
        $sheetXpath = new \DOMXPath($sheetDoc);
        $sheetXpath->registerNamespace('m', $mainNs);
        $rows = [];
        $maxColumn = 0;
        foreach ($sheetXpath->query('/m:worksheet/m:sheetData/m:row') as $rowNode) {
            $rowIndex = max(0, (int) $rowNode->getAttribute('r') - 1);
            $cells = [];
            foreach ($sheetXpath->query('m:c', $rowNode) as $cell) {
                if (! preg_match('/^([A-Z]+)\d+$/', $cell->getAttribute('r'), $match)) continue;
                $columnIndex = 0;
                foreach (str_split($match[1]) as $letter) $columnIndex = $columnIndex * 26 + ord($letter) - 64;
                $columnIndex--;
                $maxColumn = max($maxColumn, $columnIndex);
                $type = $cell->getAttribute('t');
                $valueNode = $sheetXpath->query('m:v', $cell)->item(0);
                $value = $valueNode?->textContent ?? '';
                if ($type === 's') $value = $sharedStrings[(int) $value] ?? '';
                elseif ($type === 'inlineStr') $value = $sheetXpath->query('m:is', $cell)->item(0)?->textContent ?? '';
                $cells[$columnIndex] = $value;
            }
            $rows[$rowIndex] = $cells;
        }
        $zip->close();

        if (! count($rows)) return [];
        ksort($rows);
        $lastRow = max(array_keys($rows));
        $output = [];
        for ($rowIndex = 0; $rowIndex <= $lastRow; $rowIndex++) {
            $output[] = array_replace(array_fill(0, $maxColumn + 1, ''), $rows[$rowIndex] ?? []);
        }
        return $output;
    }

    private function normalize(mixed $name): string
    {
        $name = mb_strtolower(trim((string) $name));
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^\pL\pN]+/u', ' ', $name)));
    }
    private function value(array $row, string $key): ?string { $v = trim((string) ($row[$key] ?? '')); return $v === '' ? null : $v; }
    private function status(array $row): string
    {
        $raw = mb_strtolower(trim((string) ($row['inventory_status'] ?? '')));
        if ($raw === 'out' && str_contains(mb_strtolower((string) ($row['exit_reason'] ?? '')), 'dead')) return 'Dead';
        return $raw === 'out' ? 'Out' : 'Live';
    }
    private function date(?string $value): ?string
    {
        if (! $value) return null;
        if (is_numeric($value)) return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        try { return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d'); } catch (\Throwable) { return null; }
    }
}
