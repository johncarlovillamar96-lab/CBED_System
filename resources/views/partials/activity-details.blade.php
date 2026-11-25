<dialog id="activity-{{ $entry->id }}" aria-labelledby="activity-title-{{ $entry->id }}" class="m-auto max-h-[85vh] w-[92vw] max-w-3xl overflow-y-auto rounded-xl bg-white p-6 text-left shadow-xl backdrop:bg-slate-900/60">
    <form method="dialog" class="float-right">
        <button class="rounded-lg border px-3 py-1 font-semibold" autofocus>Close</button>
    </form>
    <h2 id="activity-title-{{ $entry->id }}" class="mb-4 text-lg font-semibold">Activity #{{ $entry->id }}</h2>
    <p class="mb-4 whitespace-pre-line">{{ $entry->activityDescription() }}</p>
    <dl class="grid gap-2 sm:grid-cols-2">
        @foreach ([
            'User' => $entry->actor_name.' (#'.$entry->actor_id.')',
            'First Name' => $entry->actor_first_name ?: '—',
            'Last Name' => $entry->actor_last_name ?: '—',
            'Role' => \App\Models\User::ROLES[$entry->actor_role] ?? $entry->actor_role,
            'Logged at (Asia/Manila)' => $entry->localTime()->format('M j, Y g:i:s A'),
            'Transaction dates' => implode(', ', $details['transaction_dates'] ?? []),
            'Cooperative' => implode(', ', $details['cooperatives'] ?? []),
            'Module' => $entry->module ?? 'System',
            'Action' => $entry->action,
            'Unique animals' => $details['unique_animals'] ?? null,
            'Farmers monitored' => $entry->module === 'Animal Monitoring' ? ($details['farmers_monitored'] ?? null) : null,
            'Services conducted' => $details['services_conducted'] ?? null,
            'Animal IDs' => implode(', ', $details['animal_ids'] ?? []),
            'Monitoring report IDs' => implode(', ', $details['monitoring_report_ids'] ?? []),
            'Synchronized at (Asia/Manila)' => isset($details['synchronized_at']) ? $entry->localTime()->format('M j, Y g:i:s A') : null,
        ] as $label => $value)
            @if ($value !== null && $value !== '')
                <div class="rounded bg-slate-50 p-2"><dt class="font-semibold">{{ $label }}</dt><dd class="break-words">{{ $value }}</dd></div>
            @endif
        @endforeach
    </dl>
    @if (!$entry->details)
        <p class="mt-4">HTTP {{ $entry->status }} @if ($entry->validation_failed) · Validation failed @endif @if ($entry->target) · {{ $entry->target }} @endif</p>
    @endif
    @if (!empty($details['services']))
        <h3 class="mb-2 mt-5 font-semibold">Service breakdown</h3>
        <ul class="list-disc pl-5">
            @foreach ($details['services'] as $label => $count)
                <li>{{ $label }} · {{ $count }}@if(isset($details['service_animal_counts'][$label])) ({{ $details['service_animal_counts'][$label] }} unique animals)@endif</li>
            @endforeach
        </ul>
    @endif
    @if (!empty($details['sheets']))
        <h3 class="mb-2 mt-5 font-semibold">Workbook results</h3>
        <ul class="list-disc pl-5">
            @foreach ($details['sheets'] as $sheet)
                <li>{{ $sheet['target_module'] ?? 'Workbook' }} · {{ $sheet['status'] }} · {{ $sheet['rows_imported'] }} imported · {{ $sheet['rows_skipped'] }} skipped · {{ $sheet['rows_rejected'] }} rejected · {{ $sheet['rows_unmapped'] }} unmapped</li>
            @endforeach
        </ul>
    @endif
    @if (!empty($details['records']))
        @inject('activity', 'App\Support\TransactionActivity')
        <h3 class="mb-2 mt-5 font-semibold">Affected records</h3>
        @foreach ($details['records'] as $record)
            <details class="border-t border-slate-200 py-2">
                <summary class="cursor-pointer">{{ \App\Support\ActivityFields::TABLES[$record['table']][0] ?? $record['table'] }} #{{ $record['id'] }}
                    @if ($record['animal_ids']) · {{ implode(', ', $record['animal_ids']) }} @endif
                    @if ($record['date']) · {{ $record['date'] }} @endif
                </summary>
                @foreach (['dam_identifier' => 'Dam ID', 'breed' => 'Breed', 'owner' => 'Owner'] as $field => $label)
                    @if (!empty($record[$field]))
                        <p class="mt-1">{{ $label }} · {{ $record[$field] }}</p>
                    @endif
                @endforeach
                @if ($record['before'] !== null && $record['after'] !== null)
                    @php($changes = $activity->changes($record))
                    @if ($changes)
                    <table class="mt-2 w-full">
                        <thead><tr><th>Field</th><th>Previous</th><th>Updated</th></tr></thead>
                        <tbody>
                            @foreach ($changes as $field => $values)
                                <tr><td>{{ \Illuminate\Support\Str::headline($field) }}</td><td>{{ $activity->displayValue($values['previous']) }}</td><td>{{ $activity->displayValue($values['current']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                    @if (!empty($record['private_changes']))
                        <p class="mt-2">{{ implode(', ', array_map(\Illuminate\Support\Str::headline(...), $record['private_changes'])) }} changed.</p>
                    @endif
                @else
                    <dl class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach (array_filter($record['after'] ?? $record['before'], fn ($value) => $value !== null && $value !== '') as $field => $value)
                            <div><dt class="font-semibold">{{ \Illuminate\Support\Str::headline($field) }}</dt><dd>{{ $activity->displayValue($value) }}</dd></div>
                        @endforeach
                    </dl>
                @endif
            </details>
        @endforeach
    @endif
</dialog>
