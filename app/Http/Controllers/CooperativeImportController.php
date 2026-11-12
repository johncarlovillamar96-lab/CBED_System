<?php

namespace App\Http\Controllers;

use App\Support\SpreadsheetWorksheetReader;
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

        $result = $this->importInventoryRecords($this->readInventory($path), (int) $batch['cooperative_id']);

        @unlink($path);
        $request->session()->forget('cooperative_import');

        return redirect()->route('admin.carabao-inventory')->with('success', "Import complete: {$result['farmers']} new farmer(s), {$result['matched']} matched farmer(s), and {$result['animals']} animal row(s) added or updated.");
    }

    public function importInventorySheet(string $path, int $cooperativeId): array
    {
        return $this->importInventoryRecords($this->readInventory($path), $cooperativeId);
    }

    public function importInventoryWorksheet(
        array $rows,
        ?array $breedingRows,
        int $cooperativeId,
        ?callable $rowCooperativeResolver = null,
        ?callable $unmappedRowHandler = null
    ): array
    {
        $records = $this->parseInventoryRows($rows, $breedingRows);
        if ($rowCooperativeResolver === null) {
            return $this->importInventoryRecords($records, $cooperativeId);
        }

        $recordsByCooperative = [];
        $unmappedRows = 0;
        foreach ($records as $record) {
            $resolution = $rowCooperativeResolver($record);
            $rowCooperativeId = $resolution['cooperative_id'] ?? null;
            if ($rowCooperativeId === null) {
                if ($unmappedRowHandler !== null) {
                    $unmappedRowHandler($record, $resolution['identifier_id'] ?? null);
                }
                $unmappedRows++;
                continue;
            }
            $recordsByCooperative[$rowCooperativeId][] = $record;
        }

        $result = [
            'farmers' => 0,
            'animals' => 0,
            'matched' => 0,
            'rows' => count($records),
            'unmapped' => $unmappedRows,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => $unmappedRows,
        ];
        foreach ($recordsByCooperative as $rowCooperativeId => $cooperativeRecords) {
            $cooperativeResult = $this->importInventoryRecords($cooperativeRecords, (int) $rowCooperativeId);
            foreach (['farmers', 'animals', 'matched', 'inserted', 'updated', 'skipped'] as $key) {
                $result[$key] += $cooperativeResult[$key];
            }
        }

        return $result;
    }

    private function importInventoryRecords(array $records, int $coopId): array
    {
        return DB::transaction(function () use ($records, $coopId) {
            $seq = (int) DB::table('farmers')->where('cooperative_id', $coopId)->max('cooperative_sequence');
            $farmers = 0;
            $animals = 0;
            $animalsInserted = 0;
            $animalsUpdated = 0;
            $rowsSkipped = 0;
            $matched = 0;
            $farmersByName = [];
            foreach (DB::table('farmers')->where('cooperative_id', $coopId)->get() as $candidate) {
                foreach ($this->farmerNameVariants($candidate) as $variant) {
                    if ($variant !== '') {
                        $farmersByName[$variant][] = $candidate;
                    }
                }
            }

            $registries = collect($records)
                ->map(fn ($row) => $this->value($row, 'registry'))
                ->filter()
                ->unique()
                ->values();
            $animalsByRegistry = DB::table('carabaos')
                ->whereIn('registry_number', $registries)
                ->get()
                ->groupBy('registry_number');

            foreach ($records as $row) {
                $name = trim((string) ($row['farmer'] ?? ''));
                $animalName = trim((string) ($row['animal'] ?? ''));
                if ($name === '' && $animalName === '') {
                    $rowsSkipped++;
                    continue;
                }

                $farmer = null;
                if ($name !== '') {
                    $key = $this->normalize($name);
                    $matches = collect($farmersByName[$key] ?? [])->unique('id')->values();
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
                        foreach ($this->farmerNameVariants($farmer) as $variant) {
                            if ($variant !== '') {
                                $farmersByName[$variant][] = $farmer;
                            }
                        }
                        $farmers++;
                    } else {
                        $matched++;
                    }
                }

                $registry = $this->value($row, 'registry');
                $tag = $this->value($row, 'tag');
                $bolus = $this->value($row, 'bolus');
                if ($animalName === '' && ! $registry && ! $tag && ! $bolus) {
                    $rowsSkipped++;
                    continue;
                }
                $status = $this->status($row);
                $existing = null;
                if ($registry) {
                    $registryAnimals = $animalsByRegistry->get($registry, collect());
                    $inCooperative = $registryAnimals->where('cooperative_id', $coopId)->values();
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
                    if (! $existing && $inCooperative->isEmpty() && $registryAnimals->isNotEmpty()) {
                        $hasClosedEpisode = $registryAnimals->contains(fn ($animal) => in_array(mb_strtolower($animal->status), ['out', 'dead'], true));
                        if (! in_array(mb_strtolower($status), ['out', 'dead'], true) && ! $hasClosedEpisode) {
                            $existing = $registryAnimals->first(fn ($animal) => mb_strtolower($animal->status) === 'live');
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
                    $updates = array_filter($animalData, fn ($v) => $v !== null && $v !== '');
                    DB::table('carabaos')->where('id', $existing->id)->update($updates);
                    foreach ($updates as $column => $value) {
                        $existing->{$column} = $value;
                    }
                    $animalsUpdated++;
                } else {
                    $baseAnimalId = $registry ?: ($tag ?: ($bolus ?: 'CB-'.$coopId.'-'.str()->uuid()));
                    $animalData['animal_id'] = DB::table('carabaos')->where('animal_id', $baseAnimalId)->exists()
                        ? 'CB-'.$coopId.'-'.$baseAnimalId.'-F'.($farmer?->id ?? '0').'-'.strtoupper(substr($status, 0, 1))
                        : $baseAnimalId;
                    $animalData['created_at'] = now();
                    $animalId = DB::table('carabaos')->insertGetId($animalData);
                    if ($registry) {
                        $animalData['id'] = $animalId;
                        $animalsByRegistry->put(
                            $registry,
                            $animalsByRegistry->get($registry, collect())->push((object) $animalData)
                        );
                    }
                    $animalsInserted++;
                }
                $animals++;
            }
            return [
                'farmers' => $farmers,
                'animals' => $animals,
                'matched' => $matched,
                'rows' => count($records),
                'inserted' => $animalsInserted,
                'updated' => $animalsUpdated,
                'skipped' => $rowsSkipped,
            ];
        });
    }

    private function farmerNameVariants(object $farmer): array
    {
        $given = trim(($farmer->first_name ?? '').' '.($farmer->middle_name ?? ''));

        return array_unique([
            $this->normalize(trim($given.' '.($farmer->last_name ?? ''))),
            $this->normalize(trim(($farmer->last_name ?? '').' '.$given)),
            $this->normalize($farmer->last_name ?? ''),
        ]);
    }

    private function readInventory(string $path): array
    {
        $book = IOFactory::load($path);
        $findSheet = function (string $expected) use ($book) {
            $expectedName = preg_replace('/[^a-z0-9]/i', '', $expected);
            foreach ($book->getWorksheetIterator() as $worksheet) {
                if (strcasecmp(preg_replace('/[^a-z0-9]/i', '', $worksheet->getTitle()), $expectedName) === 0) {
                    return $worksheet;
                }
            }

            return null;
        };
        $sheet = $findSheet('BD_ALL');
        abort_if(! $sheet, 422, 'Could not find the BD_ALL inventory sheet in this workbook.');
        $rows = SpreadsheetWorksheetReader::rows($sheet);
        $breeding = $findSheet('FHPR_Breeding');
        $breedingRows = $breeding ? SpreadsheetWorksheetReader::rows($breeding) : null;
        $out = $this->parseInventoryRows($rows, $breedingRows);
        $book->disconnectWorksheets();
        return $out;
    }

    private function parseInventoryRows(array $rows, ?array $breedingRows): array
    {
        $headerRow = null;
        $normalizeHeader = fn ($value) => preg_replace('/[^a-z0-9]/i', '', strtolower(trim((string) $value)));
        foreach (array_slice($rows, 0, 30, true) as $index => $values) {
            $normalizedHeaders = array_map($normalizeHeader, $values);
            if (in_array('farmerrecipient', $normalizedHeaders, true) && in_array('nameofanimal', $normalizedHeaders, true)) {
                $headerRow = $index;
                break;
            }
        }
        if ($headerRow === null) abort(422, 'Could not find the BD_ALL inventory headers (Farmer/Recipient and Name of Animal).');
        $headers = array_map($normalizeHeader, $rows[$headerRow]);
        $out = [];
        foreach (array_slice($rows, $headerRow + 1, null, true) as $rowIndex => $values) {
            $width = max(count($headers), count($values));
            $r = array_combine(array_pad(array_slice($headers, 0, $width), $width, ''), array_pad(array_slice($values, 0, $width), $width, null));
            $get = fn (array $names) => collect($names)->map(fn ($n) => $r[$normalizeHeader($n)] ?? null)->first(fn ($v) => $v !== null && $v !== '');
            $out[] = [
                'source_row' => (int) $rowIndex + 1,
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
        return $out;
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
