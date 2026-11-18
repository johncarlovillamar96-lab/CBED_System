<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Support\AnimalClassification;

class MonitoringReportController extends Controller
{
    private const SPECIFICATIONS = [
        'calf_monitoring' => 'Calf Drop',
        'breeding' => 'Mating',
        'health_nutrition' => 'Health & Nutrition',
        'dry_off' => 'MTD',
    ];

    public function bcsScores()
    {
        return response()->json($this->bcsScoreOptions());
    }

    private function bcsScoreOptions(): array
    {
        return DB::table('bcs_records')->whereNotNull('body_condition_score')
            ->distinct()->orderBy('body_condition_score')->pluck('body_condition_score')
            ->map(fn ($score) => number_format((float) $score, 2, '.', ''))->values()->all();
    }

    public function animals(int $cooperative)
    {
        abort_unless(DB::table('cooperatives')->where('id', $cooperative)->exists(), 404);

        $animals = $this->eligibleAnimals($cooperative)
            ->orderBy('animal.registry_number')
            ->orderBy('animal.bolus_id')
            ->orderBy('animal.id')
            ->get([
                'animal.id as carabao_id', 'animal.animal_id', 'animal.bolus_id', 'animal.registry_number',
                'animal.tag_number', 'animal.animal_name', 'animal.sex', 'animal.birth_date', 'animal.breed',
                'animal.classification', 'animal.reproductive_status', 'animal.lactation_status',
                'animal.status as inventory_status', 'animal.cooperative_id', 'animal.farmer_id',
                'farmer.cooperative_id as farmer_cooperative_id', 'farmer.first_name', 'farmer.middle_name', 'farmer.last_name',
            ]);

        $ids = $animals->pluck('carabao_id')->map(fn ($id) => (int) $id)->all();
        $asOf = now()->toDateString();
        $latestBcs = DB::table('bcs_records')->whereIn('carabao_id', $ids)->whereNotNull('body_condition_score')
            ->orderByDesc('record_date')->orderByDesc('id')->get(['carabao_id', 'record_date', 'body_condition_score'])->unique('carabao_id')->keyBy('carabao_id');
        $lastCalving = [];
        foreach (DB::table('calves')->whereIn('dam_id', $ids)->whereDate('birth_date', '<=', $asOf)
            ->where(fn ($query) => $query->whereNull('birth_outcome')->orWhere('birth_outcome', '<>', 'Abortion'))
            ->orderByDesc('birth_date')->get(['dam_id', 'birth_date']) as $calf) {
            $lastCalving[(int) $calf->dam_id] ??= $calf->birth_date;
        }
        foreach (DB::table('mating_records')->whereIn('carabao_id', $ids)->whereNotNull('actual_calving_date')
            ->whereDate('actual_calving_date', '<=', $asOf)->get(['carabao_id', 'actual_calving_date']) as $record) {
            $key = (int) $record->carabao_id;
            if (! isset($lastCalving[$key]) || $record->actual_calving_date > $lastCalving[$key]) $lastCalving[$key] = $record->actual_calving_date;
        }
        if (Schema::hasTable('fhpr_records') && Schema::hasColumn('fhpr_records', 'present_calving_date')) {
            foreach (DB::table('fhpr_records')->whereIn('carabao_id', $ids)->whereNotNull('present_calving_date')
                ->whereDate('present_calving_date', '<=', $asOf)->get(['carabao_id', 'present_calving_date']) as $record) {
                $key = (int) $record->carabao_id;
                if (! isset($lastCalving[$key]) || $record->present_calving_date > $lastCalving[$key]) $lastCalving[$key] = $record->present_calving_date;
            }
        }
        $dryOffDates = DB::table('mtd_records')->whereIn('carabao_id', $ids)->whereNotNull('dryoff_date')->whereDate('dryoff_date', '<=', $asOf)
            ->selectRaw('carabao_id, MAX(dryoff_date) as dry_off_date')->groupBy('carabao_id')->pluck('dry_off_date', 'carabao_id');
        if (Schema::hasTable('fhpr_records') && Schema::hasColumn('fhpr_records', 'dry_off_date')) {
            foreach (DB::table('fhpr_records')->whereIn('carabao_id', $ids)->whereNotNull('dry_off_date')->whereDate('dry_off_date', '<=', $asOf)
                ->selectRaw('carabao_id, MAX(dry_off_date) as dry_off_date')->groupBy('carabao_id')->get() as $record) {
                $key = (int) $record->carabao_id;
                if (! isset($dryOffDates[$key]) || $record->dry_off_date > $dryOffDates[$key]) $dryOffDates[$key] = $record->dry_off_date;
            }
        }
        $pregnancyChecks = [];
        foreach (DB::table('mating_records')->whereIn('carabao_id', $ids)->whereNotNull('pregnancy_check_date')
            ->whereIn('pregnancy_result', ['Positive', 'Negative', 'Pregnant', 'Not Pregnant'])
            ->whereDate('pregnancy_check_date', '<=', $asOf)->orderBy('pregnancy_check_date')->orderBy('id')
            ->get(['carabao_id', 'pregnancy_check_date', 'pregnancy_result']) as $record) {
            $pregnancyChecks[(int) $record->carabao_id] = $record;
        }

        return response()->json($animals->map(function ($animal) use ($latestBcs, $lastCalving, $dryOffDates, $pregnancyChecks, $asOf) {
            $id = (int) $animal->carabao_id;
            $pregnancyCheck = $pregnancyChecks[$id] ?? null;
            $classification = AnimalClassification::classify([
                'sex' => $animal->sex, 'birth_date' => $animal->birth_date,
                'inventory_classification' => $animal->classification,
                'last_calving_date' => $lastCalving[$id] ?? null,
                'dry_off_date' => $dryOffDates[$id] ?? null,
                'pregnancy_result' => $pregnancyCheck?->pregnancy_result,
                'pregnancy_date' => $pregnancyCheck?->pregnancy_check_date,
                'as_of' => $asOf, 'dryoff_review_dim' => config('cbed.dry_off_review_dim', 280),
            ]);
            $bcs = $latestBcs->get($id);
            return [
                'carabao_id' => (int) $animal->carabao_id,
                'animal_id' => $animal->animal_id,
                'bolus_id' => $animal->bolus_id,
                'registry_number' => $animal->registry_number,
                'tag_number' => $animal->tag_number,
                'animal_name' => $animal->animal_name,
                'sex' => $animal->sex,
                'birth_date' => $animal->birth_date,
                'breed' => $animal->breed,
                'classification' => $classification['classification'],
                'last_calving_date' => $classification['last_calving_date'],
                'days_in_milk' => $classification['dim'],
                'dry_off_date' => $classification['dry_off_date'],
                'dry_off_review' => $classification['dry_off_review'],
                'pregnancy_status' => $classification['pregnancy'],
                'latest_bcs' => $bcs?->body_condition_score,
                'bcs_date' => $bcs?->record_date,
                'lactation_status' => $animal->lactation_status,
                'inventory_status' => $animal->inventory_status,
                'farmer_id' => $animal->farmer_id,
                'farmer_name' => trim(implode(' ', array_filter([$animal->first_name, $animal->middle_name, $animal->last_name]))),
            ];
        }));
    }

    private function eligibleAnimals(int $cooperativeId): \Illuminate\Database\Query\Builder
    {
        $exitPattern = 'out|dead|deceas|culled|sold|transfer|dispos|exit';
        $animals = DB::table('carabaos as animal')
            ->leftJoin('farmers as farmer', 'farmer.id', '=', 'animal.farmer_id')
            ->whereRaw('COALESCE(animal.cooperative_id, farmer.cooperative_id) = ?', [$cooperativeId])
            ->whereRaw("LOWER(TRIM(COALESCE(animal.sex, ''))) IN ('female', 'f')")
            ->whereRaw("LOWER(TRIM(COALESCE(animal.status, ''))) IN ('live', 'alive', 'active')")
            ->whereNull('animal.death_date')
            ->whereRaw("LOWER(COALESCE(animal.exit_reason, '')) NOT REGEXP ?", [$exitPattern])
            ->whereNotExists(fn ($query) => $query->from('nature_of_exits')
                ->whereColumn('nature_of_exits.carabao_id', 'animal.id')
                ->whereRaw("LOWER(COALESCE(nature_of_exits.nature_of_exit, '')) REGEXP ?", [$exitPattern]));

        if (Schema::hasColumn('carabaos', 'nature_of_exit')) {
            $animals->whereRaw("LOWER(COALESCE(animal.nature_of_exit, '')) NOT REGEXP ?", [$exitPattern]);
        }

        return $animals;
    }

    public function store(Request $request)
    {
        $validated = $this->validateReport($request, true);
        $cooperative = DB::table('cooperatives')->where('id', $validated['cooperative_id'])->first();
        abort_unless($cooperative, 404);

        $reportId = DB::transaction(function () use ($request, $validated, $cooperative) {
            $reportId = DB::table('monitoring_reports')->insertGetId([
                'cooperative_id' => $cooperative->id,
                'created_by' => auth()->id(),
                'cooperative_name' => $cooperative->cooperative_name,
                'province' => $cooperative->province,
                'municipality' => $cooperative->municipality,
                'barangay' => $cooperative->barangay,
                'report_date' => $validated['monitoring_date'],
                'monitored_by' => $validated['monitored_by'] ?? null,
                'attested_by' => $validated['attested_by'] ?? null,
                'noted_by' => $validated['noted_by'] ?? null,
                'report_data' => json_encode(['version' => 2, 'rows' => []], JSON_UNESCAPED_UNICODE),
                'remarks' => $validated['remarks'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->saveAnimalDetails($reportId, (int) $cooperative->id, $validated['rows'], $validated['monitoring_date']);

            return $reportId;
        });

        return redirect()->route('ui.monitoring', ['report_id' => $reportId])
            ->with('success', 'Monitoring report saved with the selected animals and service records.');
    }

    public function update(Request $request, int $report)
    {
        $reportRecord = DB::table('monitoring_reports')->where('id', $report)->first();
        abort_unless($reportRecord, 404);
        $validated = $this->validateReport($request, false);

        DB::transaction(function () use ($validated, $reportRecord) {
            DB::table('monitoring_reports')->where('id', $reportRecord->id)->update([
                'report_date' => $validated['monitoring_date'],
                'monitored_by' => $validated['monitored_by'] ?? null,
                'attested_by' => $validated['attested_by'] ?? null,
                'noted_by' => $validated['noted_by'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'updated_at' => now(),
            ]);

            $this->saveAnimalDetails((int) $reportRecord->id, (int) $reportRecord->cooperative_id, $validated['rows'], $validated['monitoring_date'], true);
        });

        return redirect()->route('ui.monitoring', ['report_id' => $reportRecord->id])
            ->with('success', 'Monitoring report and its linked module records were updated.');
    }

    public function editData(int $report)
    {
        $record = DB::table('monitoring_reports')->where('id', $report)->first();
        abort_unless($record, 404);

        return response()->json([
            'report' => [
                'id' => $record->id,
                'cooperative_id' => $record->cooperative_id,
                'cooperative_name' => $record->cooperative_name,
                'monitoring_date' => $record->report_date,
                'monitored_by' => $record->monitored_by,
                'attested_by' => $record->attested_by,
                'noted_by' => $record->noted_by,
                'remarks' => $record->remarks,
            ],
            'rows' => $this->reportRows((int) $record->id, $record->report_data),
        ]);
    }

    public function show(int $report)
    {
        $report = DB::table('monitoring_reports')->where('id', $report)->first();
        abort_unless($report, 404);
        $report->legacy_only = ! DB::table('monitoring_report_details')->where('monitoring_report_id', $report->id)->exists();
        $report->report_data = ['rows' => $this->reportRows((int) $report->id, $report->report_data)];
        $bcsScores = $this->bcsScoreOptions();
        $semenBulls = DB::table('semen_bulls')->whereRaw('LOWER(status) = ?', ['active'])->orderBy('bull_name')->get(['id','bull_id','bull_name','breed']);
        $technicianOptions = DB::table('mating_records')->whereNotNull('technician')->where('technician','<>','')->distinct()->orderBy('technician')->pluck('technician');

        return view('monitoring-report', compact('report', 'bcsScores', 'semenBulls', 'technicianOptions'));
    }

    public function export(int $report)
    {
        $record = DB::table('monitoring_reports')->where('id', $report)->first();
        abort_unless($record, 404);
        $rows = $this->reportRows((int) $record->id, $record->report_data);
        $columns = [
            'No.', 'Cooperative', 'Farmer', 'Bolus ID', 'Registry ID', 'Tag No.', 'Animal Name', 'Sex', 'Birthday', 'Breed',
            'Body Condition Score (BCS)', 'Availability', 'Reason', 'Monitoring Specification', 'Monitoring Status', 'Monitoring Details', 'Remarks',
        ];

        return response()->streamDownload(function () use ($record, $rows, $columns) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, ['Animal Monitoring Report']);
            fputcsv($file, ['Cooperative/Association', $record->cooperative_name, 'Monitoring Date', $record->report_date]);
            fputcsv($file, ['Province', $record->province, 'Municipality', $record->municipality, 'Barangay', $record->barangay]);
            fputcsv($file, []);
            fputcsv($file, $columns);
            foreach ($rows as $index => $row) {
                fputcsv($file, [
                    $index + 1, $record->cooperative_name, $row['farmer_name'] ?? '', $row['bolus_id'] ?? '',
                    $row['registry_id'] ?? '', $row['tag_number'] ?? '', $row['animal_name'] ?? '', $row['sex'] ?? '',
                    $row['birth_date'] ?? '', $row['breed'] ?? '', $row['body_condition_score'] ?? '', $row['availability_status'] ?? '',
                    $row['unavailability_reason'] ?? '', $row['monitoring_specification'] ?? '',
                    $row['monitoring_status'] ?? '', json_encode($row['monitoring_data'] ?? [], JSON_UNESCAPED_UNICODE),
                    $row['remarks'] ?? '',
                ]);
            }
            fclose($file);
        }, Str::slug($record->cooperative_name).'-monitoring-'.$record->report_date.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function reportRows(int $reportId, ?string $reportData): array
    {
        $details = DB::table('monitoring_report_details')->where('monitoring_report_id', $reportId)->orderBy('id')->get();
        if ($details->isNotEmpty()) {
            return $details->map(function ($detail) {
                return [
                    'detail_id' => (int) $detail->id,
                    'carabao_id' => $detail->carabao_id,
                    'animal_id' => $detail->animal_database_id,
                    'bolus_id' => $detail->bolus_id,
                    'registry_id' => $detail->registry_number,
                    'tag_number' => $detail->tag_number,
                    'animal_name' => $detail->animal_name,
                    'sex' => $detail->sex,
                    'birth_date' => $detail->birth_date,
                    'breed' => $detail->breed,
                    'body_condition_score' => $detail->body_condition_score,
                    'farmer_name' => $detail->farmer_name ?? '',
                    'availability_status' => $detail->availability_status,
                    'unavailability_reason' => $detail->unavailability_reason,
                    'monitoring_specification' => $detail->monitoring_specification,
                    'destination_module' => $detail->destination_module,
                    'destination_record_id' => $detail->destination_record_id,
                    'destination_inventory_id' => $detail->destination_inventory_id,
                    'monitoring_status' => $detail->monitoring_status,
                    'monitoring_data' => json_decode($detail->monitoring_data, true) ?: [],
                    'remarks' => $detail->remarks,
                ];
            })->all();
        }

        $legacy = json_decode((string) $reportData, true) ?: [];
        return array_values($legacy['rows'] ?? []);
    }

    private function validateReport(Request $request, bool $creating): array
    {
        $rules = [
            'monitoring_date' => ['required', 'date'],
            'monitored_by' => ['nullable', 'string', 'max:255'],
            'attested_by' => ['nullable', 'string', 'max:255'],
            'noted_by' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'rows' => ['required', 'array', 'min:1', 'max:2000'],
            'rows.*.carabao_id' => ['required', 'integer', 'distinct', 'exists:carabaos,id'],
            'rows.*.body_condition_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'rows.*.availability_status' => ['required', 'in:available,unavailable'],
            'rows.*.unavailability_reason' => ['nullable', 'in:Not Present,Transferred,Sick,Dead,Sold,Other'],
            'rows.*.monitoring_specification' => ['nullable', 'in:calf_monitoring,breeding,health_nutrition,dry_off'],
            'rows.*.monitoring_data' => ['nullable', 'array'],
            'rows.*.monitoring_services' => ['nullable', 'array:calf_monitoring,breeding,health_nutrition,dry_off'],
            'rows.*.monitoring_services.*' => ['required', 'json'],
            'rows.*.remarks' => ['nullable', 'string', 'max:2000'],
        ];
        if ($creating) $rules['cooperative_id'] = ['required', 'integer', 'exists:cooperatives,id'];
        $validated = $request->validate($rules);

        $submittedIds = collect($validated['rows'])->pluck('carabao_id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        if ($creating) {
            $eligibleCount = $this->eligibleAnimals((int) $validated['cooperative_id'])
                ->whereIn('animal.id', $submittedIds)->count();
            abort_unless($eligibleCount === count($submittedIds), 422, 'One or more selected animals are no longer eligible. Reload the cooperative roster.');
        } else {
            $reportId = (int) $request->route('report');
            $reportIds = DB::table('monitoring_report_details')->where('monitoring_report_id', $reportId)
                ->pluck('carabao_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            if ($reportIds !== $submittedIds) abort(422, 'The report animal list changed. Reload the report before saving.');
        }

        foreach ($validated['rows'] as $index => &$row) {
            if (($row['availability_status'] ?? 'available') !== 'unavailable') {
                $this->validateSpecification($row['monitoring_specification'] ?? null, $row['monitoring_data'] ?? [], $index);
                foreach ($row['monitoring_services'] ?? [] as $service => $payload) {
                    $data = json_decode($payload, true);
                    abort_unless(is_array($data), 422, 'Invalid monitoring service details.');
                    $this->validateSpecification($service, $data, $index);
                    $row['monitoring_services'][$service] = $data;
                }
            }
        }
        unset($row);

        return $validated;
    }

    private function validateSpecification(?string $specification, array $data, int $index): void
    {
        if (! $specification) return;
        $rules = match ($specification) {
            'calf_monitoring' => [
                'birth_outcome' => ['nullable', 'in:Live Birth,Stillbirth,Abortion'], 'calf_status' => ['nullable', 'in:Live,Out,Dead'],
                'date_of_birth' => ['nullable', 'date'], 'sex' => ['nullable', 'in:Male,Female'], 'calf_id' => ['nullable', 'string', 'max:255'],
                'breed' => ['nullable', 'string', 'max:255'], 'sire_id' => ['nullable', 'string', 'max:255'],
                'birth_weight' => ['nullable', 'numeric', 'min:0', 'max:1000'], 'findings' => ['nullable', 'string', 'max:2000'], 'remarks' => ['nullable', 'string', 'max:2000'],
            ],
            'breeding' => [
                'breeding_date' => ['nullable', 'date'], 'method' => ['nullable', 'in:AI,NM'], 'semen_bull_id' => ['nullable', 'integer', 'exists:semen_bulls,id'],
                'bull_id' => ['nullable', 'string', 'max:255'], 'semen_used' => ['nullable', 'integer', 'min:0'], 'pd_date' => ['nullable', 'date'],
                'record_date' => ['nullable', 'date'], 'pregnancy_status' => ['nullable', 'in:Pregnant,Not Pregnant,Inconclusive'],
                'estrus_type' => ['nullable', 'in:ES,NH,FTAI'], 'technician' => ['nullable', 'string', 'max:255'], 'pregnancy_months' => ['nullable', 'integer', 'min:0', 'max:11'],
                'expected_calving' => ['nullable', 'date'], 'natural_mating_details' => ['nullable', 'string', 'max:2000'], 'findings' => ['nullable', 'string', 'max:2000'], 'remarks' => ['nullable', 'string', 'max:2000'],
            ],
            'health_nutrition' => [
                'health_status' => ['nullable', 'in:Healthy,Under Observation,Under Treatment,Needs Follow-up'], 'body_condition' => ['nullable', 'string', 'max:100'],
                'symptoms' => ['nullable', 'string', 'max:2000'], 'findings' => ['nullable', 'string', 'max:2000'], 'treatment' => ['nullable', 'string', 'max:2000'],
                'medication' => ['nullable', 'string', 'max:1000'], 'recommendations' => ['nullable', 'string', 'max:2000'], 'follow_up_date' => ['nullable', 'date'], 'remarks' => ['nullable', 'string', 'max:2000'],
                'no_of_visits_monitored' => ['nullable', 'integer', 'min:0', 'max:100000'], 'no_of_treatments' => ['nullable', 'integer', 'min:0', 'max:100000'],
                'services' => ['nullable', 'array', 'max:100'],
                'services.*.selected' => ['nullable', 'boolean'],
                'services.*.type' => ['nullable', 'string', 'max:100'],
                'services.*.other_service_name' => ['nullable', 'string', 'max:255'],
                'services.*.dosage_ml' => ['nullable', 'numeric', 'min:0', 'max:100000'],
                'services.*.remarks' => ['nullable', 'string', 'max:2000'],
            ],
            'dry_off' => [
                'dry_off_confirmed' => ['required', 'in:Yes,No'], 'dry_off_date' => ['nullable', 'date'],
                'days_in_milk' => ['nullable', 'integer', 'min:0', 'max:10000'],
                'findings' => ['nullable', 'string', 'max:2000'], 'remarks' => ['nullable', 'string', 'max:2000'],
            ],
            default => [],
        };
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) abort(422, 'Animal row '.($index + 1).': '.$validator->errors()->first());
        $required = match ($specification) {
            'calf_monitoring' => ['date_of_birth', 'birth_outcome'],
            'breeding' => ['breeding_date', 'method', 'semen_bull_id'],
            'health_nutrition' => [],
            default => [],
        };
        foreach ($required as $field) {
            if (trim((string) ($data[$field] ?? '')) === '') abort(422, 'Animal row '.($index + 1).': '.$field.' is required for this monitoring specification.');
        }
        if ($specification === 'calf_monitoring' && ($data['birth_outcome'] ?? null) === 'Live Birth' && empty($data['sex'])) {
            abort(422, 'Animal row '.($index + 1).': calf sex is required for a live birth.');
        }
        if ($specification === 'breeding' && ($data['method'] ?? null) === 'AI') {
            foreach (['pregnancy_status'=>'Pregnancy Diagnosis','estrus_type'=>'Type of Estrus','technician'=>'AI Technician'] as $field=>$label) {
                if (trim((string)($data[$field]??''))==='') abort(422,'Animal row '.($index+1).': '.$label.' is required for AI.');
            }
        }
        if ($specification === 'health_nutrition') {
            $services = $this->selectedHealthServices($data['services'] ?? []);
            if (empty($data['health_status']) && $services === []) {
                abort(422, 'Animal row '.($index + 1).': select a health status or at least one Health and Nutrition service.');
            }
            foreach ($services as $service) {
                if ($service['type'] === 'Other Services' && $service['other_service_name'] === '') {
                    abort(422, 'Animal row '.($index + 1).': enter the name of the other service.');
                }
            }
            $types = array_column($services, 'type');
            if (count($types) !== count(array_unique($types))) {
                abort(422, 'Animal row '.($index + 1).': each Health and Nutrition service may only be selected once.');
            }
        }
        if ($specification === 'dry_off' && ($data['dry_off_confirmed'] ?? null) === 'Yes' && empty($data['dry_off_date'])) {
            abort(422, 'Animal row '.($index + 1).': enter the actual dry-off date to confirm dry-off.');
        }
    }

    private function selectedHealthServices(array $services): array
    {
        return collect($services)->filter(fn ($service) => is_array($service) && filter_var($service['selected'] ?? false, FILTER_VALIDATE_BOOLEAN))
            ->map(fn ($service) => [
                'type' => trim((string) ($service['type'] ?? '')),
                'other_service_name' => trim((string) ($service['other_service_name'] ?? '')),
                'dosage_ml' => ($service['dosage_ml'] ?? '') !== '' ? (string) $service['dosage_ml'] : null,
                'remarks' => trim((string) ($service['remarks'] ?? '')),
            ])->values()->all();
    }

    private function saveAnimalDetails(int $reportId, int $cooperativeId, array $rows, string $monitoringDate, bool $updating = false): void
    {
        foreach ($rows as $row) {
            $animal = DB::table('carabaos as animal')->leftJoin('farmers as farmer', 'farmer.id', '=', 'animal.farmer_id')
                ->where('animal.id', (int) $row['carabao_id'])
                ->whereRaw('COALESCE(animal.cooperative_id, farmer.cooperative_id) = ?', [$cooperativeId])
                ->first(['animal.*', 'farmer.cooperative_id as farmer_cooperative_id', 'farmer.first_name', 'farmer.middle_name', 'farmer.last_name']);
            abort_unless($animal, 422, 'An animal is no longer assigned to this cooperative. Reload the report.');
            if (! $updating) {
                $isLiveFemale = in_array(mb_strtolower(trim((string) $animal->sex)), ['female', 'f'], true)
                    && in_array(mb_strtolower(trim((string) $animal->status)), ['live', 'alive', 'active'], true)
                    && empty($animal->death_date)
                    && ! preg_match('/out|dead|deceas|culled|sold|transfer|dispos|exit/i', (string) ($animal->exit_reason ?? '').' '.(string) ($animal->nature_of_exit ?? ''))
                    && ! DB::table('nature_of_exits')->where('carabao_id', $animal->id)
                        ->whereRaw("LOWER(COALESCE(nature_of_exit, '')) REGEXP ?", ['out|dead|deceas|culled|sold|transfer|dispos|exit'])->exists();
                abort_unless($isLiveFemale, 422, 'Only live female animals can be added to a reproductive monitoring report.');
            }

            $existing = $updating ? DB::table('monitoring_report_details')->where('monitoring_report_id', $reportId)->where('carabao_id', $animal->id)->first() : null;
            $availability = $row['availability_status'] ?? 'available';
            $specification = $availability === 'unavailable' ? null : ($row['monitoring_specification'] ?? null);
            $details = $availability === 'unavailable' ? [] : ($row['monitoring_data'] ?? []);
            $services = $availability === 'available' ? ($row['monitoring_services'] ?? []) : [];
            $previousDetails = json_decode((string) ($existing->monitoring_data ?? ''), true) ?: [];
            $additional = $previousDetails['_additional_services'] ?? [];
            if ($services !== []) {
                $specification = array_key_first($services);
                $details = $services[$specification];
                unset($services[$specification]);
            }
            $hasBcsInput = array_key_exists('body_condition_score', $row);
            $bodyConditionScore = $availability === 'unavailable'
                ? ($existing->body_condition_score ?? null)
                : ($hasBcsInput
                    ? (($row['body_condition_score'] ?? '') !== ''
                        ? number_format((float) $row['body_condition_score'], 2, '.', '')
                        : null)
                    : ($existing->body_condition_score ?? null));
            $payload = [
                'availability_status' => $availability,
                'unavailability_reason' => $availability === 'unavailable' ? ($row['unavailability_reason'] ?? null) : null,
                'monitoring_specification' => $specification,
                'monitoring_data' => json_encode($details, JSON_UNESCAPED_UNICODE),
                'body_condition_score' => $bodyConditionScore,
                'remarks' => $row['remarks'] ?? null,
                'updated_at' => now(),
            ];
            if ($existing) {
                DB::table('monitoring_report_details')->where('id', $existing->id)->update($payload);
                $detailId = (int) $existing->id;
            } else {
                $detailId = DB::table('monitoring_report_details')->insertGetId($payload + [
                    'monitoring_report_id' => $reportId, 'carabao_id' => $animal->id, 'animal_database_id' => $animal->animal_id,
                    'bolus_id' => $animal->bolus_id, 'registry_number' => $animal->registry_number, 'tag_number' => $animal->tag_number,
                    'animal_name' => $animal->animal_name,
                    'farmer_name' => trim(implode(' ', array_filter([$animal->first_name, $animal->middle_name, $animal->last_name]))),
                    'sex' => $animal->sex, 'birth_date' => $animal->birth_date, 'breed' => $animal->breed,
                    'monitoring_status' => $availability === 'unavailable' ? 'unavailable' : 'pending', 'created_at' => now(),
                ]);
            }
            $detail = DB::table('monitoring_report_details')->where('id', $detailId)->first();
            if ($availability === 'available' && $bodyConditionScore !== null) {
                $this->syncBcsRecord($detailId, $cooperativeId, $animal, $monitoringDate, $bodyConditionScore, $reportId);
            } elseif ($availability === 'available' && $hasBcsInput && $existing && $existing->body_condition_score !== null) {
                $this->unlinkBcsRecord($detailId);
            }
            $result = $availability === 'unavailable'
                ? ['monitoring_status' => 'unavailable']
                : $this->syncDestination($detail, $animal, $specification, $details, $monitoringDate, $reportId);
            if ($additional !== [] || $services !== []) {
                $primaryData = json_decode((string) DB::table('monitoring_report_details')->where('id', $detailId)->value('monitoring_data'), true) ?: [];
            }
            foreach ($services as $service => $data) {
                $serviceDetail = clone $detail;
                $serviceDetail->destination_module = $additional[$service]['destination_module'] ?? null;
                $serviceDetail->destination_record_id = $additional[$service]['destination_record_id'] ?? null;
                $serviceDetail->destination_inventory_id = $additional[$service]['destination_inventory_id'] ?? null;
                $additional[$service] = ['data' => $data] + $this->syncDestination(
                    $serviceDetail, $animal, $service, $data, $monitoringDate, $reportId
                );
            }
            if ($additional !== []) {
                $result['monitoring_data'] = json_encode($primaryData + ['_additional_services' => $additional], JSON_UNESCAPED_UNICODE);
            }
            DB::table('monitoring_report_details')->where('id', $detailId)->update($result + ['updated_at' => now()]);
            $this->syncInventoryClassification($animal);
        }
    }

    private function syncInventoryClassification(object $animal): void
    {
        $asOf = now()->toDateString();
        $lastCalving = DB::table('calves')->where('dam_id', $animal->id)->whereDate('birth_date', '<=', $asOf)
            ->where(fn ($query) => $query->whereNull('birth_outcome')->orWhere('birth_outcome', '<>', 'Abortion'))->max('birth_date');
        $recordedCalving = DB::table('mating_records')->where('carabao_id', $animal->id)->whereNotNull('actual_calving_date')->whereDate('actual_calving_date', '<=', $asOf)->max('actual_calving_date');
        if ($recordedCalving && (! $lastCalving || $recordedCalving > $lastCalving)) $lastCalving = $recordedCalving;
        if (Schema::hasTable('fhpr_records') && Schema::hasColumn('fhpr_records', 'present_calving_date')) {
            $fhprCalving = DB::table('fhpr_records')->where('carabao_id', $animal->id)->whereNotNull('present_calving_date')
                ->whereDate('present_calving_date', '<=', $asOf)->max('present_calving_date');
            if ($fhprCalving && (! $lastCalving || $fhprCalving > $lastCalving)) $lastCalving = $fhprCalving;
        }

        $dryOff = null;
        if (Schema::hasTable('mtd_records') && Schema::hasColumn('mtd_records', 'dryoff_date')) {
            $dryOff = DB::table('mtd_records')->where('carabao_id', $animal->id)->whereNotNull('dryoff_date')->whereDate('dryoff_date', '<=', $asOf)->max('dryoff_date');
        }
        if (Schema::hasTable('fhpr_records') && Schema::hasColumn('fhpr_records', 'dry_off_date')) {
            $fhprDryOff = DB::table('fhpr_records')->where('carabao_id', $animal->id)->whereNotNull('dry_off_date')->whereDate('dry_off_date', '<=', $asOf)->max('dry_off_date');
            if ($fhprDryOff && (! $dryOff || $fhprDryOff > $dryOff)) $dryOff = $fhprDryOff;
        }

        $pregnancyCheck = DB::table('mating_records')->where('carabao_id', $animal->id)
            ->whereNotNull('pregnancy_check_date')->whereIn('pregnancy_result', ['Positive', 'Negative', 'Pregnant', 'Not Pregnant'])->whereDate('pregnancy_check_date', '<=', $asOf)
            ->orderByDesc('pregnancy_check_date')->orderByDesc('id')->first(['pregnancy_check_date', 'pregnancy_result']);
        $classification = AnimalClassification::classify([
            'sex' => $animal->sex,
            'birth_date' => $animal->birth_date,
            'inventory_classification' => $animal->classification,
            'last_calving_date' => $lastCalving,
            'dry_off_date' => $dryOff,
            'pregnancy_result' => $pregnancyCheck?->pregnancy_result,
            'pregnancy_date' => $pregnancyCheck?->pregnancy_check_date,
            'as_of' => $asOf,
            'dryoff_review_dim' => config('cbed.dry_off_review_dim', 280),
        ]);

        if ($classification['classification'] !== 'Unknown / Needs Verification') {
            DB::table('carabaos')->where('id', $animal->id)->update([
                'classification' => $classification['classification'],
                'updated_at' => now(),
            ]);
        }
    }

    private function syncBcsRecord(int $detailId, int $cooperativeId, object $animal, string $monitoringDate, string $score, int $reportId): void
    {
        $record = DB::table('bcs_records')->where('monitoring_report_detail_id', $detailId)->first();
        if (! $record) {
            $record = DB::table('bcs_records')
                ->where('carabao_id', $animal->id)
                ->where('cooperative_id', $cooperativeId)
                ->where('registry_number', $animal->registry_number)
                ->whereDate('record_date', $monitoringDate)
                ->where('body_condition_score', $score)
                ->whereNull('monitoring_report_detail_id')
                ->orderBy('id')->first();
        }

        $sourceData = $record ? json_decode((string) $record->source_data, true) : [];
        if (! is_array($sourceData)) {
            $sourceData = $record && $record->source_data ? ['original_source_data' => $record->source_data] : [];
        }
        $sourceData['monitoring_report_id'] = $reportId;
        $sourceData['monitoring_report_detail_id'] = $detailId;
        $values = [
            'cooperative_id' => $cooperativeId,
            'carabao_id' => $animal->id,
            'registry_number' => $animal->registry_number,
            'record_date' => $monitoringDate,
            'body_condition_score' => $score,
            'monitoring_report_detail_id' => $detailId,
            'source_data' => json_encode($sourceData, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ];

        if ($record) {
            DB::table('bcs_records')->where('id', $record->id)->update($values);
        } else {
            DB::table('bcs_records')->insert($values + ['created_at' => now()]);
        }
    }

    private function unlinkBcsRecord(int $detailId): void
    {
        $record = DB::table('bcs_records')->where('monitoring_report_detail_id', $detailId)->first();
        if (! $record) return;

        $sourceData = json_decode((string) $record->source_data, true);
        if (is_array($sourceData)) {
            unset($sourceData['monitoring_report_id'], $sourceData['monitoring_report_detail_id']);
            $sourceData = json_encode($sourceData, JSON_UNESCAPED_UNICODE);
        } else {
            $sourceData = $record->source_data;
        }

        DB::table('bcs_records')->where('id', $record->id)->update([
            'monitoring_report_detail_id' => null,
            'source_data' => $sourceData,
            'updated_at' => now(),
        ]);
    }

    private function syncDestination(object $detail, object $animal, ?string $specification, array $data, string $monitoringDate, int $reportId): array
    {
        $module = $specification ? (self::SPECIFICATIONS[$specification] ?? null) : null;
        $pending = [
            'destination_module' => $module,
            'monitoring_status' => 'pending',
        ];
        if (! $specification || ! $module) return $pending;
        $sourceData = ['monitoring_report_id' => $reportId, 'monitoring_detail_id' => $detail->id, 'monitoring_specification' => $specification, 'monitoring_fields' => $data];

        if ($specification === 'dry_off') {
            if (($data['dry_off_confirmed'] ?? null) !== 'Yes' || empty($data['dry_off_date'])) return $pending;
            $data['service_type'] = 'Dry-off Confirmed';
            DB::table('monitoring_report_details')->where('id', $detail->id)->update([
                'monitoring_data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'updated_at' => now(),
            ]);
            $lastCalving = DB::table('calves')->where('dam_id', $animal->id)->whereDate('birth_date', '<=', $data['dry_off_date'])
                ->where(fn ($query) => $query->whereNull('birth_outcome')->orWhere('birth_outcome', '<>', 'Abortion'))->max('birth_date');
            $recordedCalving = DB::table('mating_records')->where('carabao_id', $animal->id)->whereNotNull('actual_calving_date')
                ->whereDate('actual_calving_date', '<=', $data['dry_off_date'])->max('actual_calving_date');
            if ($recordedCalving && (! $lastCalving || $recordedCalving > $lastCalving)) $lastCalving = $recordedCalving;
            if (Schema::hasTable('fhpr_records') && Schema::hasColumn('fhpr_records', 'present_calving_date')) {
                $fhprCalving = DB::table('fhpr_records')->where('carabao_id', $animal->id)->whereNotNull('present_calving_date')
                    ->whereDate('present_calving_date', '<=', $data['dry_off_date'])->max('present_calving_date');
                if ($fhprCalving && (! $lastCalving || $fhprCalving > $lastCalving)) $lastCalving = $fhprCalving;
            }
            $daysInMilk = $lastCalving
                ? (int) max(0, \Carbon\Carbon::parse($lastCalving)->startOfDay()->diffInDays(\Carbon\Carbon::parse($data['dry_off_date'])->startOfDay(), false))
                : null;
            $fields = [
                'carabao_id' => $animal->id,
                'test_date' => $monitoringDate,
                'dryoff_date' => $data['dry_off_date'],
                'days_in_milk' => $daysInMilk,
                'registry_number' => $animal->registry_number,
                'remarks' => trim(implode(' · ', array_filter([$data['findings'] ?? null, $data['remarks'] ?? $detail->remarks]))) ?: null,
                'source_data' => json_encode($sourceData + ['dry_off_confirmed' => true], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ];
            $existingId = $detail->destination_module === 'MTD' ? $detail->destination_record_id : null;
            if ($existingId && DB::table('mtd_records')->where('id', $existingId)->exists()) {
                DB::table('mtd_records')->where('id', $existingId)->update($fields);
                $recordId = (int) $existingId;
            } else {
                $recordId = (int) DB::table('mtd_records')->insertGetId($fields + ['created_at' => now()]);
            }
            return ['destination_module' => 'MTD', 'destination_record_id' => $recordId, 'destination_inventory_id' => null, 'monitoring_status' => 'completed'];
        }

        if ($specification === 'calf_monitoring') {
            $birthDate = $data['date_of_birth'] ?? null;
            $outcome = $data['birth_outcome'] ?? null;
            $sex = $data['sex'] ?? null;
            if (! $birthDate || ! $outcome || ($outcome === 'Live Birth' && ! $sex)) return $pending;
            $calfId = trim((string) ($data['calf_id'] ?? ''));
            $matchingCalf = null;
            if ($detail->destination_module === $module && $detail->destination_record_id) {
                $matchingCalf = DB::table('calves')->where('id', $detail->destination_record_id)->first();
            }
            if (! $matchingCalf && $calfId === '') $matchingCalf = DB::table('calves')->where('dam_id', $animal->id)->where('birth_date', $birthDate)->where('sex', $sex)->first();
            if ($calfId === '' && $matchingCalf?->calf_animal_id) $calfId = (string) $matchingCalf->calf_animal_id;
            if ($calfId === '' && $outcome === 'Live Birth') {
                $existingChild = DB::table('carabaos')->where('dam_id', $animal->animal_id)->where('birth_date', $birthDate)->where('sex', $sex)->first();
                if ($existingChild) $calfId = (string) $existingChild->animal_id;
            }
            if ($calfId === '') $calfId = 'CALF-'.date('Y', strtotime($birthDate)).'-'.strtoupper(Str::random(8));
            if (($data['calf_id'] ?? '') !== $calfId) {
                $data['calf_id'] = $calfId;
                DB::table('monitoring_report_details')->where('id', $detail->id)->update([
                    'monitoring_data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'updated_at' => now(),
                ]);
            }
            $calfStatus = $data['calf_status'] ?? match ($outcome) {'Stillbirth' => 'Dead', 'Abortion' => 'Not Applicable', default => 'Live'};
            $calfFields = [
                'calf_animal_id' => $calfId, 'sire_id' => $data['sire_id'] ?? null, 'birth_date' => $birthDate, 'sex' => $sex,
                'birth_weight' => $data['birth_weight'] ?? null, 'monitoring_date' => $monitoringDate, 'birth_outcome' => $outcome,
                'animal_status' => $calfStatus, 'calf_findings' => $data['findings'] ?? null,
                'remarks' => $data['remarks'] ?? $detail->remarks, 'updated_at' => now(),
            ];
            $calf = $matchingCalf;
            if (! $calf) $calf = DB::table('calves')->where('calf_animal_id', $calfId)->first();
            if (! $calf) $calf = DB::table('calves')->where('dam_id', $animal->id)->where('birth_date', $birthDate)->where('sex', $sex)->first();
            if ($calf) {
                DB::table('calves')->where('id', $calf->id)->update($calfFields);
                $calfRecordId = (int) $calf->id;
            } else {
                $calfRecordId = DB::table('calves')->insertGetId($calfFields + ['dam_id' => $animal->id, 'created_at' => now()]);
            }

            $inventoryId = $detail->destination_inventory_id ? (int) $detail->destination_inventory_id : null;
            if ($outcome === 'Live Birth') {
                $child = DB::table('carabaos')->where('animal_id', $calfId)->first();
                if (! $child) {
                    $inventoryId = DB::table('carabaos')->insertGetId([
                        'animal_id' => $calfId, 'farmer_id' => $animal->farmer_id,
                        'cooperative_id' => $animal->cooperative_id ?: $animal->farmer_cooperative_id,
                        'animal_name' => $data['animal_name'] ?? null, 'sex' => $sex,
                        'breed' => $data['breed'] ?? $animal->breed, 'classification' => $sex === 'Male' ? 'Male Calf' : 'Female Calf',
                        'birth_date' => $birthDate, 'status' => $calfStatus, 'sire_id' => $data['sire_id'] ?? null,
                        'dam_id' => $animal->animal_id, 'dam_name' => $animal->animal_name,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                } else {
                    abort_unless((string) $child->dam_id === (string) $animal->animal_id, 422, 'Calf ID is already assigned to another dam.');
                    $inventoryId = (int) $child->id;
                    DB::table('carabaos')->where('id', $inventoryId)->update([
                        'sex' => $sex, 'breed' => $data['breed'] ?? $animal->breed, 'birth_date' => $birthDate,
                        'status' => $calfStatus, 'sire_id' => $data['sire_id'] ?? null, 'updated_at' => now(),
                    ]);
                }
            }
            return ['destination_module' => $module, 'destination_record_id' => $calfRecordId, 'destination_inventory_id' => $inventoryId, 'monitoring_status' => 'completed'];
        }

        if ($specification === 'breeding') {
            $breedingDate = $data['breeding_date'] ?? null;
            $method = $data['method'] ?? null;
            if (! $breedingDate || ! $method) return $pending;
            $bull = ! empty($data['semen_bull_id']) ? DB::table('semen_bulls')->where('id', $data['semen_bull_id'])->first() : null;
            $recordDate = $data['record_date'] ?? $monitoringDate;
            $sourceData['record_date'] = $recordDate;
            $sourceData['monitoring_date'] = $recordDate;
            $sourceData['estrus_type'] = $data['estrus_type'] ?? null;
            $sourceData['ai_technician'] = $data['technician'] ?? null;
            $sourceData['pregnancy_status'] = $data['pregnancy_status'] ?? null;
            $recordFields = [
                'carabao_id' => $animal->id, 'cooperative_id' => $animal->cooperative_id ?: $animal->farmer_cooperative_id,
                'registry_number' => $animal->registry_number, 'mating_date' => $breedingDate,
                'mating_type' => $method === 'AI' ? 'AI' : 'NM', 'bull_id' => $bull?->bull_id ?: ($data['bull_id'] ?? null),
                'semen_bull_id' => $bull?->id, 'technician' => $method === 'AI' ? ($data['technician'] ?? null) : null,
                'pregnancy_check_date' => $data['pd_date'] ?? null,
                'pregnancy_result' => match ($data['pregnancy_status'] ?? null) {'Pregnant' => 'Positive', 'Not Pregnant' => 'Negative', 'Inconclusive' => 'Inconclusive', default => null},
                'expected_calving_date' => $data['expected_calving'] ?? null,
                'remarks' => trim(implode(' · ', array_filter([$data['natural_mating_details'] ?? null, $data['findings'] ?? null, $data['remarks'] ?? $detail->remarks]))) ?: null,
                'source_data' => json_encode($sourceData + ['semen_used' => $data['semen_used'] ?? null, 'pregnancy_months' => $data['pregnancy_months'] ?? null], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ];
            $existingId = $detail->destination_module === $module ? $detail->destination_record_id : null;
            if ($existingId && DB::table('mating_records')->where('id', $existingId)->exists()) {
                DB::table('mating_records')->where('id', $existingId)->update($recordFields);
                $recordId = (int) $existingId;
            } else {
                $recordId = DB::table('mating_records')->insertGetId($recordFields + ['created_at' => now()]);
            }
            return ['destination_module' => $module, 'destination_record_id' => $recordId, 'destination_inventory_id' => null, 'monitoring_status' => 'completed'];
        }

        if ($specification === 'health_nutrition') {
            $category = 'health_nutrition';
            $services = $this->selectedHealthServices($data['services'] ?? []);
            $serviceSummary = collect($services)->map(function ($service) {
                $name = $service['type'] === 'Other Services' ? $service['other_service_name'] : $service['type'];
                $dosage = $service['dosage_ml'] !== null ? $service['dosage_ml'].' mL' : null;
                return trim(implode(' — ', array_filter([$name, $dosage, $service['remarks']])));
            })->values()->all();
            $healthStatus = trim((string) ($data['health_status'] ?? '')) ?: ($services ? 'Services Provided' : null);
            if (empty($healthStatus) && $services === []) return $pending;
            $findings = trim(implode(' · ', array_filter([
                $data['symptoms'] ?? null, $data['findings'] ?? null, $data['body_condition'] ?? null,
            ])));
            $recordFields = [
                'carabao_id' => $animal->id, 'cooperative_id' => $animal->cooperative_id ?: $animal->farmer_cooperative_id,
                'registry_number' => $animal->registry_number, 'monitoring_date' => $monitoringDate,
                'health_status' => $healthStatus, 'findings' => $findings ?: null,
                'treatment' => trim(implode(' · ', array_filter([$data['treatment'] ?? null, $data['medication'] ?? null]))) ?: null,
                'follow_up_date' => $data['follow_up_date'] ?? null, 'record_category' => $category,
                'remarks' => $data['remarks'] ?? $detail->remarks,
                'source_data' => json_encode($sourceData + [
                    'record_category' => $category, 'recommendations' => $data['recommendations'] ?? null,
                'services' => $services, 'services_display' => $serviceSummary,
                'health_activity' => ['no_of_visits_monitored' => ($data['no_of_visits_monitored'] ?? '') !== '' ? (int) $data['no_of_visits_monitored'] : null, 'no_of_treatments' => ($data['no_of_treatments'] ?? '') !== '' ? (int) $data['no_of_treatments'] : null],
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ];
            $existingId = $detail->destination_module === $module ? $detail->destination_record_id : null;
            if ($existingId && DB::table('health_records')->where('id', $existingId)->exists()) {
                DB::table('health_records')->where('id', $existingId)->update($recordFields);
                $recordId = (int) $existingId;
            } else {
                $recordId = DB::table('health_records')->insertGetId($recordFields + ['created_at' => now()]);
            }
            return ['destination_module' => $module, 'destination_record_id' => $recordId, 'destination_inventory_id' => null, 'monitoring_status' => 'completed'];
        }

        return $pending;
    }
}
