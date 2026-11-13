@extends('layouts.admin')

@section('title', 'Monitoring | CBED')
@section('page-heading', 'Monitoring')

@section('content')
<div class="inventory-page monitoring-page mating-dashboard min-w-0 bg-slate-50 text-slate-800">
    <div class="pcc-mating-hero mb-7 flex flex-col gap-5 rounded-2xl p-5 text-white shadow-xl sm:p-6 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-center gap-4">
            <div class="pcc-brand-mark hidden sm:flex" aria-hidden="true"><i class="fa-solid fa-clipboard-check"></i><span><i class="fa-solid fa-cow"></i></span></div>
            <div>
                <div class="mb-2 flex flex-wrap items-center gap-2 text-sm text-blue-100"><i class="fa-solid fa-house"></i><span>/</span><span>CBED Data System</span><span>/</span><span>Animal Management</span><span>/</span><b class="text-white">Monitoring</b></div>
                <h1 class="text-2xl font-bold text-white sm:text-3xl">Animal Monitoring</h1>
                <p class="mt-1 text-sm text-blue-100">Review animal monitoring records by cooperative, farmer, registry ID, and visit date.</p>
                <span class="pcc-program-tag"><i class="fa-solid fa-seedling"></i> Animal Management · Monitoring</span>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @if(!$cooperatives->isEmpty())<button type="button" onclick="openMonitoringModal()" class="action-primary"><i class="fa-solid fa-plus"></i>Add Monitoring Record</button>@endif
            <a href="{{ route('ui.monitoring') }}" class="action-secondary"><i class="fa-solid fa-rotate"></i>Refresh</a>
        </div>
    </div>

    <section class="mb-6 flex flex-col justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 sm:flex-row sm:items-center">
        <div class="flex gap-3"><i class="fa-solid fa-circle-check mt-0.5"></i><div><b>Monitoring database is ready</b><p class="mt-1">Reports save in MySQL. Select a cooperative to load its registered animals from Carabao Inventory.</p></div></div>
        @if($cooperatives->isEmpty())
            <span class="rounded-lg bg-amber-100 p-3 text-amber-900">Add a cooperative before creating a monitoring report.</span>
        @endif
    </section>

    <div id="monitoring-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/60 p-2 sm:p-3" role="dialog" aria-modal="true" aria-labelledby="monitoring-modal-title">
        <form method="POST" action="{{ route('ui.monitoring.reports.store') }}" id="monitoring-form" class="inventory-modal-panel inventory-panel flex max-h-[96vh] w-full max-w-[99vw] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            @csrf
            <div class="inventory-modal-header flex items-start justify-between gap-4 border-b p-5"><div><h2 id="monitoring-modal-title" class="text-xl font-bold text-white">Add Monitoring Record</h2><p class="mt-1 text-sm text-blue-100">Animal identification is loaded from Carabao Inventory.</p></div><button type="button" onclick="closeMonitoringModal()" class="rounded-lg p-2 text-white hover:bg-white/10" aria-label="Close"><i class="fa-solid fa-xmark"></i></button></div>
            <div class="grid gap-3 border-b bg-slate-50 p-5 sm:grid-cols-2">
                <label class="text-sm font-semibold">Monitoring Date<input type="date" name="monitoring_date" required value="{{ now()->toDateString() }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-normal"></label>
                <label class="text-sm font-semibold">Cooperative<select id="monitoring-cooperative" name="cooperative_id" required class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-normal"><option value="">Select a cooperative</option>@foreach($cooperatives as $cooperative)<option value="{{ $cooperative->id }}">{{ $cooperative->cooperative_name }}</option>@endforeach</select></label>
            </div>
            <div id="monitoring-counters" class="grid grid-cols-2 gap-2 border-b p-4 sm:grid-cols-5"><div class="rounded-lg bg-slate-100 p-2 text-center text-xs">Total <b id="count-total">0</b></div><div class="rounded-lg bg-emerald-50 p-2 text-center text-xs">Available <b id="count-available">0</b></div><div class="rounded-lg bg-slate-200 p-2 text-center text-xs">Unavailable <b id="count-unavailable">0</b></div><div class="rounded-lg bg-blue-50 p-2 text-center text-xs">Monitored <b id="count-monitored">0</b></div><div class="rounded-lg bg-amber-50 p-2 text-center text-xs">Pending <b id="count-pending">0</b></div></div>
            <div class="flex flex-wrap gap-2 border-b p-4"><input id="monitoring-search" type="search" placeholder="Search bolus, registry, tag or animal name" class="min-w-56 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm"><select id="filter-availability" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All availability</option><option value="available">Available</option><option value="unavailable">Unavailable</option></select><select id="filter-specification" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All specifications</option><option value="calf_monitoring">Calf Monitoring</option><option value="breeding">Breeding</option><option value="health_nutrition">Health &amp; Nutrition</option><option value="other_services">Other Services</option></select><select id="monitoring-page-size" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option>10</option><option selected>25</option><option>50</option><option>100</option></select></div>
            <div id="monitoring-loading" class="hidden p-8 text-center text-sm text-slate-500"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Loading cooperative animals…</div><div id="monitoring-empty" class="hidden p-8 text-center text-sm text-slate-500">Select a cooperative to load animals.</div><div id="monitoring-error" class="hidden p-4 text-sm text-rose-700"></div>
            <div class="inventory-table-scroll min-h-0 flex-1 overflow-auto"><table class="inventory-data-table w-full min-w-[1600px] border-collapse text-xs"><thead class="sticky top-0 z-10 text-left"><tr>@foreach(['No.','Bolus ID','Registry No.','Tag No.','Animal Name / Farmer','Sex','Birthday','Breed','Body Condition Score (BCS)','Specification / Details','Availability','Remarks','Status'] as $h)<th class="border px-2 py-2">{{ $h }}</th>@endforeach</tr></thead><tbody id="monitoring-animal-rows"></tbody></table></div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t p-4"><div class="flex items-center gap-2"><button type="button" id="monitoring-prev" class="page-btn">Previous</button><span id="monitoring-page-label" class="text-sm text-slate-600"></span><button type="button" id="monitoring-next" class="page-btn">Next</button></div><div class="flex gap-2"><button type="button" onclick="closeMonitoringModal()" class="action-secondary rounded-lg">Cancel</button><button type="submit" id="monitoring-submit-button" class="action-primary rounded-lg"><i class="fa-solid fa-floppy-disk mr-1"></i>Save Monitoring Report</button></div></div>
            <datalist id="breeding-technician-list">@foreach($technicianOptions ?? [] as $technician)<option value="{{ $technician }}">@endforeach</datalist><div id="breeding-monitor-modal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="breeding-modal-title"><div class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl"><header class="rounded-t-2xl bg-gradient-to-r from-[#0b1f3a] to-[#087d9b] p-5 text-white"><h2 id="breeding-modal-title" class="text-xl font-bold">What breeding method was used?</h2><p class="mt-1 text-sm text-blue-100">Choose AI or NM to show the matching breeding fields.</p></header><div class="space-y-4 p-5"><label class="block text-sm font-semibold">Breeding Method<select id="breeding-modal-method" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">Select method</option><option value="AI">AI (Artificial Insemination)</option><option value="NM">NM (Natural Mating)</option></select></label><div id="breeding-modal-fields" class="grid gap-3 sm:grid-cols-2"></div></div><footer class="flex justify-end gap-2 rounded-b-2xl border-t bg-slate-50 p-4"><button type="button" onclick="cancelBreedingModal()" class="action-secondary">Cancel</button><button type="button" onclick="saveBreedingModal()" class="action-primary"><i class="fa-solid fa-check mr-1"></i>Save Breeding Details</button></footer></div></div>
        </form>
    </div>

    <section class="inventory-panel mb-7 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-5 sm:p-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Monthly Monitoring Calendar</h2>
                    <p class="mt-1 text-sm text-slate-500">See which cooperative was visited and summarize animal health services recorded for each visit.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('ui.monitoring', ['month' => $previousMonth, 'cooperative_id' => $selectedCooperativeId ?: null, 'report_search' => $reportSearch ?: null, 'report_specification'=>$reportSpecification?:null, 'report_availability'=>$reportAvailability?:null, 'report_status'=>$reportStatus?:null]) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" aria-label="Previous month"><i class="fa-solid fa-chevron-left"></i></a>
                    <span class="min-w-36 text-center font-semibold text-slate-800">{{ \Carbon\Carbon::createFromFormat('Y-m', $selectedMonth)->format('F Y') }}</span>
                    <a href="{{ route('ui.monitoring', ['month' => $nextMonth, 'cooperative_id' => $selectedCooperativeId ?: null, 'report_search' => $reportSearch ?: null, 'report_specification'=>$reportSpecification?:null, 'report_availability'=>$reportAvailability?:null, 'report_status'=>$reportStatus?:null]) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" aria-label="Next month"><i class="fa-solid fa-chevron-right"></i></a>
                </div>
            </div>
            <form method="GET" action="{{ route('ui.monitoring') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <label class="text-xs font-semibold text-slate-600">Month
                    <input type="month" name="month" value="{{ $selectedMonth }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm font-normal text-slate-800">
                </label>
                <label class="text-xs font-semibold text-slate-600">Cooperative
                    <select name="cooperative_id" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm font-normal text-slate-800">
                        <option value="">All cooperatives</option>
                        @foreach($cooperatives as $cooperative)
                            <option value="{{ $cooperative->id }}" @selected((string)$cooperative->id === $selectedCooperativeId)>{{ $cooperative->cooperative_name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-600">Search cooperative
                    <input type="search" name="report_search" value="{{ $reportSearch }}" placeholder="Enter a cooperative name" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm font-normal text-slate-800">
                </label>
                <label class="text-xs font-semibold text-slate-600">Monitoring specification
                    <select name="report_specification" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm font-normal"><option value="">All specifications</option><option value="calf_monitoring" @selected($reportSpecification==='calf_monitoring')>Calf Monitoring</option><option value="breeding" @selected($reportSpecification==='breeding')>Breeding</option><option value="health_nutrition" @selected($reportSpecification==='health_nutrition')>Health &amp; Nutrition</option><option value="other_services" @selected($reportSpecification==='other_services')>Other Services</option></select>
                </label>
                <label class="text-xs font-semibold text-slate-600">Availability
                    <select name="report_availability" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm font-normal"><option value="">All availability</option><option value="available" @selected($reportAvailability==='available')>Available</option><option value="unavailable" @selected($reportAvailability==='unavailable')>Unavailable</option></select>
                </label>
                <label class="text-xs font-semibold text-slate-600">Monitoring status
                    <select name="report_status" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm font-normal"><option value="">All statuses</option><option value="completed" @selected($reportStatus==='completed')>Completed</option><option value="pending" @selected($reportStatus==='pending')>Pending</option><option value="unavailable" @selected($reportStatus==='unavailable')>Unavailable</option></select>
                </label>
                <div class="flex items-end gap-2 xl:col-start-3 xl:row-start-1 xl:row-span-3">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#0b1f3a] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#122b4d]"><i class="fa-solid fa-filter"></i>Apply filters</button>
                    <a href="{{ route('ui.monitoring') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Clear</a>
                </div>
            </form>
        </div>

        @php
            $calendarCells = array_merge(array_fill(0, $calendarStartOffset, null), range(1, $calendarDaysInMonth));
            while (count($calendarCells) % 7 !== 0) $calendarCells[] = null;
        @endphp
        <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50 text-center text-[10px] font-bold uppercase tracking-wide text-slate-500 sm:text-xs">
            @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)<div class="border-r border-slate-200 px-1 py-3 last:border-r-0">{{ $weekday }}</div>@endforeach
        </div>
        <div class="grid grid-cols-7">
            @foreach($calendarCells as $day)
                <div class="min-h-28 border-b border-r border-slate-200 p-1.5 last:border-r-0 sm:min-h-36 sm:p-2">
                    @if($day)
                        <div class="mb-1 text-xs font-semibold text-slate-500">{{ $day }}</div>
                        @foreach($calendarReportsByDay->get($day, collect()) as $calendarReport)
                            <a href="{{ route('ui.monitoring', ['report_id' => $calendarReport->id, 'month' => $selectedMonth, 'cooperative_id' => $selectedCooperativeId ?: null, 'report_search' => $reportSearch ?: null]) }}#monitoring-record-table" class="mb-1 block rounded-md border border-blue-200 bg-blue-50 p-1.5 text-[10px] leading-snug text-blue-950 hover:bg-blue-100 sm:p-2 sm:text-xs">
                                <span class="block font-bold">{{ $calendarReport->cooperative_name }}</span>
                                <span class="mt-1 block">{{ $calendarReport->summary['animals'] }} animals</span>
                                <span class="mt-1 block">BC {{ $calendarReport->summary['blood_collection'] }} · FS {{ $calendarReport->summary['fecalysis'] }} · Vac {{ $calendarReport->summary['vaccine'] }}</span>
                                <span class="block">De-worm {{ $calendarReport->summary['deworming'] }} · Vi-Supp {{ $calendarReport->summary['vi_supp'] }}</span>
                                <span class="block">Ear tag {{ $calendarReport->summary['ear_tagged'] }} · Bolus {{ $calendarReport->summary['bolus'] }} · Flash {{ $calendarReport->summary['flashing'] }}</span>
                                @if(!empty($calendarReport->summary['service_summary']))<span class="mt-1 block">Services: {{ implode(', ', $calendarReport->summary['service_summary']) }}</span>@endif
                                @if(!empty($calendarReport->summary['specifications']))<span class="block">{{ implode(', ', $calendarReport->summary['specifications']) }}</span>@endif
                            </a>
                        @endforeach
                    @endif
                </div>
            @endforeach
        </div>
        @if($calendarReports->isEmpty())
            <p class="border-t border-slate-200 px-5 py-4 text-sm text-slate-500">No monitoring visits match this month and filter.</p>
        @endif
    </section>

    @if($reports->isNotEmpty())
        <section class="inventory-panel mb-7 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-bold text-slate-900">Previous Visit Reports</h2>
                <p class="mt-1 text-sm text-slate-500">Open a saved report to review or print the previous visit.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[650px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Visit date</th><th class="px-5 py-3">Cooperative/Association</th><th class="px-5 py-3">Total / Available / Unavailable</th><th class="px-5 py-3">Monitored / Pending</th><th class="px-5 py-3">Specifications</th><th class="px-5 py-3">Created by</th><th class="px-5 py-3 text-right">Actions</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($reports as $report)
                            <tr>
                                <td class="whitespace-nowrap px-5 py-3">{{ \Carbon\Carbon::parse($report->report_date)->format('F j, Y') }}</td>
                                <td class="px-5 py-3 font-medium text-slate-800">{{ $report->cooperative_name }}</td>
                                <td class="whitespace-nowrap px-5 py-3">{{ $report->summary['animals'] }} / {{ $report->summary['available'] }} / {{ $report->summary['unavailable'] }}</td>
                                <td class="whitespace-nowrap px-5 py-3">{{ $report->summary['completed'] }} / {{ $report->summary['pending'] }}</td>
                                <td class="px-5 py-3">{{ implode(', ', $report->summary['specifications'] ?: ['—']) }}</td>
                                <td class="px-5 py-3">{{ $report->created_by_name ?? '—' }}</td>
                                <td class="px-5 py-3 text-right"><a href="{{ route('ui.monitoring', ['report_id' => $report->id]) }}#monitoring-record-table" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"><i class="fa-regular fa-folder-open"></i>View</a> <a href="{{ route('ui.monitoring.reports.show', $report->id) }}" class="inline-flex items-center gap-2 rounded-lg bg-[#0b1f3a] px-3 py-2 text-sm font-semibold text-white"><i class="fa-solid fa-pen"></i>Edit / Print</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <div class="mb-7 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach([
            ['title' => 'Total Animals', 'value' => $counts['total_animals'], 'description' => 'In selected report', 'icon' => 'fa-cow', 'iconClass' => 'bg-blue-50 text-blue-600'],
            ['title' => 'Available', 'value' => $counts['available'], 'description' => 'Can be monitored', 'icon' => 'fa-circle-check', 'iconClass' => 'bg-emerald-50 text-emerald-600'],
            ['title' => 'Unavailable', 'value' => $counts['unavailable'], 'description' => 'Unavailable for this visit', 'icon' => 'fa-circle-minus', 'iconClass' => 'bg-slate-100 text-slate-600'],
            ['title' => 'Monitored', 'value' => $counts['monitored'], 'description' => 'Completed destination entries', 'icon' => 'fa-clipboard-check', 'iconClass' => 'bg-violet-50 text-violet-600'],
            ['title' => 'Pending', 'value' => $counts['pending'], 'description' => 'Available animals pending', 'icon' => 'fa-clock', 'iconClass' => 'bg-orange-50 text-orange-600'],
        ] as $card)
            <div class="pcc-stat-card rounded-xl bg-white p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">{{ $card['title'] }}</p>
                        <h3 class="mt-3 text-3xl font-bold text-slate-800">{{ number_format($card['value']) }}</h3>
                        <p class="mt-2 text-xs text-slate-400">{{ $card['description'] }}</p>
                    </div>
                    <div class="pcc-stat-icon flex h-12 w-12 items-center justify-center rounded-xl text-xl {{ $card['iconClass'] }}">
                        <i class="fa-solid {{ $card['icon'] }}"></i>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <section id="monitoring-record-table" x-data="{ rowSearch: '' }" class="inventory-panel overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-bold text-slate-800">Animal Monitoring Report Table</h2>
                @if($activeReport)
                    <p class="mt-1 text-sm text-slate-500">{{ $activeReport->cooperative_name }} · {{ \Carbon\Carbon::parse($activeReport->report_date)->format('F j, Y') }}</p>
                @else
                    <p class="mt-1 text-sm text-slate-500">Create a visit report to fill and save this monitoring table.</p>
                @endif
            </div>
            @if($activeReport)
                <div class="flex flex-wrap items-center gap-2">
                    <input type="search" x-model.debounce.150ms="rowSearch" placeholder="Search rows" aria-label="Search monitoring report rows" class="w-40 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <span class="rounded-lg bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700">{{ number_format(count($activeReport->report_data['rows'] ?? [])) }} rows</span>
                    <a href="{{ route('ui.monitoring.reports.export', $activeReport->id) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"><i class="fa-solid fa-file-csv"></i>Export CSV</a>
                    <a href="{{ route('ui.monitoring.reports.show', $activeReport->id) }}" class="inline-flex items-center gap-2 rounded-lg bg-[#0b1f3a] px-3 py-2 text-sm font-semibold text-white hover:bg-[#122b4d]"><i class="fa-solid fa-pen-to-square"></i>Edit / Print</a>
                </div>
            @endif
        </div>

        <div class="inventory-table-scroll overflow-x-auto">
            <table class="inventory-data-table w-full min-w-[3000px] border-collapse text-xs">
                <thead class="bg-slate-50 text-center text-slate-700">
                    <tr class="font-bold">
                        <th rowspan="2" class="border border-slate-300 px-2 py-2">No.</th>
                        <th rowspan="2" class="border border-slate-300 px-2 py-2">Cooperative</th>
                        <th colspan="9" class="border border-slate-300 px-2 py-2">Animal Information</th>
                        <th colspan="4" class="border border-slate-300 px-2 py-2">Calf Monitoring</th>
                        <th colspan="4" class="border border-slate-300 px-2 py-2">Breeding</th>
                        <th colspan="6" class="border border-slate-300 px-2 py-2">Health and Nutrition</th>
                        <th colspan="3" class="border border-slate-300 px-2 py-2">Other Services</th>
                        <th colspan="2" class="border border-slate-300 px-2 py-2">Availability</th>
                        <th colspan="2" class="border border-slate-300 px-2 py-2">Monitoring</th>
                        <th rowspan="2" class="border border-slate-300 px-2 py-2">Remarks</th>
                    </tr>
                    <tr class="font-semibold">
                        @foreach(['Bolus ID','Registry ID','Tag No.','Animal Name','Farmer','Sex','Date of Birth','Breed','BCS','Last Calving', 'Sex', 'NM or AI', 'Sire ID', 'PD', 'Type of Estrus', 'NM or AI', 'Sire ID', 'Health Status','BC - Blood Collection', 'FS - Fecalysis', 'Vac - Vaccine', 'De-worm', 'Vi-Supp', 'Ear tagged', 'Bolus', 'Flashing','Availability','Reason','Specification','Status'] as $label)
                            <th class="border border-slate-300 px-2 py-2">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @if($activeReport)
                        @foreach(($activeReport->report_data['rows'] ?? []) as $index => $row)
                            @php $d=$row['monitoring_data']??[]; $display=['farmer_name'=>$row['farmer_name']??'','registry_id'=>$row['registry_id']??'','date_of_birth'=>$row['birth_date']??$row['date_of_birth']??'','dam_breed'=>$row['breed']??$row['dam_breed']??'','health_status'=>$d['health_status']??$row['health_status']??'','last_calving'=>$d['date_of_birth']??$d['last_calving']??'','calf_sex'=>$d['sex']??$row['calf_sex']??'','calf_nm_ai'=>$d['method']??$d['nm_ai']??'','calf_sire_id'=>$d['sire_id']??'','pd'=>$d['pd_date']??$d['pd']??'','type_of_estrus'=>$d['pregnancy_status']??$d['type_of_estrus']??'','breeding_nm_ai'=>$d['method']??'','breeding_sire_id'=>$d['bull_id']??'','blood_collection'=>$d['blood_collection']??'','fecalysis'=>$d['fecalysis']??'','vaccine'=>$d['vaccine']??'','deworming'=>$d['deworming']??'','vi_supp'=>$d['vi_supp']??'','ear_tagged'=>$d['ear_tagged']??'','bolus'=>$d['bolus']??'','flashing'=>$d['flashing']??'','remarks'=>$row['remarks']??'']; @endphp
                            <tr x-show="!rowSearch || $el.innerText.toLowerCase().includes(rowSearch.toLowerCase())">
                                <td class="border border-slate-300 px-2 py-2 text-center">{{ $index + 1 }}</td>
                                <td class="border border-slate-300 px-2 py-2">{{ $activeReport->cooperative_name }}</td>
                                @foreach(['bolus_id'=>$row['bolus_id']??'','registry_id'=>$row['registry_id']??'','tag_number'=>$row['tag_number']??'','animal_name'=>$row['animal_name']??'','farmer_name'=>$display['farmer_name'],'sex'=>$row['sex']??'','date_of_birth'=>$display['date_of_birth'],'dam_breed'=>$display['dam_breed'],'body_condition_score'=>$row['body_condition_score']??'','last_calving'=>$display['last_calving'],'calf_sex'=>$display['calf_sex'],'calf_nm_ai'=>$display['calf_nm_ai'],'calf_sire_id'=>$display['calf_sire_id'],'pd'=>$display['pd'],'type_of_estrus'=>$display['type_of_estrus'],'breeding_nm_ai'=>$display['breeding_nm_ai'],'breeding_sire_id'=>$display['breeding_sire_id'],'health_status'=>$display['health_status'],'blood_collection'=>$display['blood_collection'],'fecalysis'=>$display['fecalysis'],'vaccine'=>$display['vaccine'],'deworming'=>$display['deworming'],'vi_supp'=>$display['vi_supp'],'ear_tagged'=>$display['ear_tagged'],'bolus'=>$display['bolus'],'flashing'=>$display['flashing'],'availability_status'=>$row['availability_status']??'','unavailability_reason'=>$row['unavailability_reason']??'','monitoring_specification'=>$row['monitoring_specification']??'','monitoring_status'=>$row['monitoring_status']??'','remarks'=>$row['remarks']??''] as $field=>$value)
                                    <td class="border border-slate-300 px-2 py-2">{{ $value }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    @else
                        <tr><td colspan="33" class="px-5 py-12 text-center text-slate-500">No saved visit report yet. Create a report above to start recording these monitoring details.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@push('styles')
<style>
.monitoring-page [x-cloak]{display:none!important}
.monitoring-page .pcc-mating-hero{position:relative;isolation:isolate;overflow:hidden;background:radial-gradient(circle at 90% 18%,rgba(255,196,56,.25),transparent 19%),linear-gradient(118deg,#082d50 0%,#075785 55%,#0786a5 100%);box-shadow:0 16px 32px rgba(7,62,99,.2)}
.monitoring-page .pcc-mating-hero::after{content:"";position:absolute;z-index:-1;width:19rem;height:19rem;right:11%;top:-14rem;border:1px solid rgba(255,214,92,.45);border-radius:50%;box-shadow:0 0 0 26px rgba(255,214,92,.08),0 0 0 54px rgba(255,255,255,.045)}
.monitoring-page .pcc-brand-mark{position:relative;flex:none;width:4.35rem;height:4.35rem;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.34);border-radius:1.35rem;background:linear-gradient(145deg,rgba(255,255,255,.22),rgba(255,255,255,.07));color:#ffd05b;font-size:2rem;box-shadow:0 10px 22px rgba(0,28,52,.2)}
.monitoring-page .pcc-brand-mark span{position:absolute;right:-.45rem;bottom:-.4rem;display:grid;width:1.65rem;height:1.65rem;place-items:center;border:2px solid #075785;border-radius:999px;background:#ffd05b;color:#12345a;font-size:.75rem}
.monitoring-page .pcc-program-tag{display:inline-flex;align-items:center;gap:.4rem;margin-top:.7rem;border:1px solid rgba(255,213,91,.35);border-radius:999px;background:rgba(4,37,68,.24);padding:.35rem .7rem;color:#ffe08a;font-size:.7rem;font-weight:700;letter-spacing:.035em}
.monitoring-page .action-primary,.monitoring-page .action-secondary,.monitoring-page .page-btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;border-radius:.5rem;padding:.625rem 1rem;font-size:.875rem;font-weight:600}
.monitoring-page .action-primary{background:#ffd05b;color:#12345a;box-shadow:0 7px 16px rgba(4,37,68,.2)}.monitoring-page .action-primary:hover{background:#ffdc7d}
.monitoring-page .action-secondary{border:1px solid #cbd5e1;background:#fff;color:#334155}.monitoring-page .action-secondary:hover,.monitoring-page .page-btn:hover:not(:disabled){background:#f1f5f9}
.monitoring-page .pcc-mating-hero .action-secondary{border-color:rgba(255,255,255,.28);background:rgba(255,255,255,.1);color:#fff;backdrop-filter:blur(8px)}
.monitoring-page .pcc-mating-hero .action-secondary:hover{background:rgba(255,255,255,.2)}
.monitoring-page .pcc-stat-card{position:relative;overflow:hidden;border:1px solid #dce6ed;border-top:4px solid #0b5e8b;border-radius:1rem;box-shadow:0 5px 18px rgba(15,45,67,.06);transition:transform .16s ease,box-shadow .16s ease}
.monitoring-page .pcc-stat-card:hover{transform:translateY(-3px);box-shadow:0 14px 28px rgba(7,62,99,.13)}
.monitoring-page .pcc-stat-card:nth-child(2){border-top-color:#e87523}.monitoring-page .pcc-stat-card:nth-child(3){border-top-color:#25835b}.monitoring-page .pcc-stat-card:nth-child(4){border-top-color:#0786a5}
.monitoring-page .pcc-stat-icon{background:linear-gradient(145deg,#082d50,#0b6d9b);color:#fff;border-radius:1rem;box-shadow:0 8px 18px rgba(7,62,99,.2)}
.monitoring-page .inventory-panel{border-color:#dce6ed;box-shadow:0 7px 24px rgba(15,45,67,.06)}
.monitoring-page .inventory-panel thead,.monitoring-page .inventory-panel thead th{background:#103c60;color:#fff;font-weight:700}
.monitoring-page .inventory-panel thead th{border-bottom:3px solid #e8b22f}
.monitoring-page .inventory-panel tbody tr{transition:background-color .14s ease}.monitoring-page .inventory-panel tbody tr:hover{background:#f0f8fb}
.monitoring-page .inventory-table-scroll{max-height:72vh}
.monitoring-page .inventory-data-table{border-collapse:separate;border-spacing:0}
.monitoring-page .inventory-data-table thead th{position:sticky;top:0;z-index:20}
.monitoring-page .inventory-data-table tbody tr:nth-child(even){background:#f8fafc}.monitoring-page .inventory-data-table tbody tr:hover{background:#f0f8fb}
.monitoring-page .inventory-data-table tbody td{border-bottom:1px solid #e8edf3;vertical-align:middle}
.monitoring-page .inventory-modal-header{position:sticky;top:0;z-index:50;background:linear-gradient(105deg,#082d50,#075785);border-color:#075785}
.monitoring-page .inventory-modal-header button{display:grid;width:2.5rem;height:2.5rem;place-items:center;border:1px solid rgba(255,255,255,.3);border-radius:.75rem;color:#fff}
.monitoring-page .page-btn{border:1px solid #cbd5e1;background:#fff;color:#334155}.monitoring-page .page-btn:disabled{opacity:.4;cursor:not-allowed}
</style>
@endpush

@push('scripts')
<script>
const monitoringBaseUrl = @json(url('/monitoring/cooperatives'));
let monitoringAnimals = [], monitoringPage = 1, semenBullOptions = [], bcsOptions = [];
const aiTechnicianOptions = @json($technicianOptions ?? []);
let activeBreedingIndex = null, breedingModalOriginal = null, monitoringSubmitting = false;
const escapeMonitoring = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character]));
const monitoringSpecs = {
  calf_monitoring: {label:'Calf Monitoring',dest:'Calf Drop', fields:[['date_of_birth','Calf Date of Birth','date'],['birth_outcome','Birth Outcome','select','Live Birth|Stillbirth|Abortion'],['sex','Calf Sex','select','Male|Female'],['breed','Calf Breed','text'],['calf_id','Calf ID','text'],['sire_id','Sire ID','text'],['calf_status','Calf Status','select','Live|Out|Dead'],['findings','Findings','text']]},
  breeding: {label:'Breeding',dest:'Mating',fields:[['breeding_date','Breeding Date','date'],['method','Method','select','AI|NM'],['semen_bull_id','Semen Bull database ID (AI)','number'],['bull_id','Bull ID','text'],['semen_used','Semen Used','number'],['natural_mating_details','Natural Mating Details','text'],['pd_date','PD Date','date'],['pregnancy_status','Pregnancy Status','text'],['pregnancy_months','Pregnancy Months','number'],['expected_calving','Expected Calving','date'],['findings','Findings','text']]},
  health_nutrition: {label:'Health and Nutrition',dest:'Health & Nutrition',fields:[['health_status','Health Status (optional)','select','Healthy|Under Observation|Under Treatment|Needs Follow-up'],['no_of_visits_monitored','No. of Visits / Monitored','number'],['no_of_treatments','No. of Treatments','number'],['body_condition','Body Condition','text'],['symptoms','Symptoms','text'],['findings','Clinical Findings','text'],['treatment','Treatment','text'],['medication','Medication','text'],['recommendations','Recommendations','text'],['follow_up_date','Follow-up Date','date']]}
};
async function openMonitoringModal(){const m=document.getElementById('monitoring-modal');m.classList.remove('hidden');m.classList.add('flex');document.body.classList.add('overflow-hidden');const pending=[];if(!semenBullOptions.length)pending.push(fetch('/monitoring/semen-bulls',{headers:{Accept:'application/json'}}).then(r=>r.ok?r.json():[]).then(v=>semenBullOptions=v).catch(e=>console.warn('Semen bull list unavailable',e)));if(!bcsOptions.length)pending.push(fetch('/monitoring/bcs-scores',{headers:{Accept:'application/json'}}).then(r=>r.ok?r.json():[]).then(v=>bcsOptions=v).catch(e=>console.warn('BCS values unavailable',e)));await Promise.all(pending);if(monitoringAnimals.length)renderMonitoringRows()}
function closeMonitoringModal(){const m=document.getElementById('monitoring-modal');m.classList.add('hidden');m.classList.remove('flex');document.body.classList.remove('overflow-hidden')}
function control(name,label,type,options,required=false,disabled=false,value=''){let numericCounts=/\[(no_of_visits_monitored|no_of_treatments)\]$/.test(name),base=`name="${name}" aria-label="${label}" class="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-xs" ${numericCounts?'min="0" step="1"':''} ${required?'required':''} ${disabled?'disabled':''}`;if(name.endsWith('[semen_bull_id]'))return `<label class="block min-w-32">${label}<select ${base}><option value="">Select bull…</option>${semenBullOptions.map(b=>`<option value="${escapeMonitoring(b.id)}" ${String(value)===String(b.id)?'selected':''}>${escapeMonitoring(b.bull_id)} ${escapeMonitoring(b.bull_name)}</option>`).join('')}</select></label>`;if(type==='select')return `<label class="block min-w-32">${label}<select ${base}><option value="">Select…</option>${options.split('|').map(v=>`<option value="${escapeMonitoring(v)}" ${value===v?'selected':''}>${escapeMonitoring(v)}</option>`).join('')}</select></label>`;return `<label class="block min-w-32">${label}<input type="${type}" value="${escapeMonitoring(value)}" ${base}></label>`}
const healthServiceTypes=['Deworming','Vitamins','Vaccination','Uterine Flushing','Fecalysis','Ear Tagging','Recruitment','Hands-on Milking','Forage Demo','Other Services'];
function healthServicesFields(i,disabled){let services=monitoringAnimals[i]?._data?.services||[];return `<fieldset class="col-span-3 mt-2 rounded border border-emerald-200 bg-emerald-50 p-2"><legend class="px-1 text-[11px] font-bold text-emerald-900">Health and Nutrition Services (select any)</legend><div class="grid grid-cols-1 gap-2 lg:grid-cols-2">${healthServiceTypes.map((type,index)=>{let s=services[index]||{},checked=!!s.selected;return `<div class="rounded border border-slate-200 bg-white p-2"><input type="hidden" name="rows[${i}][monitoring_data][services][${index}][type]" value="${type}"><label class="flex items-center gap-2 text-xs font-semibold"><input type="checkbox" name="rows[${i}][monitoring_data][services][${index}][selected]" value="1" ${checked?'checked':''} ${disabled?'disabled':''}>${type}</label>${type==='Other Services'?`<label class="mt-2 block text-[10px] text-slate-600">Other service name<input name="rows[${i}][monitoring_data][services][${index}][other_service_name]" value="${escapeMonitoring(s.other_service_name||'')}" class="mt-1 w-full rounded border px-2 py-1 text-xs" placeholder="e.g. Ear Tagging" ${disabled?'disabled':''}></label>`:''}<label class="mt-2 block text-[10px] text-slate-600">Dosage (mL, optional)<input type="number" min="0" step="0.01" name="rows[${i}][monitoring_data][services][${index}][dosage_ml]" value="${escapeMonitoring(s.dosage_ml||'')}" class="mt-1 w-full rounded border px-2 py-1 text-xs" placeholder="When applicable" ${disabled?'disabled':''}></label><label class="mt-2 block text-[10px] text-slate-600">Service remarks<input name="rows[${i}][monitoring_data][services][${index}][remarks]" value="${escapeMonitoring(s.remarks||'')}" class="mt-1 w-full rounded border px-2 py-1 text-xs" ${disabled?'disabled':''}></label></div>`}).join('')}</div></fieldset>`}
function rowFields(i,spec,disabled){let cfg=monitoringSpecs[spec];if(!cfg)return '<span class="text-slate-400">Select a specification</span>';if(spec==='breeding'){let d=monitoringAnimals[i]?._data||{},bull=semenBullOptions.find(b=>String(b.id)===String(d.semen_bull_id)),summary=d.method?`${d.method} · ${d.breeding_date||'No breeding date'} · ${bull?.bull_id||d.bull_id||'No sire selected'}`:'Method and breeding details required';return `<div class="mb-2 text-[10px] font-bold text-blue-700">Destination: ${cfg.dest}</div><div class="rounded-lg border border-amber-200 bg-amber-50 p-2"><p class="text-xs font-semibold">${escapeMonitoring(summary)}</p><button type="button" class="mt-2 rounded border border-amber-300 bg-white px-2 py-1 text-xs font-semibold text-amber-900" onclick="openBreedingModal(${i})" ${disabled?'disabled':''}>${d.method?'Edit Breeding Details':'Choose Method and Enter Details'}</button></div>`}let fields=cfg.fields.map(f=>control(`rows[${i}][monitoring_data][${f[0]}]`,f[1],f[2],f[3],false,disabled,monitoringAnimals[i]?._data?.[f[0]]||'')).join('');return `<div class="mb-1 text-[10px] font-bold text-blue-700">Destination: ${cfg.dest}</div><div class="grid min-w-[640px] grid-cols-3 gap-1">${fields}${spec==='health_nutrition'?healthServicesFields(i,disabled):''}</div>`}
function breedingInput(key,label,type,value='',options=[]){let id=`breeding-field-${key}`,inner=type==='select'?`<select id="${id}" data-breeding-field="${key}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">Select…</option>${options.map(option=>`<option value="${escapeMonitoring(option.value)}" ${String(value)===String(option.value)?'selected':''}>${escapeMonitoring(option.label)}</option>`).join('')}</select>`:`<input id="${id}" data-breeding-field="${key}" type="${type}" value="${escapeMonitoring(value)}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">`;return `<label class="block text-sm font-semibold">${label}${inner}</label>`}
function renderBreedingModalFields(method,data={}){const box=document.getElementById('breeding-modal-fields');if(!method){box.innerHTML='<p class="text-sm text-slate-500 sm:col-span-2">Select AI or NM to continue.</p>';return}const bulls=semenBullOptions.map(b=>({value:b.id,label:`${b.bull_id||'No ID'} · ${b.bull_name}${b.breed?` · ${b.breed}`:''}`}));let fields=[breedingInput('breeding_date','Breeding Date *','date',data.breeding_date||''),breedingInput('semen_bull_id','Sire ID or Name *','select',data.semen_bull_id||'',bulls)];if(method==='AI'){fields.push(breedingInput('pregnancy_status','Pregnancy Diagnosis (PD) *','select',data.pregnancy_status||'',[{value:'Pregnant',label:'Pregnant'},{value:'Not Pregnant',label:'Not Pregnant'}]));fields.push(breedingInput('estrus_type','Type of Estrus *','select',data.estrus_type||'',[{value:'ES',label:'ES'},{value:'NH',label:'NH'},{value:'FTAI',label:'FTAI'}]));fields.push(breedingInput('technician','AI Technician *','text',data.technician||''));const tech=document.getElementById('breeding-technician-list');tech.innerHTML=aiTechnicianOptions.map(name=>`<option value="${escapeMonitoring(name)}">`).join('')}fields.push(breedingInput('record_date','Date (if different)','date',data.record_date||''));box.innerHTML=fields.join('');if(method==='AI')document.getElementById('breeding-field-technician')?.setAttribute('list','breeding-technician-list')}
function openBreedingModal(i,previous=null){activeBreedingIndex=i;const animal=monitoringAnimals[i];breedingModalOriginal=previous||{spec:animal._spec,data:JSON.parse(JSON.stringify(animal._data||{}))};const data=animal._data||{};const methodSelect=document.getElementById('breeding-modal-method');methodSelect.value=data.method||'';renderBreedingModalFields(methodSelect.value,data);const modal=document.getElementById('breeding-monitor-modal');modal.classList.remove('hidden');modal.classList.add('flex')}
function closeBreedingModal(){const modal=document.getElementById('breeding-monitor-modal');modal.classList.add('hidden');modal.classList.remove('flex')}
function cancelBreedingModal(){if(activeBreedingIndex!==null){const animal=monitoringAnimals[activeBreedingIndex];animal._spec=breedingModalOriginal?.spec||'';animal._data=breedingModalOriginal?.data||{};document.getElementById(`breeding-storage-${activeBreedingIndex}`)?.remove();if(animal._spec==='breeding')storeBreedingInputs(activeBreedingIndex,animal._data)}closeBreedingModal();renderMonitoringRows()}
function storeBreedingInputs(i,data){document.getElementById(`breeding-storage-${i}`)?.remove();if(data?.method!=='AI'&&data?.method!=='NM')return;const storage=document.createElement('div');storage.id=`breeding-storage-${i}`;storage.hidden=true;const keys=['method','breeding_date','semen_bull_id','bull_id','record_date',...(data.method==='AI'?['pregnancy_status','estrus_type','technician']:[])];keys.forEach(key=>{const input=document.createElement('input');input.type='hidden';input.name=`rows[${i}][monitoring_data][${key}]`;input.value=data[key]??'';storage.appendChild(input)});document.getElementById('monitoring-form').appendChild(storage)}
function saveBreedingModal(){const i=activeBreedingIndex,method=document.getElementById('breeding-modal-method').value;if(i===null||!method){alert('Choose AI or NM.');return}const read=key=>document.querySelector(`[data-breeding-field="${key}"]`)?.value?.trim()||'',breedingDate=read('breeding_date'),bullId=read('semen_bull_id'),recordDate=read('record_date');if(!breedingDate||!bullId){alert('Breeding Date and Sire ID or Name are required.');return}const bull=semenBullOptions.find(item=>String(item.id)===String(bullId));const data={method,breeding_date:breedingDate,semen_bull_id:bullId,bull_id:bull?.bull_id||'',record_date:recordDate};if(method==='AI'){data.pregnancy_status=read('pregnancy_status');data.estrus_type=read('estrus_type');data.technician=read('technician');if(!data.pregnancy_status||!data.estrus_type||!data.technician){alert('AI requires Pregnancy Diagnosis, Type of Estrus, and AI Technician.');return}}const animal=monitoringAnimals[i];animal._spec='breeding';animal._data=data;storeBreedingInputs(i,data);checkMonitoringComplete(animal);closeBreedingModal();const cell=document.getElementById(`monitoring-fields-${i}`);if(cell)cell.innerHTML=rowFields(i,'breeding',false);updateMonitoringCounts()}
function renderMonitoringRows(){let tbody=document.getElementById('monitoring-animal-rows'), search=document.getElementById('monitoring-search').value.toLowerCase(), avf=document.getElementById('filter-availability').value,sf=document.getElementById('filter-specification').value,size=Number(document.getElementById('monitoring-page-size').value);let filtered=monitoringAnimals.map((a,i)=>({...a,_i:i})).filter(a=>(`${a.bolus_id||''} ${a.registry_number||''} ${a.tag_number||''} ${a.animal_name||''}`.toLowerCase().includes(search))&&(!avf||a._availability===avf)&&(!sf||a._spec===sf));let pages=Math.max(1,Math.ceil(filtered.length/size));monitoringPage=Math.min(monitoringPage,pages);let shown=new Set(filtered.slice((monitoringPage-1)*size,monitoringPage*size).map(a=>a._i));let items=monitoringAnimals.map((a,i)=>({...a,_i:i,_show:shown.has(i)}));tbody.innerHTML=items.map(a=>{let i=a._i,un=a._availability==='unavailable';return `<tr class="${un?'bg-slate-100 text-slate-500':''} ${a._show?'':'hidden'}" data-row="${i}"><td class="border px-2 py-2">${i+1}<input type="hidden" name="rows[${i}][carabao_id]" value="${a.carabao_id}"><input type="hidden" name="rows[${i}][availability_status]" value="${un?'unavailable':'available'}"></td><td class="border px-2 py-2">${a.bolus_id||'—'}</td><td class="border px-2 py-2">${a.registry_number||'—'}</td><td class="border px-2 py-2">${a.tag_number||'—'}</td><td class="border px-2 py-2">${a.animal_name||'—'}<div class="text-slate-500">${a.farmer_name||''}</div></td><td class="border px-2 py-2">${a.sex||'—'}</td><td class="border px-2 py-2">${a.birth_date||'—'}</td><td class="border px-2 py-2">${escapeMonitoring(a.breed||'—')}</td><td class="border px-2 py-2"><select name="rows[${i}][body_condition_score]" onchange="setMonitoringBcs(${i},this.value)" class="w-28 rounded border border-slate-300 px-2 py-1" ${un?'disabled':''}><option value="">Not scored</option>${bcsOptions.map(score=>`<option value="${escapeMonitoring(score)}" ${String(a._bcs||'')===String(score)?'selected':''}>${escapeMonitoring(score)}</option>`).join('')}</select></td><td class="border p-2"><select class="mb-1 w-full rounded border px-2 py-1" name="rows[${i}][monitoring_specification]" onchange="setMonitoringSpec(${i},this.value)" ${un?'disabled':''}><option value="">Select specification</option>${Object.entries(monitoringSpecs).map(([k,v])=>`<option value="${k}" ${a._spec===k?'selected':''}>${v.label}</option>`).join('')}</select><div id="monitoring-fields-${i}">${rowFields(i,a._spec,un)}</div></td><td class="border px-2 py-2"><label class="flex items-center gap-1 font-semibold"><input type="checkbox" ${un?'checked':''} onchange="setMonitoringUnavailable(${i},this.checked)">Unavailable</label>${un?`<select name="rows[${i}][unavailability_reason]" class="mt-2 rounded border px-1 py-1"><option value="">Reason</option>${['Not Present','Transferred','Sick','Dead','Sold','Other'].map(v=>`<option value="${v}" ${a._reason===v?'selected':''}>${v}</option>`).join('')}</select>`:''}<span class="mt-1 block rounded px-1 py-0.5 ${un?'bg-slate-200':'bg-emerald-100 text-emerald-800'}">${un?'Unavailable':'Available'}</span></td><td class="border p-2"><input class="w-36 rounded border px-2 py-1" name="rows[${i}][remarks]" placeholder="Remarks" value="${a._remarks||''}"></td><td class="border px-2 py-2"><span class="rounded bg-${un?'slate':'amber'}-100 px-2 py-1">${un?'Unavailable':a._spec&&a._complete?'Completed':'Pending'}</span></td></tr>`}).join('');let empty=document.getElementById('monitoring-empty');empty.textContent=document.getElementById('monitoring-cooperative').value?'No animals are registered under this cooperative.':'Select a cooperative to load animals.';empty.classList.toggle('hidden',monitoringAnimals.length>0);document.getElementById('monitoring-page-label').textContent=`Page ${monitoringPage} of ${pages} · ${filtered.length} animals`;document.getElementById('monitoring-prev').disabled=monitoringPage<=1;document.getElementById('monitoring-next').disabled=monitoringPage>=pages;updateMonitoringCounts()}
function updateMonitoringCounts(){let total=monitoringAnimals.length,un=monitoringAnimals.filter(a=>a._availability==='unavailable').length,done=monitoringAnimals.filter(a=>a._availability!=='unavailable'&&a._spec&&a._complete).length;document.getElementById('count-total').textContent=total;document.getElementById('count-available').textContent=total-un;document.getElementById('count-unavailable').textContent=un;document.getElementById('count-monitored').textContent=done;document.getElementById('count-pending').textContent=total-un-done}
function setMonitoringUnavailable(i,value){monitoringAnimals[i]._availability=value?'unavailable':'available';renderMonitoringRows()}
function setMonitoringSpec(i,value){const animal=monitoringAnimals[i],previous={spec:animal._spec,data:JSON.parse(JSON.stringify(animal._data||{}))};if(value==='breeding'){animal._spec='breeding';renderMonitoringRows();openBreedingModal(i,previous);return}document.getElementById(`breeding-storage-${i}`)?.remove();animal._spec=value;animal._data=value===previous.spec?previous.data:{};let cell=document.getElementById(`monitoring-fields-${i}`);cell.innerHTML=rowFields(i,value,false);checkMonitoringComplete(animal);updateMonitoringCounts()}
function setMonitoringBcs(i,value){monitoringAnimals[i]._bcs=value;}
function checkMonitoringComplete(a){let d=a._data||{},required={calf_monitoring:['date_of_birth','birth_outcome'],breeding:['breeding_date','method','semen_bull_id']}[a._spec]||[];let healthComplete=a._spec!=='health_nutrition'||!!String(d.health_status||'').trim()||(Array.isArray(d.services)&&d.services.some(s=>s&&s.selected));let aiComplete=a._spec!=='breeding'||d.method!=='AI'||!!(d.pregnancy_status&&d.estrus_type&&d.technician);a._complete=required.every(k=>String(d[k]||'').trim())&&healthComplete&&aiComplete&&(a._spec!=='calf_monitoring'||d.birth_outcome!=='Live Birth'||!!d.sex)}
document.getElementById('monitoring-cooperative').addEventListener('change',async e=>{let id=e.target.value;monitoringAnimals=[];monitoringPage=1;renderMonitoringRows();if(!id)return;document.getElementById('monitoring-loading').classList.remove('hidden');document.getElementById('monitoring-empty').classList.add('hidden');try{let res=await fetch(`${monitoringBaseUrl}/${id}/animals`,{headers:{'Accept':'application/json'}});if(!res.ok)throw new Error('Could not load animals for this cooperative.');let data=await res.json();monitoringAnimals=data.map(a=>({...a,_availability:'available',_spec:'',_bcs:'',_complete:false}));}catch(err){let box=document.getElementById('monitoring-error');box.textContent=err.message;box.classList.remove('hidden')}finally{document.getElementById('monitoring-loading').classList.add('hidden');renderMonitoringRows()}});
['monitoring-search','filter-availability','filter-specification','monitoring-page-size'].forEach(id=>document.getElementById(id).addEventListener('input',()=>{monitoringPage=1;renderMonitoringRows()}));document.getElementById('monitoring-prev').onclick=()=>{monitoringPage--;renderMonitoringRows()};document.getElementById('monitoring-next').onclick=()=>{monitoringPage++;renderMonitoringRows()};
document.getElementById('breeding-modal-method').addEventListener('change',e=>renderBreedingModalFields(e.target.value,monitoringAnimals[activeBreedingIndex]?._data||{}));
document.getElementById('monitoring-form').addEventListener('submit',e=>{if(monitoringSubmitting){e.preventDefault();return}if(!monitoringAnimals.length){e.preventDefault();alert('Select a cooperative that has registered animals.');return}const incomplete=monitoringAnimals.findIndex(a=>a._spec==='breeding'&&a._availability!=='unavailable'&&!a._complete);if(incomplete>=0){e.preventDefault();alert(`Complete Breeding details for animal ${monitoringAnimals[incomplete].registry_number||monitoringAnimals[incomplete].animal_id||incomplete+1}.`);return}monitoringSubmitting=true;const button=document.getElementById('monitoring-submit-button');button.disabled=true;button.innerHTML='<i class="fa-solid fa-spinner fa-spin mr-1"></i>Saving…'});
document.getElementById('monitoring-animal-rows').addEventListener('input',e=>{let input=e.target,name=input.name||'',dataMatch=name.match(/^rows\[(\d+)\]\[monitoring_data\](.*)$/),plainMatch=name.match(/^rows\[(\d+)\]\[(remarks|unavailability_reason|body_condition_score)\]$/);if(dataMatch){let a=monitoringAnimals[Number(dataMatch[1])];a._data=a._data||{};let path=[...dataMatch[2].matchAll(/\[([^\]]+)\]/g)].map(m=>/^\d+$/.test(m[1])?Number(m[1]):m[1]),value=input.type==='checkbox'?input.checked:input.value,target=a._data;path.slice(0,-1).forEach((key,index)=>{if(target[key]===undefined)target[key]=typeof path[index+1]==='number'?[]:{};target=target[key]});if(path.length)target[path[path.length-1]]=value;checkMonitoringComplete(a);updateMonitoringCounts();let tr=input.closest('tr'),status=tr?.lastElementChild?.querySelector('span');if(status)status.textContent=a._complete?'Completed':'Pending'}else if(plainMatch){let a=monitoringAnimals[Number(plainMatch[1])];a[plainMatch[2]==='remarks'?'_remarks':plainMatch[2]==='body_condition_score'?'_bcs':'_reason']=input.value}});
document.getElementById('monitoring-animal-rows').addEventListener('change',e=>{let input=e.target,name=input.name||'',m=name.match(/^rows\[(\d+)\]\[unavailability_reason\]$/);if(m)monitoringAnimals[Number(m[1])]._reason=input.value});
</script>
@endpush
