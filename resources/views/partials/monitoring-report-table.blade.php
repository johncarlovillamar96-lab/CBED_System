<table class="monitoring-report-grid w-full min-w-[1500px] border-collapse text-sm">
    <thead class="bg-slate-100 text-center text-xs font-semibold text-slate-700">
        <tr>
            <th rowspan="2" scope="col" class="border px-3 py-2">No.</th>
            <th rowspan="2" scope="col" class="border px-3 py-2">Farmer</th>
            <th rowspan="2" scope="col" class="border px-3 py-2">Animal ID</th>
            <th rowspan="2" scope="col" class="border px-3 py-2">BCS</th>
            <th colspan="3" scope="colgroup" class="border px-3 py-2">Calf Monitoring</th>
            <th colspan="4" scope="colgroup" class="border px-3 py-2">Breeding</th>
            <th rowspan="2" scope="col" class="border px-3 py-2">Health and Nutrition</th>
            <th colspan="3" scope="colgroup" class="border px-3 py-2">Other Services</th>
            <th rowspan="2" scope="col" class="border px-3 py-2">Monitoring Date</th>
            <th rowspan="2" scope="col" class="border px-3 py-2">Remarks</th>
        </tr>
        <tr>
            @foreach (['Last Calving', 'Sex', 'AI / NM', 'PD', 'Type of Estrus', 'AI / NM', 'Sire ID', 'Ear Tagging', 'Bolus', 'Flashing'] as $heading)
                <th scope="col" class="border px-3 py-2">{{ $heading }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody @if (isset($tbodyId)) id="{{ $tbodyId }}" @endif>
        @forelse ($reportRows as $index => $row)
            @php
                $calf = $row['report_calf'] ?? [];
                $monitoringDate = $row['monitoring_date'] ?? '';
                $breeding = $row['_visit_breeding'][$monitoringDate] ?? [];
                $visitPd = $row['_visit_pd'][$monitoringDate] ?? null;
                [$health, $other] = \App\Support\MonitoringReportTable::services($row);
            @endphp
            <tr class="align-top even:bg-slate-50">
                <td class="border px-3 py-2 text-center">{{ $firstRow + $index }}</td>
                <td class="border px-3 py-2">
                    <span class="block font-medium">{{ $row['farmer'] }}</span>
                    <small class="block text-xs text-slate-500">{{ $row['cooperative'] }}</small>
                </td>
                <td class="border px-3 py-2">
                    <span class="block font-medium">{{ $row['animal_id'] }}</span>
                    <small class="block text-xs text-slate-500">{{ $row['classification'] }}</small>
                </td>
                <td class="border px-3 py-2">
                    {{ $row['bcs'] ?? '–' }}
                    @include('partials.monitoring-report-date', ['recordDate' => $row['bcs_date'] ?? null])
                </td>
                <td class="border px-3 py-2">{{ empty($calf['date']) ? '–' : \Carbon\Carbon::parse($calf['date'])->format('M j, Y') }}</td>
                @foreach ([
                    [$calf['sex'] ?? null, $calf['date'] ?? null],
                    [\App\Support\MonitoringReportTable::breedingMethod($calf['method'] ?? null), $calf['date'] ?? null],
                    [$visitPd ?? (blank($breeding['pd_date'] ?? null) ? ($breeding['pd'] ?? null) : null), $visitPd === null ? null : $monitoringDate],
                    [$breeding['estrus'] ?? null, $breeding['date'] ?? null],
                    [\App\Support\MonitoringReportTable::breedingMethod($breeding['method'] ?? null), $breeding['date'] ?? null],
                    [$breeding['sire_id'] ?? null, $breeding['date'] ?? null],
                ] as [$value, $date])
                    <td class="border px-3 py-2">
                        {{ filled($value) ? $value : '–' }}
                        @if (filled($value))
                            @include('partials.monitoring-report-date', ['recordDate' => $date])
                        @endif
                    </td>
                @endforeach
                <td class="border px-3 py-2">
                    @forelse ($health as $entry)
                        <div class="mb-1 last:mb-0">
                            {{ $entry['value'] }}
                            @include('partials.monitoring-report-date', ['recordDate' => $entry['date']])
                        </div>
                    @empty
                        –
                    @endforelse
                </td>
                @foreach (['ear_tagging', 'bolus', 'flashing'] as $service)
                    <td class="border px-3 py-2">
                        @if (($other[$service]['value'] ?? null) === true)
                            <span role="img" aria-label="Done" class="font-bold text-emerald-700">&#10003;</span>
                        @else
                            {{ $other[$service]['value'] ?? '–' }}
                        @endif
                        @include('partials.monitoring-report-date', ['recordDate' => $other[$service]['date'] ?? null])
                    </td>
                @endforeach
                <td class="border px-3 py-2">
                    @if (!empty($row['monitoring_date']))
                        <time datetime="{{ $row['monitoring_date'] }}">{{ \Carbon\Carbon::parse($row['monitoring_date'])->format('F j, Y') }}</time>
                    @else
                        –
                    @endif
                </td>
                <td class="border px-3 py-2">
                    {{ $row['remarks'] ?: '–' }}
                    @include('partials.monitoring-report-date', ['recordDate' => $row['remarks_date'] ?? null])
                </td>
            </tr>
        @empty
            <tr><td colspan="17" class="border px-3 py-8 text-center text-slate-500">No live animals match these filters.</td></tr>
        @endforelse
    </tbody>
</table>
