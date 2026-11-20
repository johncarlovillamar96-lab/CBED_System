@extends('layouts.admin')

@section('title', 'Monitoring | CBED')
@section('dataSource', 'database')
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
            <a href="{{ route('ui.activity-logs') }}" class="action-secondary"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>Activity Logs</a>
        </div>
    </div>

    @if($cooperatives->isEmpty())
        <p class="mb-6 rounded-lg bg-amber-100 p-3 text-sm text-amber-900">Add a cooperative before creating a monitoring report.</p>
    @endif

    <dialog id="monitoring-modal" aria-labelledby="monitoring-modal-title" class="m-auto max-h-[94vh] w-[98vw] max-w-none overflow-auto rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-950/60">
        <form method="POST" action="{{ route('ui.monitoring.reports.store') }}" id="monitoring-form">
            @csrf
            <header class="flex items-center justify-between gap-3 border-b p-5">
                <h2 id="monitoring-modal-title" class="text-xl font-bold text-[#12345a]">Add Monitoring Record</h2>
                <button type="button" onclick="closeMonitoringModal()" class="rounded-lg p-2 hover:bg-slate-100" aria-label="Close monitoring entry"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </header>
            <div class="grid gap-4 px-5 pt-5 sm:grid-cols-3">
                <x-monitoring-picker id="monitoring-cooperative" label="Cooperative" name="cooperative_id">
                    <option value="">Select a cooperative</option>
                    @foreach ($cooperatives as $cooperative)
                        <option value="{{ $cooperative->id }}">{{ $cooperative->cooperative_name }}</option>
                    @endforeach
                </x-monitoring-picker>
                <x-monitoring-picker id="monitoring-farmer-filter" label="Farmer">
                    <option value="">Select a farmer</option>
                </x-monitoring-picker>
                <label class="text-sm font-semibold">Monitoring date
                    <input type="date" name="monitoring_date" required value="{{ now()->toDateString() }}" class="mt-1 block min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-normal">
                </label>
            </div>
            <div class="space-y-4 p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p id="monitoring-farmer-animals" role="status" class="text-sm text-slate-600">Select a cooperative and farmer to view live animals.</p>
                    <button type="button" id="monitoring-add-row" disabled class="cbed-add-button action-secondary disabled:opacity-50">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Add Row
                    </button>
                </div>
                <p id="monitoring-loading" role="status" class="hidden rounded-lg bg-slate-50 p-3 text-sm text-slate-600"><i class="fa-solid fa-spinner fa-spin mr-2" aria-hidden="true"></i>Loading cooperative animals?</p>
                <p id="monitoring-error" role="alert" class="hidden rounded-lg bg-rose-50 p-3 text-sm text-rose-800"></p>

                <div class="overflow-x-auto rounded-lg border border-slate-200" role="region" aria-label="Monitoring entry rows" tabindex="0">
                    <table class="inventory-data-table w-full min-w-[90rem] table-fixed text-left text-sm">
                        <colgroup>
                            <col class="w-12">
                            <col class="w-72">
                            <col class="w-40">
                            <col class="w-24">
                            <col class="w-[34rem]">
                            <col class="w-12">
                            <col class="w-64">
                        </colgroup>
                        <thead class="bg-slate-100 text-xs uppercase text-slate-600">
                            <tr>
                                @foreach (['No.', 'Animal ID', 'Classification', 'BCS', 'Monitoring Services', 'Remove', 'Remarks'] as $heading)
                                    <th scope="col" class="px-2 py-3">
                                        @if ($heading === 'Remove')<span class="sr-only">{{ $heading }}</span>@else{{ $heading }}@endif
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody id="monitoring-animal-rows" class="divide-y divide-slate-100"></tbody>
                    </table>
                    <p id="monitoring-empty" role="status" class="px-4 py-5 text-center text-sm text-slate-500">Add a row to choose a live animal.</p>
                </div>
            </div>
            <footer class="flex justify-end gap-2 border-t bg-slate-50 p-4">
                <button type="button" onclick="closeMonitoringModal()" class="action-secondary">Cancel</button>
                <button type="submit" id="monitoring-submit-button" disabled class="action-primary disabled:opacity-50"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>Save Monitoring Report</button>
            </footer>

        </form>


    </dialog>

    <dialog id="monitoring-calf-dialog" x-data="calfDropPage(true)" @open-monitoring-calf.window="openMonitoringCalf($event.detail)" @close="showAdd = false" aria-labelledby="add-calf-title" class="fixed inset-0 m-0 h-full max-h-none w-full max-w-none overflow-visible bg-transparent p-0 backdrop:bg-transparent">
        @include('partials.calf-entry-modal')
    </dialog>
    <dialog id="monitoring-breeding-dialog" x-data="breedingPage(true)" @open-monitoring-breeding.window="openMonitoringMating($event.detail)" @close="monitoringMatingContext = null" aria-labelledby="monitoring-breeding-title" class="shared-mating-modal m-auto max-h-[94vh] w-11/12 max-w-4xl overflow-y-auto rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-950/60">
        <form @submit.prevent="saveMonitoringMating()">
            <header class="border-b p-5"><h2 id="monitoring-breeding-title" class="text-xl font-bold text-slate-900">Mating Record</h2></header>
            <div class="space-y-4 p-5">
                <label class="block text-sm font-medium text-slate-700">Cooperative<input readonly :value="monitoringMatingContext?.cooperative || ''" class="form-input mt-1 bg-slate-50"></label>
                @include('partials.mating-edit-form', ['monitoringMating' => true])
                <p x-show="notice" x-text="notice" role="alert" class="text-sm text-rose-800"></p>
            </div>
            <footer class="flex flex-wrap justify-end gap-2 border-t bg-slate-50 p-4">
                <button x-show="monitoringMatingContext?.hasService" type="button" @click="finishMonitoringMating(null)" class="mr-auto rounded-lg px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Remove service</button>
                <button type="button" @click="$root.close()" class="action-secondary">Cancel</button>
                <button type="submit" class="action-primary">Save Mating Details</button>
            </footer>
        </form>
    </dialog>
    <dialog id="monitoring-health-dialog" oncancel="event.preventDefault(); closeHealthEditor()" aria-labelledby="health-editor-title" class="fixed inset-0 m-0 h-full max-h-none w-full max-w-none overflow-visible bg-transparent p-0 backdrop:bg-transparent">
        @include('partials.health-activity-modal')
    </dialog>
    @include('partials.animal-exit-dialog', ['monitoringExit' => true])

    <details class="group inventory-panel mb-7 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50 sm:px-5 [&::-webkit-details-marker]:hidden">
            <h2 class="text-lg font-bold text-slate-900">Monitoring Calendar</h2>
            <span class="flex shrink-0 items-center gap-2 text-sm font-semibold text-slate-700">
                <span class="group-open:hidden">Show calendar</span>
                <span class="hidden group-open:inline">Hide calendar</span>
                <i class="fa-solid fa-chevron-down text-xs group-open:rotate-180" aria-hidden="true"></i>
            </span>
        </summary>
        <div class="border-y border-slate-200 px-4 py-3 sm:px-5">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="mt-1 text-sm text-slate-500">Browse actual monitoring, breeding, calf, and health service dates. {{ number_format($calendarEvents->count()) }} dated record(s) in 2026.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('ui.monitoring', ['month' => $previousMonth, 'cooperative_id' => $selectedCooperativeId ?: null, 'report_search' => $reportSearch ?: null, 'report_specification'=>$reportSpecification?:null, 'report_availability'=>$reportAvailability?:null, 'report_status'=>$reportStatus?:null]) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" aria-label="Previous month"><i class="fa-solid fa-chevron-left"></i></a>
                    <span class="min-w-36 text-center font-semibold text-slate-800">{{ \Carbon\Carbon::createFromFormat('Y-m', $selectedMonth)->format('F Y') }}</span>
                    <a href="{{ route('ui.monitoring', ['month' => $nextMonth, 'cooperative_id' => $selectedCooperativeId ?: null, 'report_search' => $reportSearch ?: null, 'report_specification'=>$reportSpecification?:null, 'report_availability'=>$reportAvailability?:null, 'report_status'=>$reportStatus?:null]) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" aria-label="Next month"><i class="fa-solid fa-chevron-right"></i></a>
                </div>
            </div>
            <form method="GET" action="{{ route('ui.monitoring') }}" class="grid grid-cols-1 gap-x-3 gap-y-2 sm:grid-cols-2 lg:grid-cols-4">
                <label class="min-w-0 text-xs font-semibold text-slate-600">Calendar view<select name="view" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm font-normal"><option value="day" @selected($calendarView==='day')>Day View</option><option value="month" @selected($calendarView==='month')>Month View</option><option value="year" @selected($calendarView==='year')>Year View</option></select></label>
                <label class="min-w-0 text-xs font-semibold text-slate-600">Selected day<input type="date" name="date" min="2026-01-01" max="2026-12-31" value="{{ $selectedDate }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm font-normal"></label>
                <label class="min-w-0 text-xs font-semibold text-slate-600">Month
                    <input type="month" name="month" value="{{ $selectedMonth }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm font-normal text-slate-800">
                </label>
                <label class="min-w-0 text-xs font-semibold text-slate-600">Cooperative
                    <select name="cooperative_id" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm font-normal text-slate-800">
                        <option value="">All cooperatives</option>
                        @foreach($cooperatives as $cooperative)
                            <option value="{{ $cooperative->id }}" @selected((string)$cooperative->id === $selectedCooperativeId)>{{ $cooperative->cooperative_name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="min-w-0 text-xs font-semibold text-slate-600">Search cooperative
                    <input type="search" name="report_search" value="{{ $reportSearch }}" placeholder="Enter a cooperative name" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm font-normal text-slate-800">
                </label>
                <label class="min-w-0 text-xs font-semibold text-slate-600">Monitoring specification
                    <select name="report_specification" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm font-normal"><option value="">All specifications</option><option value="calf_monitoring" @selected($reportSpecification==='calf_monitoring')>Calf Monitoring</option><option value="breeding" @selected($reportSpecification==='breeding')>Breeding</option><option value="health_nutrition" @selected($reportSpecification==='health_nutrition')>Health &amp; Nutrition</option><option value="other_services" @selected($reportSpecification==='other_services')>Other Services</option></select>
                </label>
                <label class="min-w-0 text-xs font-semibold text-slate-600">Availability
                    <select name="report_availability" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm font-normal"><option value="">All availability</option><option value="available" @selected($reportAvailability==='available')>Available</option><option value="unavailable" @selected($reportAvailability==='unavailable')>Unavailable</option></select>
                </label>
                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#0b1f3a] px-3 py-1.5 text-sm font-semibold text-white hover:bg-[#122b4d]"><i class="fa-solid fa-filter"></i>Apply filters</button>
                    <a href="{{ route('ui.monitoring') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Clear</a>
                </div>
            </form>
        </div>

        @php
            $calendarCells = array_merge(array_fill(0, $calendarStartOffset, null), range(1, $calendarDaysInMonth));
            while (count($calendarCells) % 7 !== 0) $calendarCells[] = null;
        @endphp
        @if($calendarView==='year')
            <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3">
                @for($monthNo=1;$monthNo<=12;$monthNo++) @php $monthKey=sprintf('2026-%02d',$monthNo); @endphp
                    <a href="{{ route('ui.monitoring',['view'=>'month','month'=>$monthKey]) }}" class="rounded-lg border border-slate-200 p-4 hover:bg-slate-50"><b>{{ \Carbon\Carbon::create(2026,$monthNo,1)->format('F') }}</b><span class="float-right text-sm text-slate-500">{{ $calendarEvents->filter(fn($e)=>substr((string)$e->date,0,7)===$monthKey)->count() }} record(s)</span></a>
                @endfor
            </div>
        @elseif($calendarView==='day' && $selectedDate)
            <div class="border-t p-4"><h3 class="mb-3 font-semibold">Services on {{ \Carbon\Carbon::parse($selectedDate)->format('F j, Y') }}</h3>
                @forelse($calendarEventsByDate->get($selectedDate,collect()) as $event)<div class="mb-2 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm"><b>{{ $event->service }}</b> · {{ $event->animal_id ?? 'Animal ID unavailable' }} @if(!empty($event->bolus_id)) · Bolus {{ $event->bolus_id }} @endif @if(!empty($event->farmer_name)) · {{ $event->farmer_name }} @endif @if(!empty($event->cooperative_name)) · {{ $event->cooperative_name }} @endif</div>@empty<p class="text-sm text-slate-500">No records for this date.</p>@endforelse
            </div>
        @else
        <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50 text-center text-[10px] font-bold uppercase tracking-wide text-slate-500 sm:text-xs">
            @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)<div class="border-r border-slate-200 px-1 py-3 last:border-r-0">{{ $weekday }}</div>@endforeach
        </div>
        <div class="grid grid-cols-7">
            @foreach($calendarCells as $day)
                <div class="min-h-28 border-b border-r border-slate-200 p-1.5 last:border-r-0 sm:min-h-36 sm:p-2">
                    @if($day)
                        <div class="mb-1 text-xs font-semibold text-slate-500">{{ $day }}</div>
                        @foreach($calendarEventsByDate->get(sprintf('2026-%02d-%02d',(int)substr($selectedMonth,5,2),$day), collect()) as $event)
                            <a href="{{ route('ui.monitoring',['view'=>'day','date'=>sprintf('2026-%02d-%02d',(int)substr($selectedMonth,5,2),$day),'month'=>$selectedMonth]) }}" class="mb-1 block rounded-md border border-blue-200 bg-blue-50 p-1.5 text-[10px] leading-snug text-blue-950 hover:bg-blue-100 sm:p-2 sm:text-xs"><span class="block font-bold">{{ $event->service }}</span><span>{{ $event->animal_id ?? 'Animal ID unavailable' }}@if(!empty($event->bolus_id)) · {{ $event->bolus_id }}@endif</span>@if(!empty($event->cooperative_name))<span class="block">{{ $event->cooperative_name }}</span>@endif</a>
                        @endforeach
                    @endif
                </div>
            @endforeach
        </div>
        @if($calendarEvents->isEmpty())
            <p class="border-t border-slate-200 px-5 py-4 text-sm text-slate-500">No 2026 monitoring and service records were found.</p>
        @endif
        @endif
    </details>

    <section id="monitoring-record-table" class="inventory-panel pcc-records-panel overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b p-5 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-bold text-slate-800">Animal Monitoring Report Table</h2><p class="mt-1 text-sm text-slate-500">{{ number_format($consolidatedRowsPage->total()) }} live animal(s) · {{ $reportYear }}</p></div><div class="flex flex-wrap gap-2"><label class="self-center text-xs font-semibold">Paper size<select id="monitoring-paper-size" onchange="document.documentElement.classList.toggle('paper-a4',this.value==='a4')" class="ml-1 rounded border px-2 py-2"><option value="legal">Legal landscape</option><option value="a4">A4 landscape</option></select></label><a href="{{ route('ui.monitoring', array_merge(request()->query(), ['table_export'=>'xlsx'])) }}" class="rounded-lg bg-emerald-700 px-4 py-2.5 text-center text-sm font-semibold text-white">Export filtered Excel</a><a href="{{ route('ui.monitoring', array_merge(request()->query(), ['table_export'=>'csv'])) }}" class="rounded-lg border border-emerald-700 px-3 py-2.5 text-center text-sm font-semibold text-emerald-800">CSV</a><button type="button" onclick="window.print()" class="rounded-lg bg-[#0b1f3a] px-4 py-2.5 text-sm font-semibold text-white">Print report</button></div></div>
        <form method="GET" action="{{ route('ui.monitoring') }}" class="grid grid-cols-1 gap-3 border-b p-4 sm:grid-cols-2 xl:grid-cols-4">
            <label class="text-xs font-semibold text-slate-600">Monitoring year<input name="year" type="number" min="2000" max="2100" value="{{ $reportYear }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></label>
            <label class="text-xs font-semibold text-slate-600">GIP reporting date<input name="gip_reporting_date" type="date" value="{{ $gipReportingDate }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></label>
            <input type="hidden" name="table_per_page" value="{{ $tablePerPage }}">
            <label class="text-xs font-semibold text-slate-600">Cooperative / Association<select name="table_cooperative" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"><option value="">All cooperatives</option>@foreach($cooperatives as $cooperative)<option value="{{ $cooperative->cooperative_name }}" @selected($tableCooperative===$cooperative->cooperative_name)>{{ $cooperative->cooperative_name }}</option>@endforeach</select></label>
            <label class="text-xs font-semibold text-slate-600">Farmer<select name="table_farmer" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"><option value="">All farmers</option>@foreach($farmerOptions as $farmer)<option value="{{ $farmer }}" @selected($tableFarmer===$farmer)>{{ $farmer }}</option>@endforeach</select></label>
            <label class="text-xs font-semibold text-slate-600">Animal ID<input name="table_animal" value="{{ $tableAnimal }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></label>
            <label class="text-xs font-semibold text-slate-600">Province<input name="table_province" value="{{ $tableProvince }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></label>
            <label class="text-xs font-semibold text-slate-600">Municipality<input name="table_municipality" value="{{ $tableMunicipality }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></label>
            <label class="text-xs font-semibold text-slate-600">Barangay<input name="table_barangay" value="{{ $tableBarangay }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></label>
            <label class="text-xs font-semibold text-slate-600">Monitoring date from<input name="table_date_start" type="date" value="{{ $tableDateStart }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></label>
            <label class="text-xs font-semibold text-slate-600">Monitoring date to<input name="table_date_end" type="date" value="{{ $tableDateEnd }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></label>
            <label class="text-xs font-semibold text-slate-600">Search<input name="table_search" value="{{ $tableSearch }}" type="search" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" placeholder="Farmer, animal or cooperative"></label>
            <label class="text-xs font-semibold text-slate-600">Service type<select name="table_service" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"><option value="">All services</option>@foreach($categoryLabels as $key=>$label)<option value="{{ $key }}" @selected($tableService===$key)>{{ $label }}</option>@endforeach</select></label>
            <div class="flex items-end gap-2"><button class="rounded-lg bg-[#0b1f3a] px-4 py-2.5 text-sm font-semibold text-white">Apply filters</button><a href="{{ route('ui.monitoring') }}#monitoring-record-table" class="rounded-lg border px-4 py-2.5 text-sm">Clear</a></div>
        </form>
        <div class="overflow-x-auto print:hidden"><table class="w-full min-w-[980px] border-collapse text-sm">
            <thead class="bg-slate-100 text-left text-xs uppercase text-slate-600"><tr>@foreach(['No.','Farmer','Animal ID','Classification','BCS','DIM','Pregnancy','Services','Monitoring Date','Remarks'] as $heading)<th class="border px-3 py-2">{{ $heading }}</th>@endforeach</tr></thead>
            <tbody>
                @forelse($consolidatedRowsPage as $index=>$row)
                    <tr class="align-top even:bg-slate-50"><td class="border px-3 py-2 text-center">{{ $consolidatedRowsPage->firstItem()+$index }}</td><td class="border px-3 py-2">{{ $row['farmer'] }}</td><td class="border px-3 py-2 font-medium">{{ $row['animal_id'] }}</td><td class="border px-3 py-2">{{ $row['classification'] }}</td><td class="border px-3 py-2">{{ $row['bcs'] ?? '–' }}</td><td class="border px-3 py-2">{{ $row['dim'] ?? '–' }} @if(!empty($row['dry_off_review']))<span class="mt-1 block rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800">For Dry-off Review</span>@endif</td><td class="border px-3 py-2">{{ $row['pregnancy'] }}</td><td class="border px-3 py-2">@forelse($row['compact_services'] as $service)<span class="mr-1 inline-block rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-blue-800">{{ $service }}</span>@empty – @endforelse</td><td class="border px-3 py-2">{{ !empty($row['monitoring_date']) ? \Carbon\Carbon::parse($row['monitoring_date'])->format('F j, Y') : '–' }}</td><td class="border px-3 py-2">{{ $row['remarks'] ?: '–' }}</td></tr>
                @empty<tr><td colspan="10" class="border px-3 py-8 text-center text-slate-500">No live female animals match these filters.</td></tr>@endforelse
            </tbody>
        </table></div>
        <div id="animal-monitoring-print">
            <header class="monitoring-print-header">
                <p>Department of Agriculture</p><p>Philippine Carabao Center at</p><p>Central Luzon State University</p><p>Science City of Muñoz, Nueva Ecija</p>
                <h1>ANIMAL MONITORING</h1>
                <div class="monitoring-print-meta"><span><b>Cooperative/Association:</b> {{ $tableCooperative ?: 'All cooperatives' }}</span><span><b>Province:</b> {{ $tableProvince ?: 'All provinces' }}</span><span><b>Municipality:</b> {{ $tableMunicipality ?: 'All municipalities' }}</span><span><b>Barangay:</b> {{ $tableBarangay ?: 'All barangays' }}</span><span><b>Date:</b> @if($tableDateStart && $tableDateEnd){{ \Carbon\Carbon::parse($tableDateStart)->format('M j, Y') }} – {{ \Carbon\Carbon::parse($tableDateEnd)->format('M j, Y') }}@elseif($tableDateStart){{ \Carbon\Carbon::parse($tableDateStart)->format('F j, Y') }}@elseif($tableDateEnd){{ \Carbon\Carbon::parse($tableDateEnd)->format('F j, Y') }}@else{{ \Carbon\Carbon::parse($gipReportingDate)->format('F j, Y') }}@endif</span></div>
            </header>
            <div class="inventory-table-scroll overflow-x-auto"><table class="monitoring-report-grid w-full min-w-[2200px] border-collapse text-[11px]">
                <thead class="sticky top-0 z-20 bg-slate-50 text-center text-slate-700"><tr><th rowspan="2" class="border px-2 py-2">No.</th><th rowspan="2" class="border px-2 py-2">Name of Farmer</th><th colspan="4" class="border px-2 py-2">Animal Information</th><th colspan="5" class="border px-2 py-2">Calf Monitoring</th><th colspan="6" class="border px-2 py-2">Breeding</th><th colspan="7" class="border px-2 py-2">Health and Nutrition</th><th colspan="4" class="border px-2 py-2">Other Services</th><th rowspan="2" class="border px-2 py-2">Latest Monitoring Date</th><th rowspan="2" class="border px-2 py-2">Remarks</th></tr>
                    <tr>@foreach(['Animal ID','Date of Birth','Dam Breed','BCS','Last Calving','Calf Sex','Breeding Method','Sire ID','Monitoring Date','PD','Type of Estrus','Breeding Method','Sire ID','Breeding Date','Monitoring Date','BCS','Fecalysis (FS)','Vaccination','Deworming','Vitamins','Uterine Flushing','Monitoring Date','Ear Tagging','Bolus','Other Service','Monitoring Date'] as $heading)<th class="border px-2 py-2">{{ $heading }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @forelse($consolidatedRows->values() as $index=>$row)
                        @php($readableDate=fn($value)=>$value?\Carbon\Carbon::parse($value)->format('M j, Y'):'–')
                        @php($health=$row['health']) @php($other=$row['other'])
                        <tr class="align-top"><td class="border px-2 py-1 text-center">{{ $index+1 }}</td><td class="border px-2 py-1">{{ $row['farmer'] }}</td>
                            <td class="border px-2 py-1">{{ $row['animal_id'] }}</td><td class="border px-2 py-1">{{ $readableDate($row['birth_date']) }}</td><td class="border px-2 py-1">{{ $row['dam_breed'] ?: '–' }}</td><td class="border px-2 py-1">{{ $row['bcs'] ?? '–' }}<br><small>{{ $readableDate($row['bcs_date']) }}</small></td>
                            <td class="border px-2 py-1">{{ $row['calf'] ? $readableDate($row['calf']['date']) : '–' }}</td><td class="border px-2 py-1">{{ $row['calf']['sex'] ?? '–' }}</td><td class="border px-2 py-1">{{ $row['calf']['method'] ?? '–' }}</td><td class="border px-2 py-1">{{ $row['calf']['sire_id'] ?? '–' }}</td><td class="border px-2 py-1">{{ $readableDate($row['calf']['monitoring_date'] ?? null) }}</td>
                            <td class="border px-2 py-1">{{ $row['breeding']['pd'] ?? '–' }}</td><td class="border px-2 py-1">{{ $row['breeding']['estrus'] ?? '–' }}</td><td class="border px-2 py-1">{{ $row['breeding']['method'] ?? '–' }}</td><td class="border px-2 py-1">{{ $row['breeding']['sire_id'] ?? '–' }}</td><td class="border px-2 py-1">{{ $readableDate($row['breeding']['date'] ?? null) }}</td><td class="border px-2 py-1">{{ $readableDate($row['breeding']['monitoring_date'] ?? null) }}</td>
                            <td class="border px-2 py-1">{{ $health['bcs']['value'] ?? ($row['bcs'] ?? '–') }}</td><td class="border px-2 py-1">{{ $health['fecalysis']['value'] ?? '–' }}</td><td class="border px-2 py-1">{{ $health['vaccine']['value'] ?? '–' }}</td><td class="border px-2 py-1">{{ $health['deworming']['value'] ?? '–' }}</td><td class="border px-2 py-1">{{ $health['vi_supp']['value'] ?? '–' }}</td><td class="border px-2 py-1">{{ $health['flushing']['value'] ?? '–' }}</td><td class="border px-2 py-1">{{ $readableDate($row['health_monitoring_date']) }}</td>
                            <td class="border px-2 py-1">{{ $other['ear_tagging']['value'] ?? ($row['tag_number'] ?: '–') }}</td><td class="border px-2 py-1">{{ $other['bolus']['value'] ?? ($row['bolus_id'] ?: '–') }}</td><td class="border px-2 py-1">@php($otherList=collect($other)->reject(fn($value,$key)=>in_array($key,['ear_tagging','bolus'],true))->pluck('name')->unique()->implode(', ')){{ $otherList ?: '–' }}</td><td class="border px-2 py-1">{{ $readableDate($row['other_monitoring_date']) }}</td>
                            <td class="border px-2 py-1">{{ $readableDate($row['monitoring_date']) }}</td><td class="border px-2 py-1">{{ $row['remarks'] ?: '–' }}</td></tr>
                    @empty<tr><td colspan="30" class="border px-3 py-8 text-center">No live animals match these filters.</td></tr>@endforelse
                </tbody>
            </table></div>
            <footer class="monitoring-print-signatures"><div><b>Monitored By:</b><span></span><small>Name and Signature</small><strong>AI TECHNICIAN</strong><small>Position/Designation</small></div><div><b>Attested By:</b><span></span><strong>KRISTINE JOY C. CUIZON</strong><small>Name and Signature</small><strong>DATA CUSTODIAN / MONITORING OFFICER</strong><small>Position/Designation</small></div><div><b>Noted By:</b><span></span><strong>REBECCA RUBY G. GABRIEL</strong><small>Name and Signature</small><strong>CBED COORDINATOR</strong><small>Position/Designation</small></div></footer>
        </div>
        @include('partials.server-table-pagination', [
            'paginator' => $consolidatedRowsPage,
            'sizeParam' => 'table_per_page',
            'filterParams' => request()->except(['table_page', 'table_per_page', 'table_export']),
            'unfilteredTotal' => $liveAnimalTotal,
        ])
    </section>
</div>
@endsection

@push('styles')
<style>
.monitoring-page [x-cloak]{display:none!important}
.shared-calf-modal .form-input,.shared-mating-modal .form-input{width:100%;border:1px solid #cbd5e1;border-radius:.5rem;background:#fff;padding:.625rem .75rem;font-size:.875rem;color:#1e293b;outline:none}
.shared-calf-modal .form-input:focus,.shared-mating-modal .form-input:focus{border-color:#1d4ed8;box-shadow:0 0 0 2px #dbeafe}
#animal-exit-dialog .filter-input{width:100%;border:1px solid #cbd5e1;border-radius:.5rem;background:#fff;padding:.625rem .75rem;font-size:.875rem;color:#1e293b}
.monitoring-page .pcc-mating-hero{position:relative;isolation:isolate;overflow:hidden;background:radial-gradient(circle at 90% 18%,rgba(255,196,56,.25),transparent 19%),linear-gradient(118deg,#082d50 0%,#075785 55%,#0786a5 100%);box-shadow:0 16px 32px rgba(7,62,99,.2)}
.monitoring-page .pcc-mating-hero::after{content:"";position:absolute;z-index:-1;width:19rem;height:19rem;right:11%;top:-14rem;border:1px solid rgba(255,214,92,.45);border-radius:50%;box-shadow:0 0 0 26px rgba(255,214,92,.08),0 0 0 54px rgba(255,255,255,.045)}
.monitoring-page .pcc-brand-mark{position:relative;flex:none;width:4.35rem;height:4.35rem;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.34);border-radius:1.35rem;background:linear-gradient(145deg,rgba(255,255,255,.22),rgba(255,255,255,.07));color:#ffd05b;font-size:2rem;box-shadow:0 10px 22px rgba(0,28,52,.2)}
.monitoring-page .pcc-brand-mark span{position:absolute;right:-.45rem;bottom:-.4rem;display:grid;width:1.65rem;height:1.65rem;place-items:center;border:2px solid #075785;border-radius:999px;background:#ffd05b;color:#12345a;font-size:.75rem}
.monitoring-page .pcc-program-tag{display:inline-flex;align-items:center;gap:.4rem;margin-top:.7rem;border:1px solid rgba(255,213,91,.35);border-radius:999px;background:rgba(4,37,68,.24);padding:.35rem .7rem;color:#ffe08a;font-size:.7rem;font-weight:700;letter-spacing:.035em}
.monitoring-page .action-primary,.monitoring-page .action-secondary{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;border-radius:.5rem;padding:.625rem 1rem;font-size:.875rem;font-weight:600}
.monitoring-page .action-primary{background:#ffd05b;color:#12345a;box-shadow:0 7px 16px rgba(4,37,68,.2)}.monitoring-page .action-primary:hover{background:#ffdc7d}
.monitoring-page .action-secondary{border:1px solid #cbd5e1;background:#fff;color:#334155}.monitoring-page .action-secondary:hover{background:#f1f5f9}
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
.monitoring-page .inventory-data-table{border-collapse:separate;border-spacing:0}
.monitoring-page .inventory-data-table thead th{position:sticky;top:0;z-index:20}
.monitoring-page .inventory-data-table tbody tr:nth-child(even){background:#f8fafc}.monitoring-page .inventory-data-table tbody tr:hover{background:#f0f8fb}
.monitoring-page .inventory-data-table tbody td{border-bottom:1px solid #e8edf3;vertical-align:top}
.monitoring-page .pcc-records-panel{border-color:#dce6ed;box-shadow:0 7px 24px rgba(15,45,67,.06)}
.monitoring-page .pcc-records-panel>div:first-child{border-bottom-color:#e3ebf0}
.monitoring-page #animal-monitoring-print{display:none}
.monitoring-print-header,.monitoring-print-signatures{display:none}
.monitoring-page .monitoring-report-grid{border-collapse:separate;border-spacing:0;font-size:.875rem;text-align:left}
.monitoring-page #monitoring-record-table .inventory-table-scroll{max-height:none}
.monitoring-page #monitoring-record-table .monitoring-report-grid thead,.monitoring-page #monitoring-record-table .monitoring-report-grid thead th{position:static}
.monitoring-page .monitoring-report-grid thead th{padding:1rem .75rem!important;background:#103c60!important;color:#fff!important;border-color:rgba(255,255,255,.16)!important;border-bottom:1px solid rgba(255,255,255,.18)!important;font-size:.7rem;font-weight:700;text-align:center;text-transform:uppercase;letter-spacing:.045em}
.monitoring-page .monitoring-report-grid thead tr:first-child th{border-bottom:3px solid #e8b22f!important}
.monitoring-page .monitoring-report-grid tbody td{padding:1rem .75rem!important;border-right:1px solid #e8edf3;border-bottom:1px solid #e8edf3;color:#334155;text-align:left;vertical-align:top;line-height:1.45}
.monitoring-page .monitoring-report-grid tbody td:first-child{text-align:center;font-weight:600;color:#64748b}
.monitoring-page .monitoring-report-grid tbody tr:nth-child(even){background:#f8fafc}
.monitoring-page .monitoring-report-grid tbody tr:hover{background:#f0f8fb}
.monitoring-page .monitoring-report-grid tbody tr:last-child td{border-bottom:0}
@page{size:legal landscape;margin:7mm}
@page a4{size:A4 landscape;margin:6mm}
@media print{
    html.paper-a4{page:a4}
    body *{visibility:hidden!important}
    #animal-monitoring-print,#animal-monitoring-print *{visibility:visible!important}
    #monitoring-record-table{overflow:visible!important;border:0!important;box-shadow:none!important}
    .monitoring-page{overflow:visible!important;min-width:0!important;background:#fff!important}
    #animal-monitoring-print{display:block!important;position:absolute!important;inset:0 auto auto 0!important;width:100%!important;margin:0!important;padding:0!important;background:#fff!important;color:#000!important;font-family:Arial,sans-serif!important}
    #animal-monitoring-print .inventory-table-scroll{overflow:visible!important}
    #animal-monitoring-print .monitoring-print-header{display:block!important;margin-bottom:3mm;text-align:center;font-size:8pt;line-height:1.2}
    #animal-monitoring-print .monitoring-print-header p{margin:0}
    #animal-monitoring-print .monitoring-print-header h1{margin:2mm 0;font-size:12pt;font-weight:700}
    #animal-monitoring-print .monitoring-print-meta{display:flex;flex-wrap:wrap;justify-content:space-between;gap:1mm 4mm;margin-top:2mm;text-align:left;font-size:7pt}
    #animal-monitoring-print .monitoring-print-signatures{display:grid!important;grid-template-columns:repeat(3,1fr);gap:8mm;margin:7mm 2mm 0;text-align:center;font-size:7pt}
    #animal-monitoring-print .monitoring-print-signatures div{display:flex;flex-direction:column;align-items:center;gap:1mm}
    #animal-monitoring-print .monitoring-print-signatures span{display:block;width:85%;height:6mm;border-bottom:.5pt solid #000}
    #animal-monitoring-print .monitoring-print-signatures small{font-size:6pt}
    #animal-monitoring-print .monitoring-report-grid{min-width:0!important;width:100%!important;table-layout:fixed!important;border-collapse:collapse!important;border-spacing:0!important;font-size:6.5pt!important;line-height:1.15!important;text-align:center!important}
    #animal-monitoring-print .monitoring-report-grid th,#animal-monitoring-print .monitoring-report-grid td{border:.35pt solid #000!important;padding:1.1mm .6mm!important;text-align:center!important;vertical-align:middle!important;overflow-wrap:anywhere!important}
    #animal-monitoring-print .monitoring-report-grid thead{display:table-header-group!important;position:static!important}
    #animal-monitoring-print .monitoring-report-grid thead th{position:static!important}
    #animal-monitoring-print .monitoring-report-grid tr{break-inside:avoid!important;page-break-inside:avoid!important}
    .print\:hidden{display:none!important}
}
</style>
@endpush

@include('partials.calf-entry-script')
@include('partials.mating-entry-script')
@include('partials.health-activity-script')
@include('partials.animal-exit-script')

@push('scripts')
<script>
const monitoringBaseUrl = @json(url('/monitoring/cooperatives'));
let monitoringAnimals = [], monitoringRoster = [], bcsOptions = [];
let monitoringSubmitting = false;
let monitoringAnimalsRequest = null;
const monitoringDialog = document.getElementById('monitoring-modal');

function monitoringPicker() {
    return {
        open: false,
        search: '',
        options: [],
        selected: '',
        observer: null,
        init() {
            this.$nextTick(() => {
                const select = this.$refs.select;
                const refresh = () => {
                    this.options = Array.from(select.options, option => ({
                        label: option.textContent.trim(),
                        value: option.value,
                    }));
                    this.selected = select.value;
                    if (this.options.length <= 1) this.open = false;
                };
                refresh();
                this.observer = new MutationObserver(refresh);
                this.observer.observe(select, {childList: true, subtree: true});
            });
        },
        destroy() {
            this.observer?.disconnect();
        },
        get selectedLabel() {
            return this.options.find(option => option.value === this.selected)?.label ?? '';
        },
        get matches() {
            const query = this.search.trim().toLowerCase();
            return this.options.filter(option => option.label.toLowerCase().includes(query));
        },
        togglePicker() {
            this.open = !this.open;
            if (this.open) {
                this.search = '';
                this.$nextTick(() => this.$refs.search.focus());
            }
        },
        closePicker() {
            this.open = false;
            this.$refs.trigger.focus();
        },
        choose(value) {
            if (value !== this.selected) {
                const select = this.$refs.select;
                this.selected = select.value = value;
                select.dispatchEvent(new Event('input', {bubbles: true}));
                select.dispatchEvent(new Event('change', {bubbles: true}));
            }
            this.closePicker();
        },
    };
}

monitoringDialog.addEventListener('cancel', (event) => {
    if (monitoringSubmitting) event.preventDefault();
});
const escapeMonitoring = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character]));
const monitoringSpecs = {
  calf_monitoring: {label:'Calf Monitoring'},
  breeding: {label:'Breeding'},
  health_nutrition: {label:'Health and Nutrition'},
  nature_of_exit: {label:'Nature of Exit'},
};
async function openMonitoringModal() {
    if (monitoringDialog.open) return;
    monitoringDialog.showModal();
    renderMonitoringRows();
    if (!bcsOptions.length) {
        try {
            const response = await fetch('/monitoring/bcs-scores', {headers: {Accept: 'application/json'}});
            if (response.ok) bcsOptions = await response.json();
        } catch (error) {
            console.warn('BCS values unavailable', error);
        }
    }
    if (monitoringAnimals.length) renderMonitoringRows();
}
function closeMonitoringModal() {
    if (!monitoringSubmitting) monitoringDialog.close();
}
function populateMonitoringFarmers() {
    const select = document.getElementById('monitoring-farmer-filter');
    const farmers = new Map();
    for (const animal of monitoringRoster) {
        if (animal.farmer_id) farmers.set(String(animal.farmer_id), animal.farmer_name || `Farmer #${animal.farmer_id}`);
    }
    select.replaceChildren(new Option('Select a farmer', ''),
        ...[...farmers].sort((a, b) => a[1].localeCompare(b[1])).map(([id, name]) => new Option(name, id)));
}

function farmerMonitoringAnimals(farmerId) {
    return farmerId ? monitoringRoster.filter(animal => String(animal.farmer_id) === farmerId) : [];
}

function monitoringAnimalLabel(animal) {
    const ids = [
        animal.registry_number && `Registry: ${animal.registry_number}`,
        animal.tag_number && `Tag: ${animal.tag_number}`,
        animal.bolus_id && `Bolus: ${animal.bolus_id}`,
    ].filter(Boolean);
    return [animal.animal_id || animal.registry_number || `Animal #${animal.carabao_id}`, animal.animal_name, ...ids].filter(Boolean).join(' · ');
}

function monitoringRows() {
    return monitoringAnimals.flatMap((row, index) => row ? [{row, index}] : []);
}

function renderMonitoringRows() {
    const rows = monitoringRows();
    const selected = new Set(rows.map(({row}) => row.carabao_id).filter(Boolean));
    document.getElementById('monitoring-animal-rows').innerHTML = rows.map(({row, index}, position) =>
        renderMonitoringRow(row, index, position, selected)
    ).join('');

    const farmer = document.getElementById('monitoring-farmer-filter');
    const farmerAnimals = farmerMonitoringAnimals(farmer.value);
    const available = farmerAnimals.some(animal => !selected.has(animal.carabao_id));
    const pendingRow = rows.some(({row}) => row._farmerId === farmer.value && !row.carabao_id);
    const message = document.getElementById('monitoring-farmer-animals');
    message.textContent = farmer.value
        ? `${farmerAnimals.length} live female animal(s) for ${farmer.selectedOptions[0].textContent}.`
        : (monitoringRoster.some(animal => animal.farmer_id)
            ? 'Select a farmer to view their live female animals.'
            : (document.getElementById('monitoring-cooperative').value && !monitoringAnimalsRequest
                ? 'No live female animals are linked to a farmer in this cooperative.'
                : 'Select a cooperative and farmer to view live animals.'));
    document.getElementById('monitoring-add-row').disabled = monitoringSubmitting || !available || pendingRow;
    document.getElementById('monitoring-submit-button').disabled = monitoringSubmitting || !rows.length || rows.some(({row}) => !row.carabao_id);
    document.getElementById('monitoring-empty').classList.toggle('hidden', rows.length > 0);
}

function renderMonitoringRow(row, index, position, selected) {
    const options = farmerMonitoringAnimals(row._farmerId).map(animal => {
        const isSelected = animal.carabao_id === row.carabao_id;
        return `<option value="${animal.carabao_id}" ${isSelected ? 'selected' : (selected.has(animal.carabao_id) ? 'disabled' : '')}>${escapeMonitoring(monitoringAnimalLabel(animal))}</option>`;
    }).join('');
    const disabled = row.carabao_id ? '' : 'disabled';
    const services = Object.entries(monitoringSpecs).map(([key, service]) => {
        const data = row._services?.[key];
        return `<button type="button" id="monitoring-service-${index}-${key}" onclick="openMonitoringService(${index}, '${key}')" ${disabled}
            class="flex h-11 items-center justify-center rounded-lg border px-2 text-center text-xs font-semibold leading-tight disabled:opacity-50 ${data ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'}">${data ? 'View ' : ''}${service.label}</button>
            ${data ? `<input type="hidden" name="rows[${index}][monitoring_services][${key}]" value="${escapeMonitoring(JSON.stringify(data))}">` : ''}`;
    }).join('');

    return `<tr data-row="${index}" class="align-top">
        <td class="p-2"><span class="flex h-11 items-center tabular-nums text-slate-600">${position + 1}</span><input type="hidden" name="rows[${index}][availability_status]" value="available"></td>
        <td class="p-2">
            <select id="monitoring-animal-${index}" name="rows[${index}][carabao_id]" required aria-label="Animal ID" onchange="selectMonitoringAnimal(${index}, this.value)" class="h-11 w-full rounded-lg border border-slate-300 bg-white px-2">
                <option value="">Select Animal ID</option>${options}
            </select>
            <p class="mt-1 text-xs text-slate-600">${escapeMonitoring(row._farmerName)}</p>
        </td>
        <td class="p-2"><span class="flex min-h-11 items-center break-words text-slate-700">${escapeMonitoring(row.classification || (row.carabao_id ? 'Unclassified' : '—'))}</span></td>
        <td class="p-2"><input type="number" min="0" max="10" step="0.01" name="rows[${index}][body_condition_score]" aria-label="Body condition score" value="${escapeMonitoring(row._bcs ?? '')}" class="h-11 w-full rounded-lg border border-slate-300 bg-white px-2 disabled:bg-slate-50" ${disabled}></td>
        <td class="p-2"><div class="grid grid-cols-4 gap-1.5">${services}</div></td>
        <td class="p-2"><button type="button" aria-label="Remove row ${position + 1}" onclick="removeMonitoringRow(${index})" class="flex h-11 w-full items-center justify-center rounded-lg text-rose-700 hover:bg-rose-50"><i class="fa-solid fa-trash" aria-hidden="true"></i></button></td>
        <td class="p-2"><input name="rows[${index}][remarks]" aria-label="Remarks" maxlength="2000" value="${escapeMonitoring(row._remarks || '')}" class="h-11 w-full rounded-lg border border-slate-300 bg-white px-2 disabled:bg-slate-50" ${disabled}></td>
    </tr>`;
}

document.getElementById('monitoring-add-row').addEventListener('click', (event) => {
    if (event.currentTarget.disabled) return;
    const farmer = document.getElementById('monitoring-farmer-filter');
    const index = monitoringAnimals.push({_farmerId: farmer.value, _farmerName: farmer.selectedOptions[0].textContent}) - 1;
    renderMonitoringRows();
    document.getElementById(`monitoring-animal-${index}`)?.focus();
});

function selectMonitoringAnimal(index, value) {
    const row = monitoringAnimals[index];
    if (!row || String(row.carabao_id || '') === value) return;
    const animal = farmerMonitoringAnimals(row._farmerId).find(item => String(item.carabao_id) === value);
    if (value && (!animal || monitoringAnimals.some(item => item?.carabao_id === animal.carabao_id))) return;
    monitoringAnimals[index] = {
        ...animal,
        _farmerId: row._farmerId, _farmerName: row._farmerName,
        _services: {}, _bcs: '',
    };
    renderMonitoringRows();
    document.getElementById(`monitoring-animal-${index}`)?.focus();
}

function removeMonitoringRow(index) {
    monitoringAnimals[index] = null;
    renderMonitoringRows();
}

function focusMonitoringService(index, service) {
    document.getElementById(`monitoring-service-${index}-${service}`)?.focus();
}

function openMonitoringService(index, service) {
    const row = monitoringAnimals[index];
    if (!row?.carabao_id || !monitoringSpecs[service]) return;
    const cooperative = document.getElementById('monitoring-cooperative');
    const context = {
        index,
        dam: {...row, id: row.carabao_id},
        carabaoId: row.carabao_id,
        animalId: row.animal_id || row.registry_number || String(row.carabao_id),
        registryId: row.registry_number || row.animal_id || String(row.carabao_id),
        cooperativeId: cooperative.value,
        cooperative: cooperative.selectedOptions[0]?.textContent.trim() || '',
        farmerId: row.farmer_id || row._farmerId,
        farmer: row.farmer_name || row._farmerName,
        reportDate: monitoringDialog.querySelector('[name="monitoring_date"]').value,
        hasService: Boolean(row._services[service]),
        data: row._services[service] || {},
    };
    if (service === 'health_nutrition') {
        openHealthEditor(null, false, context);
    } else {
        const event = {calf_monitoring: 'open-monitoring-calf', breeding: 'open-monitoring-breeding', nature_of_exit: 'open-monitoring-exit'}[service];
        window.dispatchEvent(new CustomEvent(event, {detail: context}));
    }
}

for (const [eventName, service] of Object.entries({
    'monitoring-calf-draft': 'calf_monitoring',
    'monitoring-breeding-draft': 'breeding',
    'monitoring-health-draft': 'health_nutrition',
    'monitoring-exit-draft': 'nature_of_exit',
})) {
    window.addEventListener(eventName, event => {
        const {index, data} = event.detail;
        const row = monitoringAnimals[index];
        if (!row) return;
        if (data) row._services[service] = data;
        else delete row._services[service];
        renderMonitoringRows();
        queueMicrotask(() => focusMonitoringService(index, service));
    });
}

document.getElementById('monitoring-cooperative').addEventListener('change', async (event) => {
    monitoringAnimalsRequest?.abort();
    const id = event.target.value;
    const request = id ? new AbortController() : null;
    monitoringAnimalsRequest = request;

    const loading = document.getElementById('monitoring-loading');
    const error = document.getElementById('monitoring-error');
    const farmer = document.getElementById('monitoring-farmer-filter');
    loading.classList.toggle('hidden', !request);
    error.classList.add('hidden');
    monitoringAnimals = [];
    monitoringRoster = [];
    farmer.value = '';
    populateMonitoringFarmers();
    renderMonitoringRows();
    if (!request) return;

    try {
        const response = await fetch(monitoringBaseUrl + '/' + encodeURIComponent(id) + '/animals', {
            headers: {Accept: 'application/json'},
            signal: request.signal,
        });
        if (!response.ok) throw new Error('Could not load animals for this cooperative.');
        const data = await response.json();
        if (monitoringAnimalsRequest !== request) return;
        monitoringRoster = data;
        populateMonitoringFarmers();
    } catch (exception) {
        if (monitoringAnimalsRequest !== request) return;
        error.textContent = exception.message;
        error.classList.remove('hidden');
    } finally {
        if (monitoringAnimalsRequest === request) {
            monitoringAnimalsRequest = null;
            loading.classList.add('hidden');
            renderMonitoringRows();
        }
    }
});
document.getElementById('monitoring-farmer-filter').addEventListener('input', renderMonitoringRows);
document.getElementById('monitoring-form').addEventListener('submit', (event) => {
    if (monitoringSubmitting) {
        event.preventDefault();
        return;
    }

    const error = document.getElementById('monitoring-error');
    if (!monitoringAnimals.some(row => row?.carabao_id) || monitoringAnimals.some(row => row && !row.carabao_id)) {
        event.preventDefault();
        error.textContent = 'Select a live animal for each report row.';
        error.classList.remove('hidden');
        error.scrollIntoView({block: 'nearest'});
        return;
    }

    error.classList.add('hidden');
    monitoringSubmitting = true;
    const button = document.getElementById('monitoring-submit-button');
    button.disabled = true;
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Saving…';
});
document.getElementById('monitoring-animal-rows').addEventListener('input', (event) => {
    const field = event.target.name.match(/^rows\[(\d+)\]\[(remarks|body_condition_score)\]$/);
    if (field) monitoringAnimals[Number(field[1])][field[2] === 'remarks' ? '_remarks' : '_bcs'] = event.target.value;
});
</script>
@endpush
