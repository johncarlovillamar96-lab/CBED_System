<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class TransactionActivity
{
    public static function setCaptureKey(Connection $db, ?string $key): void
    {
        if ($db->getDriverName() === 'pgsql') {
            $db->selectFromWriteConnection("SELECT set_config('cbed.activity_key', ?, false)", [$key ?? '']);
        } else {
            $db->statement('SET @cbed_activity_key = ?', [$key]);
        }
    }

    public function publish(Connection $db, string $captureKey, User $actor, string $route, ?string $offlineKey = null, array $importTotals = []): void
    {
        // A caller may use a stable offline submission ID after its write transaction commits.
        $operationKey = $offlineKey === null ? $captureKey : hash('sha256', 'offline:'.$actor->id.':'.$offlineKey);
        $db->transaction(function () use ($db, $captureKey, $operationKey, $actor, $route, $offlineKey, $importTotals) {
            $pending = $db->table('activity_row_changes')->where('operation_key', $captureKey);
            $events = $pending->orderBy('id')->lockForUpdate()->get();
            if ($events->isEmpty() && $importTotals === []) {
                return;
            }

            $records = $events->groupBy(fn ($event) => $event->source_table.':'.$event->record_id)
                ->map(function (Collection $events) {
                    $first = $events->first();
                    $last = $events->last();
                    return [
                        'table' => $first->source_table,
                        'id' => (int) $first->record_id,
                        'before' => json_decode($first->previous ?? 'null', true),
                        'after' => json_decode($last->current ?? 'null', true),
                        'private_changes' => $events->flatMap(fn ($event) => array_keys(array_filter(
                            json_decode($event->private_changes ?? '{}', true)
                        )))->unique()->values()->all(),
                    ];
                })->values();
            $changed = $records->filter(fn ($record) => $record['before'] !== $record['after'] || $record['private_changes'] !== [])->values();
            $reportRoute = str_starts_with($route, 'ui.monitoring.reports.') || $offlineKey !== null;
            if (($changed->isEmpty() && $importTotals === []) || ($reportRoute && ! $records->contains('table', 'monitoring_reports'))) {
                $pending->delete();
                return;
            }
            $summary = $this->summarize($db, $records, $changed, $route, $reportRoute, $offlineKey !== null, $importTotals);
            if ($summary !== null) {
                $entry = $summary + [
                    'operation_key' => $operationKey,
                    'actor_id' => $actor->id,
                    'actor_name' => $actor->name,
                    'actor_first_name' => $actor->first_name,
                    'actor_last_name' => $actor->last_name,
                    'actor_role' => $actor->role,
                    'route' => $route,
                    'status' => 200,
                    'occurred_at' => now(),
                ];
                if ($offlineKey === null) {
                    $db->table('activity_logs')->insert($entry);
                } else {
                    $db->table('activity_logs')->upsert([$entry], ['operation_key'], ['operation_key']);
                }
            }
            $pending->delete();
        });
    }

    private function summarize(Connection $db, Collection $records, Collection $changed, string $route, bool $report, bool $offline, array $importTotals): ?array
    {
        $import = str_contains($route, 'import') && !str_contains($route, 'clear-history')
            && !str_ends_with($route, '.remove') && !str_ends_with($route, '.cancel');
        $sheets = $changed->where('table', 'cooperative_workbook_import_sheets')->whereNotNull('after');
        $business = $changed->reject(fn ($row) => str_starts_with($row['table'], 'cooperative_workbook_import'));
        if ($business->isEmpty() && $sheets->isEmpty() && $importTotals === []) {
            return null;
        }

        $primary = $report ? 'monitoring_reports' : $this->primaryTable($route, $business);
        $main = $report ? $records->where('table', $primary)->values() : $business->where('table', $primary)->values();
        $action = $offline ? 'Synchronize' : ($import ? 'Import' : match (true) {
            $main->every(fn ($row) => $row['before'] === null) => 'Create',
            $main->every(fn ($row) => $row['after'] === null) => 'Delete',
            default => 'Update',
        });
        $module = $import ? 'Workbook Import' : (ActivityFields::TABLES[$primary][0] ?? 'Application');
        if ($primary === 'cooperative_product_records' && ! $import) {
            $module = Str::headline(($main->first()['after'] ?? $main->first()['before'])['product_type']).' Production';
        }

        $reportAnimals = $report ? $records->where('table', 'monitoring_report_details')
            ->pluck('after')->filter()->where('availability_status', 'available')
            ->map(fn ($row) => (object) $row) : collect();
        $animalKeys = $records->flatMap(function ($record) {
            $row = $record['after'] ?? $record['before'];
            return [$row['carabao_id'] ?? null, $row['dam_id'] ?? null,
                $record['table'] === 'carabaos' ? $record['id'] : null];
        })->merge($reportAnimals->pluck('carabao_id'))->filter()->unique();
        $animals = $db->table('carabaos')->whereIn('id', $animalKeys)->get(['id', 'animal_id', 'registry_number', 'farmer_id', 'cooperative_id'])->keyBy('id');
        foreach ($records->where('table', 'carabaos') as $record) {
            if (! $animals->has($record['id'])) {
                $animals->put($record['id'], (object) ($record['after'] ?? $record['before']));
            }
        }
        $farmers = $db->table('farmers')->whereIn('id', $animals->pluck('farmer_id')->filter()->unique())
            ->get(['id', 'cooperative_id', 'first_name', 'middle_name', 'last_name'])->keyBy('id');
        $calfBreeds = $db->table('carabaos')->whereIn('animal_id', $records->where('table', 'calves')->map(
            fn ($record) => ($record['after'] ?? $record['before'])['calf_animal_id']
        ))->pluck('breed', 'animal_id');
        foreach ($records->where('table', 'carabaos')->whereNull('after') as $record) {
            $calfBreeds->put($record['before']['animal_id'], $record['before']['breed']);
        }

        $animalIds = [];
        $animalAliases = [];
        $farmerIds = [];
        $coopIds = [];
        $dates = [];
        $services = [];
        $serviceAnimals = [];
        $serviceFacts = [];
        $diagnoses = 0;
        $rows = [];
        foreach ($business as $record) {
            $table = $record['table'];
            $row = $record['after'] ?? $record['before'];
            $animal = $animals->get($row['carabao_id'] ?? $row['dam_id'] ?? ($table === 'carabaos' ? $record['id'] : 0));
            $farmer = $farmers->get($animal?->farmer_id);
            $coopIds[] = $table === 'cooperatives' ? $record['id'] : ($row['cooperative_id'] ?? $animal?->cooperative_id ?? $farmer?->cooperative_id);
            $identifier = $animal?->animal_id ?: (($row['registry_number'] ?? null) ?: $animal?->registry_number);
            $ids = array_values(array_unique(array_filter([$identifier, $row['calf_animal_id'] ?? null])));
            $rowDate = $table === 'carabaos' ? null : $this->recordDate($row, $route);
            if (! $report && ($import || $primary === $table)) {
                $dates[] = $rowDate;
                array_push($animalIds, ...$ids);
            }
            if ($report || $import || $primary === $table) {
                array_push($animalAliases, ...$ids, ...array_filter([
                    $animal?->registry_number,
                    $row['registry_number'] ?? null,
                    $record['before']['animal_id'] ?? null,
                    $record['before']['registry_number'] ?? null,
                    $record['before']['calf_animal_id'] ?? null,
                ]));
            }
            $record['date'] = $rowDate;
            $record['animal_ids'] = $ids;
            if ($table === 'carabaos' && $farmer) {
                $record['owner'] = $this->farmerName((array) $farmer);
            }
            if ($table === 'calves') {
                $record['dam_identifier'] = $identifier;
                $record['breed'] = $calfBreeds->get($row['calf_animal_id']);
            }
            $rows[] = $record;
            if ($record['after'] !== null && ($report || $import || $primary === $table || $table === 'bcs_records')) {
                $recordServices = $this->services($record, $route);
                $diagnoses += $recordServices['Pregnancy Diagnosis'] ?? 0;
                foreach ($recordServices as $service => $count) {
                    $services[$service] = ($services[$service] ?? 0) + $count;
                    $outcome = $table === 'mating_records' && isset($recordServices['Pregnancy Diagnosis'])
                        && ! in_array($service, ['Pregnancy Diagnosis', $this->matingMethod($row['mating_type'])], true);
                    $factKey = $table.':'.$record['id'].':'.($outcome ? 'pregnancy_outcome' : $service);
                    $serviceFacts[$factKey] = ['service' => $service, 'count' => $count, 'outcome' => $outcome];
                    $serviceAnimal = $table === 'calves' ? $row['calf_animal_id'] : $identifier;
                    if ($serviceAnimal) {
                        $serviceAnimals[$service][mb_strtolower($serviceAnimal)] = true;
                    }
                }
            }
        }
        if ($report) {
            foreach ($reportAnimals as $row) {
                $animal = $animals->get($row->carabao_id);
                $animalIds[] = $animal?->animal_id ?: ($row->animal_database_id ?: ($row->registry_number ?: $animal?->registry_number));
                $animalAliases[] = $row->registry_number ?: $animal?->registry_number;
                $farmerIds[] = $animal?->farmer_id;
            }
            $dates = $main->map(fn ($row) => ($row['after'] ?? $row['before'])['report_date'])->all();
        }
        $importIds = $records->where('table', 'cooperative_workbook_imports')->pluck('id')
            ->merge($sheets->pluck('after.workbook_import_id'))->filter()->unique();
        $importCooperatives = $db->table('cooperative_workbook_imports')->whereIn('id', $importIds)->pluck('cooperative_id')->all();
        $coopIds = array_values(array_unique(array_map('intval', array_filter(array_merge($coopIds, $importCooperatives,
            [$importTotals['cooperative_id'] ?? null])))));
        $cooperatives = $db->table('cooperatives')->whereIn('id', $coopIds)->pluck('cooperative_name', 'id')->all();
        foreach ($records->whereIn('table', ['cooperatives', 'monitoring_reports']) as $record) {
            $row = $record['after'] ?? $record['before'];
            $cooperatives[$row['cooperative_id'] ?? $record['id']] ??= $row['cooperative_name'];
        }
        $animalIds = collect($animalIds)->filter()->unique(fn ($id) => mb_strtolower($id))->values()->all();
        $dates = array_values(array_unique(array_filter($dates)));
        if ($dates === []) {
            $dates[] = now(ActivityLog::TIMEZONE)->toDateString();
        }
        sort($dates);
        $importCounts = $sheets->map(fn ($row) => $row['after'])->values();
        $sameSheet = fn ($column) => $importCounts->sum(fn ($row) => max(0, (int) ($row[$column] ?? 0)));
        $counts = array_replace([
            'successful' => $sheets->isEmpty() ? $business->whereNotNull('after')->count() : $sameSheet('rows_imported'),
            'skipped' => $importCounts->sum(fn ($sheet) => max(0,
                (int) $sheet['rows_skipped'] - (int) $sheet['rows_rejected']
                    - ($sheet['target_module'] === 'Carabao Inventory' ? (int) $sheet['rows_unmapped'] : 0)
            )),
            'rejected' => $sameSheet('rows_rejected'),
            'unmapped' => $sameSheet('rows_unmapped'),
            'failed_sheets' => $importCounts->where('status', 'failed')->count(),
        ], array_intersect_key($importTotals, array_flip(['successful', 'skipped', 'rejected', 'unmapped', 'failed_sheets'])));
        $details = [
            'records' => $rows,
            'animal_ids' => $animalIds,
            'animal_ids_normalized' => array_values(array_unique(array_map('mb_strtolower', array_filter(array_merge($animalAliases, $animalIds))))),
            'cooperative_ids' => $coopIds,
            'cooperatives' => array_values($cooperatives),
            'transaction_dates' => $dates,
            'unique_animals' => count($animalIds),
            'farmers_monitored' => count(array_unique(array_filter($farmerIds))),
            'services' => $services,
            'service_animal_counts' => array_map('count', $serviceAnimals),
            'services_conducted' => array_sum($services) - $diagnoses,
            'service_facts' => $serviceFacts,
            'records_created' => $business->whereNull('before')->count(),
            'records_updated' => $business->whereNotNull('before')->whereNotNull('after')->count(),
        ];
        if ($report) {
            $details['monitoring_report_ids'] = $main->pluck('id')->all();
            if ($offline) {
                $details['synchronized_at'] = now()->toIso8601String();
            }
        }
        if ($import) {
            $details['import'] = $counts;
            $details['sheets'] = $importCounts->all();
        }
        $description = $this->description($action, $module, $primary, $main, $details, $rows, $route);
        return [
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'transaction_date' => count($dates) === 1 ? $dates[0] : null,
            'details' => json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ];
    }

    private function primaryTable(string $route, Collection $records): ?string
    {
        return match (true) {
            str_contains($route, 'calf-drop') && $records->contains('table', 'calves') => 'calves',
            str_contains($route, 'breeding-api') => 'mating_records',
            str_contains($route, 'health-records') => 'health_records',
            str_starts_with($route, 'mtd.') => 'mtd_records',
            str_starts_with($route, 'exits.') => 'nature_of_exits',
            str_starts_with($route, 'milk.') => 'milk_records',
            str_starts_with($route, 'fhpr.') => 'fhpr_records',
            default => $records->groupBy('table')->sortByDesc(fn ($rows) => $rows->count())->keys()->first(),
        };
    }

    private function recordDate(array $row, string $route): ?string
    {
        if (str_ends_with($route, '.pd')) {
            return $row['pregnancy_check_date'] ?? null;
        }

        return $row['report_date'] ?? $row['monitoring_date'] ?? $row['record_date'] ?? $row['mating_date'] ?? $row['pregnancy_check_date']
            ?? $row['test_date'] ?? $row['exit_date'] ?? $row['actual_date'] ?? $row['birth_date']
            ?? $row['dry_off_date'] ?? $row['acquisition_date'] ?? $row['used_date'] ?? null;
    }

    private function services(array $record, string $route): array
    {
        $row = $record['after'];
        $before = $record['before'] ?? [];
        return match ($record['table']) {
            'calves' => ['Calf Drop' => 1],
            'bcs_records' => ['BCS' => 1],
            'health_records' => $this->healthServices($row, $before),
            'mating_records' => $this->breedingServices($row, $before, $route),
            'mtd_records' => ['Milk Test' => 1],
            'nature_of_exits' => ['Nature of Exit' => 1],
            default => [],
        };
    }

    private function healthServices(array $row, array $before): array
    {
        $names = function (array $record): array {
            $types = $this->listValue($record['service_types'] ?? null);
            $other = $this->listValue($record['other_service_names'] ?? null);
            $labels = [];
            foreach ($types as $index => $type) {
                if (! is_string($type)) {
                    continue;
                }
                $name = $type === 'Other Services' && filled($other[$index] ?? null) ? $other[$index] : $type;
                $labels[] = match (preg_replace('/[^a-z0-9]/', '', strtolower($name))) {
                    'vitaminsmineralsupplements', 'vitaminsmineralssupplementation', 'vitaminsupplementation' => 'Vitamin Supplementation',
                    'bolusapplication' => 'Bolus',
                    'foragedemo', 'foragedemonstration' => 'Forage Demonstration',
                    'handsonmilking' => 'Hands-on Milking',
                    default => trim($name),
                };
            }
            if ($labels === []) {
                $labels = HealthWorksheetMapping::selectedServices($record['worksheet_services'] ?? []);
            }
            return array_count_values(array_filter($labels));
        };

        $services = $names($row);
        if (($row['monitoring_date'] ?? null) === ($before['monitoring_date'] ?? null)) {
            foreach ($names($before) as $label => $count) {
                if (isset($services[$label])) {
                    $services[$label] = max(0, $services[$label] - $count);
                }
            }
        }
        return array_filter($services);
    }

    private function listValue(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        return is_array($value) ? $value : [];
    }

    private function breedingServices(array $row, array $before, string $route): array
    {
        $services = [];
        if (! str_ends_with($route, '.pd') && $row['mating_date'] && ($row['mating_date'] !== ($before['mating_date'] ?? null)
            || $row['mating_type'] !== ($before['mating_type'] ?? null) || $row['bull_id'] !== ($before['bull_id'] ?? null))) {
            $method = $this->matingMethod($row['mating_type']);
            $services[$method] = 1;
        }
        $result = $row['pregnancy_result'];
        if ($result && ! in_array(strtolower($result), ['pending', 'not yet checked'], true)
            && ($result !== ($before['pregnancy_result'] ?? null) || $row['pregnancy_check_date'] !== ($before['pregnancy_check_date'] ?? null))) {
            $services['Pregnancy Diagnosis'] = 1;
            $label = match (strtolower($result)) {
                'positive', 'pregnant' => 'Pregnant',
                'negative', 'not pregnant', 'non-pregnant' => 'Non-Pregnant',
                default => $result,
            };
            $services[$label] = 1;
        }
        return $services;
    }

    private function matingMethod(?string $type): string
    {
        return match (strtolower((string) $type)) {
            'ai', 'artificial insemination' => 'Artificial Insemination',
            'nm', 'natural mating' => 'Natural Mating',
            default => $type ?: 'Mating',
        };
    }

    private function farmerName(array $row): string
    {
        return implode(' ', array_filter([$row['first_name'], $row['middle_name'], $row['last_name']]));
    }

    private function description(string $action, string $module, ?string $table, Collection $main, array $details, array $rows, string $route): string
    {
        $coop = count($details['cooperatives']) === 1 ? ' under '.$details['cooperatives'][0] : '';
        $count = $main->count();
        $animals = $details['unique_animals'];
        $animalCount = $animals.' '.Str::plural('animal', $animals);

        if ($action === 'Import') {
            $counts = collect($rows)->whereNotNull('after')->countBy('table')->map(
                fn ($total, $table) => $total.' '.Str::lower(ActivityFields::TABLES[$table][0]).' records'
            )->implode(', ');
            $import = $details['import'];
            $result = $counts !== '' ? 'Imported '.$counts : ($import['failed_sheets'] ? 'Workbook import failed' : 'Import processed');
            return $result.$coop
                .". Successful source rows: {$import['successful']}; skipped: {$import['skipped']}; rejected: {$import['rejected']}; unmapped: {$import['unmapped']}; failed sheets: {$import['failed_sheets']}.";
        }
        if ($module === 'Animal Monitoring' && $table === 'monitoring_reports') {
            $verb = match ($action) { 'Synchronize' => 'Synchronized', 'Update' => 'Updated', default => 'Saved' };
            $date = count($details['transaction_dates']) === 1 ? ' on '.$details['transaction_dates'][0] : '';
            return "$verb $count ".Str::plural('Animal Monitoring Report', $count)." covering $animalCount$coop$date.";
        }

        $record = collect($rows)->firstWhere('table', $table);
        $row = $record['after'] ?? $record['before'] ?? [];
        $animal = $record['animal_ids'][0] ?? null;
        $label = match ($table) {
            'farmers' => 'farmer '.$this->farmerName($row),
            'carabaos' => 'animal '.($row['animal_id'] ?: $row['registry_number']),
            'cooperatives' => $row['cooperative_name'],
            'calves' => 'calf drop '.$row['calf_animal_id'],
            'users' => 'account '.$row['name'],
            'semen_bulls' => 'semen bull '.$row['bull_id'],
            default => $module.($animal ? " for animal $animal" : ' record #'.($record['id'] ?? '')),
        };
        if ($action === 'Delete') {
            return $count === 1 ? "Deleted $label$coop." : "Deleted $count $module records$coop.";
        }
        if ($action === 'Update' && $count === 1 && ! str_ends_with($route, '.pd')) {
            $changes = $this->changes($record);
            $text = collect($changes)->take(3)->map(fn ($values, $field) => Str::headline($field).' changed from '
                .$this->displayValue($values['previous']).' to '.$this->displayValue($values['current']))->implode(', ');
            if ($text === '' && $record['private_changes'] !== []) {
                $text = implode(', ', array_map(Str::headline(...), $record['private_changes'])).' changed';
            }
            return "Updated $label$coop".($text ? " — $text." : '.');
        }
        if ($table === 'mating_records') {
            $services = $details['services'];
            $method = $this->matingMethod($row['mating_type']);
            if (str_ends_with($route, '.pd') || ($services['Pregnancy Diagnosis'] ?? 0) === $count && !isset($services[$method])) {
                return "Recorded pregnancy diagnosis for $animalCount$coop — ".($services['Pregnant'] ?? 0).' Pregnant and '.($services['Non-Pregnant'] ?? 0).' Non-Pregnant.';
            }
            if ($count > 1 && ($services[$method] ?? 0) === $count) {
                return "Recorded $method for $animalCount$coop.";
            }
            return $count === 1 && $animal
                ? "Recorded $method for animal $animal".($row['bull_id'] ? " using Sire ID {$row['bull_id']}" : '')."$coop."
                : "Recorded $count breeding records for $animalCount$coop.";
        }
        if ($table === 'calves') {
            $traits = implode(', ', array_filter([$row['sex'], $record['breed']]));
            $dam = $record['dam_identifier'] ? ' from Dam ID '.$record['dam_identifier'] : '';
            return $count === 1
                ? "Recorded $label$dam".($traits ? " — $traits" : '')."$coop."
                : "Recorded $count calf drops$coop.";
        }
        if ($table === 'milk_records') {
            $milk = collect($main)->pluck('after')->filter();
            $kg = $milk->sum('total_liters');
            $cows = $animals ?: $milk->sum('number_of_cows');
            $traded = $milk->whereNotNull('milk_traded');
            $value = $traded->whereNotNull('price_per_kg')->sum(fn ($row) => $row['milk_traded'] * $row['price_per_kg']);
            $breakdown = $traded->isEmpty() ? '' : ', '.$traded->sum('milk_traded').' kg traded';
            $breakdown .= $value ? ', PHP '.number_format($value, 2).' milk value' : '';
            $date = count($details['transaction_dates']) === 1 ? ' on '.$details['transaction_dates'][0] : '';
            return "Recorded $kg kg of milk from $cows lactating ".Str::plural('cow', $cows)."$coop$date$breakdown.";
        }
        if ($table === 'nature_of_exits' && $count === 1) {
            return 'Recorded '.strtolower($row['nature_of_exit']).' for animal '.($animal ?? $row['registry_number']).$coop
                .($row['exit_date'] ? ' on '.$row['exit_date'] : '').'.';
        }
        if ($table === 'carabaos' && $count === 1) {
            $traits = implode(', ', array_filter([$row['sex'], $row['breed'], $row['ownership_status'] ?: $row['entrustment']]));
            $owner = isset($record['owner']) ? ', owned by '.$record['owner'] : '';
            return "Registered $label$coop$owner".($traits ? " — $traits" : '').'.';
        }
        if (in_array($table, ['farmers', 'cooperatives', 'carabaos'], true)) {
            return $count === 1 ? "Registered $label".($table === 'cooperatives' ? '' : $coop).'.' : 'Registered '.$count.' '.strtolower($module)."$coop.";
        }
        if (in_array($table, ['health_records', 'bcs_records', 'mtd_records'], true)) {
            $activity = match ($table) {
                'health_records' => 'Health and Nutrition services',
                'bcs_records' => 'Body Condition Scores',
                default => 'milk test results',
            };
            return "Recorded $activity for $animalCount$coop.";
        }
        return $count === 1 ? "Recorded $label$coop." : "Recorded $count $module records$coop.";
    }

    public function changes(array $record): array
    {
        $changes = [];
        if ($record['before'] !== null && $record['after'] !== null) {
            foreach ($record['after'] as $field => $value) {
                $previous = $record['before'][$field] ?? null;
                if ($previous !== $value) {
                    $changes[$field] = ['previous' => $previous, 'current' => $value];
                }
            }
        }
        return $changes;
    }

    public function displayValue(mixed $value): string
    {
        return is_array($value) ? implode(', ', array_filter($value, fn ($item) => $item !== null)) : (string) ($value ?? '—');
    }
}
