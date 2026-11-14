{{-- resources/views/modules/index.blade.php --}}

@extends('layouts.admin')

@section('title', $moduleTitle ?? ucfirst(str_replace('-', ' ', $module ?? 'Module')))

@section('content')

@php
    $pageTitle = $moduleTitle ?? ucfirst(str_replace('-', ' ', $module ?? 'Module'));
    $isPaginator = method_exists($rows, 'total');
    $totalRecords = $isPaginator ? $rows->total() : $rows->count();
    $currentPageRows = $rows->count();
    $totalFields = count($columns ?? []) + (($module ?? null) === 'bcs' ? 1 : 0);
    $currentPage = method_exists($rows, 'currentPage') ? $rows->currentPage() : 1;
    $lastPage = method_exists($rows, 'lastPage') ? $rows->lastPage() : 1;
    $firstItem = method_exists($rows, 'firstItem') ? ($rows->firstItem() ?? 0) : ($currentPageRows ? 1 : 0);
    $lastItem = method_exists($rows, 'lastItem') ? ($rows->lastItem() ?? 0) : $currentPageRows;

    $searchValue = request('search', '');

    $moduleDescriptions = [
        'bcs' => 'Review dated body condition score measurements and their animal registry references.',
        'milk' => 'Review milk production by cooperative, farmer, actual collection date, and AM/PM period.',
        'fhpr' => 'Manage and monitor farm health and production records.',
        'mtd' => 'Manage milk test day records and production information.',
        'exits' => 'Monitor animal exits, transfers, and disposal records.',
        'meat' => 'Manage meat production and related records.',
        'manure' => 'Manage manure production and utilization records.',
    ];

    $pageDescription = $moduleDescriptions[$module ?? '']
        ?? 'Manage, review, and maintain your CBED records.';

    $summaryCards = [
        ['title' => 'Total Records', 'value' => number_format($totalRecords), 'description' => 'All saved records', 'icon' => 'fa-database'],
        ['title' => 'Total Fields', 'value' => number_format($totalFields), 'description' => 'Available data columns', 'icon' => 'fa-table-columns'],
        ['title' => 'Rows on This Page', 'value' => number_format($currentPageRows), 'description' => 'Currently displayed', 'icon' => 'fa-list-check'],
        ['title' => 'Current Page', 'value' => $currentPage, 'description' => 'Pagination position', 'icon' => 'fa-file-lines'],
    ];
@endphp

<div class="mating-dashboard management-page min-w-0 bg-slate-50 text-slate-800">

    {{-- PAGE HEADER --}}
    <div class="pcc-mating-hero mb-7 flex flex-col gap-5 rounded-2xl p-5 text-white shadow-xl sm:p-6 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-center gap-4">
            <div class="pcc-brand-mark hidden sm:flex" aria-hidden="true">
                <i class="fa-solid fa-cow"></i><span><i class="fa-solid fa-dna"></i></span>
            </div>
            <div>
                <div class="mb-2 flex flex-wrap items-center gap-2 text-sm text-blue-100">
                    <i class="fa-solid fa-house"></i><span>/</span>
                    <span>CBED Data System</span><span>/</span>
                    <b class="text-white">{{ $pageTitle }}</b>
                </div>
                <h1 class="text-2xl font-bold text-white sm:text-3xl">{{ $pageTitle }}</h1>
                <p class="mt-1 text-sm text-blue-100">{{ $pageDescription }}</p>
                <span class="pcc-program-tag">
                    <i class="fa-regular fa-calendar"></i> {{ now()->format('F d, Y') }}
                </span>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('modules.export', $module) }}" class="action-secondary">
                <i class="fa-solid fa-file-export"></i> Export CSV
            </a>
            @unless($module === 'fhpr')
                <a href="{{ route('modules.template', $module) }}" class="action-primary">
                    <i class="fa-solid fa-download"></i> Download Template
                </a>
            @endunless
        </div>
    </div>

    {{-- CONNECTION STATUS --}}
    <div class="mb-6 flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
        <i class="fa-solid fa-circle-check mt-0.5"></i>
        <div>
            <b>Database connected</b>
            <p class="mt-1">Records displayed below are retrieved from the CBED database.</p>
        </div>
    </div>

    @if($module === 'bcs')
        <section class="mb-7 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-[#12345a]">Existing BCS score values</h2>
            <p class="mt-1 text-sm text-slate-500">These choices come from distinct values already stored in <code>bcs_records.body_condition_score</code>. The existing table has no score-classification or interpretation columns.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                @forelse($bcsScores as $score)
                    <span class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-bold text-blue-900">{{ $score }}</span>
                @empty
                    <span class="text-sm text-slate-500">No BCS scores have been recorded yet.</span>
                @endforelse
            </div>
        </section>
    @endif

    {{-- SUCCESS / ERROR MESSAGES --}}
    @if(session('success'))
        <div class="mb-6 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
            <i class="fa-solid fa-circle-check mt-0.5"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="mb-2 font-bold">Please check the following:</p>
            <ul class="list-inside list-disc space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($module === 'milk')
        <section class="mb-6">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                <div><h2 class="text-xl font-bold text-[#12345a]">Annual Milk Production</h2><p class="mt-1 text-sm text-slate-500">Yearly totals from dated milk production records; select a year to view its quarters.</p></div>
                <span class="rounded-lg bg-blue-50 px-3 py-2 text-sm font-bold text-blue-900">Selected year: {{ $selectedYear }}</span>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @forelse($milkYearlySummaries as $yearSummary)
                    <a href="{{ request()->fullUrlWithQuery(['year' => $yearSummary->collected_year, 'quarter' => null]) }}"
                       class="rounded-xl border bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md {{ (int)$yearSummary->collected_year === $selectedYear ? 'border-blue-500 ring-2 ring-blue-100' : 'border-slate-200' }}">
                        <p class="text-sm font-semibold text-slate-500">{{ $yearSummary->collected_year }}</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format((float)$yearSummary->total_production, 2) }} <span class="text-sm font-semibold text-slate-500">kg produced</span></p>
                        <div class="mt-3 space-y-1 text-sm text-slate-600">
                            <p>Milk traded: <b class="text-slate-800">{{ number_format((float)$yearSummary->total_traded, 2) }} kg</b></p>
                            <p>Production value: <b class="text-slate-800">₱{{ number_format((float)$yearSummary->total_value, 2) }}</b></p>
                        </div>
                    </a>
                @empty
                    <p class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500">No dated milk production records are available for the active filters.</p>
                @endforelse
            </div>
        </section>

        <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div><h2 class="text-xl font-bold text-[#12345a]">Quarterly Production — {{ $selectedYear }}</h2><p class="mt-1 text-sm text-slate-500">Totals use each record's actual collection date.</p></div>
                <span class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700">Selected year: {{ $selectedYear }}</span>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($milkQuarterSummaries as $quarter)
                    <a href="{{ request()->fullUrlWithQuery(['year' => $selectedYear, 'quarter' => $quarter['name']]) }}"
                       class="rounded-xl border p-4 transition hover:shadow-md {{ $quarter['selected'] ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-100' : 'border-slate-200 bg-white' }}">
                        <div class="flex items-start justify-between gap-2"><h3 class="text-lg font-bold text-slate-900">{{ $quarter['name'] }}</h3><span class="text-xs font-medium text-slate-500">{{ $quarter['months'] }}</span></div>
                        <p class="mt-3 text-2xl font-bold text-slate-900">{{ number_format($quarter['total_production'], 2) }} <span class="text-sm font-semibold text-slate-500">kg</span></p>
                        <div class="mt-3 space-y-1 text-sm text-slate-600">
                            <p>Milk traded: <b class="text-slate-800">{{ number_format($quarter['total_traded'], 2) }} kg</b></p>
                            <p>Production value: <b class="text-slate-800">₱{{ number_format($quarter['total_value'], 2) }}</b></p>
                            <p>Collection records: <b class="text-slate-800">{{ number_format($quarter['record_count']) }}</b></p>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="font-bold text-slate-800">Production by Quarter (kg)</h3>
                    <span class="text-xs text-slate-500">Q1–Q4 · {{ $selectedYear }}</span>
                </div>
                <div class="grid h-52 grid-cols-4 items-end gap-3 border-b border-slate-300 px-2">
                    @foreach($milkQuarterSummaries as $quarter)
                        <div class="flex h-full flex-col items-center justify-end">
                            <span class="mb-2 text-center text-xs font-semibold tabular-nums text-slate-700">{{ number_format($quarter['total_production'], 2) }}</span>
                            <div class="flex h-36 w-full items-end justify-center">
                                <div class="w-10 rounded-t-md {{ $quarter['selected'] ? 'bg-blue-800' : 'bg-blue-600' }} transition-all"
                                     style="height: {{ $maxQuarterProduction > 0 ? max(4, ($quarter['total_production'] / $maxQuarterProduction) * 144) : 4 }}px"
                                     title="{{ $quarter['name'] }}: {{ number_format($quarter['total_production'], 2) }} kg"></div>
                            </div>
                            <span class="mb-2 mt-2 text-sm font-bold text-slate-700">{{ $quarter['name'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- SUMMARY CARDS --}}
    @if($module === 'health')
        <div class="mb-7 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @php $healthCards=[['Total Animals Monitored',$healthSummary['animals']],['Total Visits / Monitored',$healthSummary['visits']],['Total Treatments',$healthSummary['treatments']]]; @endphp
            @foreach($healthCards as [$title,$value])<div class="pcc-stat-card rounded-xl bg-white p-5"><p class="text-sm text-slate-500">{{ $title }}</p><p class="mt-2 text-3xl font-bold">{{ number_format($value) }}</p></div>@endforeach
            @foreach($healthSummary['services'] as $service=>$count)<div class="pcc-stat-card rounded-xl bg-white p-5"><p class="text-sm text-slate-500">{{ $service }}</p><p class="mt-2 text-3xl font-bold">{{ number_format($count) }}</p><p class="mt-1 text-xs text-slate-500">Service entries</p></div>@endforeach
        </div>
    @elseif($module === 'milk')
    <div class="mb-7 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach([
            ['Total Milk Production', number_format($milkSummary['total_production'], 2).' kg', 'Filtered collection records', 'fa-droplet'],
            ['Total Milk Traded', number_format($milkSummary['total_traded'], 2).' kg', 'Filtered collection records', 'fa-truck-ramp-box'],
            ['Total Value of Milk Production', '₱'.number_format($milkSummary['total_value'], 2), 'Production × price per kilogram', 'fa-peso-sign'],
        ] as [$title, $value, $description, $icon])
            <div class="pcc-stat-card rounded-xl bg-white p-5">
                <div class="flex items-center justify-between gap-3">
                    <div><p class="text-sm text-slate-500">{{ $title }}</p><p class="mt-2 text-2xl font-bold">{{ $value }}</p><p class="mt-1 text-xs text-slate-500">{{ $description }}</p></div>
                    <div class="pcc-stat-icon flex h-12 w-12 items-center justify-center rounded-xl"><i class="fa-solid {{ $icon }} text-xl"></i></div>
                </div>
            </div>
        @endforeach
    </div>
    @else
    <div class="mb-7 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($summaryCards as $card)
            <div class="pcc-stat-card rounded-xl bg-white p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm text-slate-500">{{ $card['title'] }}</p>
                        <p class="mt-2 text-3xl font-bold">{{ $card['value'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $card['description'] }}</p>
                    </div>
                    <div class="pcc-stat-icon flex h-12 w-12 items-center justify-center rounded-xl">
                        <i class="fa-solid {{ $card['icon'] }} text-xl"></i>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    @endif

    {{-- RECORDS TABLE --}}
    <section class="pcc-records-panel overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        <div class="flex flex-col gap-3 border-b p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-[#12345a]">{{ $module === 'milk' ? 'Milk Production Records' : $pageTitle.' Records' }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $module === 'fhpr' ? 'View and search breeding summary records.' : 'View, search, import, and manage your records.' }}</p>
            </div>
            <div class="flex gap-2">
                @if($module === 'health')
                    <button type="button" onclick="openHealthEditor()" class="action-primary"><i class="fa-solid fa-plus"></i> Add Activity</button>
                @endif
                <span class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold">
                    {{ number_format($totalRecords) }} records
                </span>
                @if($searchValue !== '' || ($module === 'milk' && collect($milkFilters)->contains(fn($value)=>$value!=='')))
                    <a href="{{ url()->current() }}" class="action-secondary">Reset Filters</a>
                @endif
            </div>
        </div>

        {{-- SEARCH --}}
        <form method="GET" action="{{ url()->current() }}"
              class="grid grid-cols-1 gap-3 border-b bg-slate-50 p-5 sm:grid-cols-2 xl:grid-cols-6">
            <div class="sm:col-span-2 xl:col-span-4">
                <label for="search" class="filter-label">Search</label>
                <input id="search" type="text" name="search" value="{{ $searchValue }}"
                       class="filter-input" placeholder="{{ $module === 'milk' ? 'Cooperative or farmer name...' : 'Search records...' }}">
            </div>
            @if($module === 'milk')
                <div>
                    <label for="cooperative_id" class="filter-label">Cooperative</label>
                    <select id="cooperative_id" name="cooperative_id" class="filter-input">
                        <option value="">All Cooperatives</option>
                        @foreach($milkCooperatives as $cooperative)
                            <option value="{{ $cooperative->id }}" @selected($milkFilters['cooperative_id'] === (string)$cooperative->id)>{{ $cooperative->cooperative_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="farmer" class="filter-label">Farmer</label>
                    <input id="farmer" type="search" name="farmer" value="{{ $milkFilters['farmer'] }}" class="filter-input" placeholder="Filter by farmer">
                </div>
                <div>
                    <label for="year" class="filter-label">Year Collected</label>
                    <select id="year" name="year" class="filter-input">
                        <option value="">All Years</option>
                        @foreach($milkYears as $year)
                            <option value="{{ $year }}" @selected($milkFilters['year'] === (string)$year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="quarter" class="filter-label">Quarter</label>
                    <select id="quarter" name="quarter" class="filter-input">
                        <option value="">All Quarters</option>
                        @foreach(['Q1','Q2','Q3','Q4'] as $quarter)
                            <option value="{{ $quarter }}" @selected($milkFilters['quarter'] === $quarter)>{{ $quarter }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="date" class="filter-label">Actual Collection Date</label>
                    <input id="date" type="date" name="date" value="{{ $milkFilters['date'] }}" class="filter-input">
                </div>
                <div>
                    <label for="period" class="filter-label">AM / PM</label>
                    <select id="period" name="period" class="filter-input">
                        <option value="">All Periods</option>
                        <option value="AM" @selected($milkFilters['period'] === 'AM')>AM</option>
                        <option value="PM" @selected($milkFilters['period'] === 'PM')>PM</option>
                    </select>
                </div>
            @endif
            <div class="flex items-end gap-2 xl:col-span-2">
                <button type="submit" class="action-primary">
                    <i class="fa-solid fa-magnifying-glass"></i> Search
                </button>
                @if($searchValue !== '' || ($module === 'milk' && collect($milkFilters)->except('search')->contains(fn($value)=>$value!=='')))
                    <a href="{{ url()->current() }}" class="action-secondary">
                        <i class="fa-solid fa-xmark"></i> Clear
                    </a>
                @endif
            </div>
        </form>

        @unless($module === 'fhpr')
        {{-- IMPORT --}}
        <div class="border-b bg-slate-50 p-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <h3 class="font-bold text-slate-800">
                        <i class="fa-solid fa-file-csv mr-2 text-emerald-600"></i>Import Records
                    </h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Upload your completed CSV file to import records into the database.
                    </p>
                </div>

                <form method="POST" action="{{ route('modules.import', $module) }}"
                      enctype="multipart/form-data"
                      class="flex w-full flex-col gap-2 sm:flex-row xl:w-auto">
                    @csrf
                    <input type="file" name="file" accept=".csv,.txt" required
                           class="block w-full max-w-sm cursor-pointer rounded-lg border border-slate-300 bg-white text-sm text-slate-600 file:mr-3 file:cursor-pointer file:border-0 file:bg-slate-100 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200">
                    <button type="submit" class="action-primary shrink-0">
                        <i class="fa-solid fa-upload"></i> Import CSV
                    </button>
                </form>
            </div>
        </div>
        @endunless

        {{-- TABLE --}}
        <div class="overflow-x-auto">
            <table class="w-full min-w-max text-left text-sm">
                <thead class="bg-slate-100 text-xs uppercase text-slate-600">
                    <tr>
                        <th class="th whitespace-nowrap">No.</th>
                        @foreach($columns as $column)
                            @php
                                $columnKey = is_array($column)
                                    ? ($column['key'] ?? $column['name'] ?? '')
                                    : $column;

                                $columnLabel = is_array($column)
                                    ? ($column['label'] ?? $columnKey)
                                    : $column;

                                $columnLabel = match ($columnKey) {
                                    'cooperative_name_display' => 'Cooperative Name',
                                    'year_collected' => 'Year Collected',
                                    'collection_period' => 'AM / PM',
                                    'total_liters' => 'Total Milk Production (kg)',
                                    'number_of_cows' => 'Number of Cows',
                                    'price_per_kg' => 'Price per Kilogram (₱)',
                                    'milk_traded' => 'Milk Traded (kg)',
                                    'value_total' => 'Value of Total Milk Production (₱)',
                                    'animal_identifier' => 'Registry ID / Animal ID',
                                    'cooperative_name_display' => 'Cooperative Name',
                                    'registry_id' => $module === 'health' ? 'Animal Registry ID' : 'Registry ID',
                                    'tag_number' => 'Tag Number',
                                    'cooperative_name' => in_array($module,['health','bcs'],true) ? 'Cooperative Name' : 'Cooperative',
                                    'breed' => 'Breed',
                                    'date_of_birth' => 'Date of Birth',
                                    'farmer_name' => in_array($module, ['bcs','milk'], true) ? 'Farmer Name' : 'Farmer',
                                    'monitoring_date' => $module === 'health' ? 'Monitoring Date' : 'Date',
                                    'services' => 'Type of Service',
                                    'dosage_ml' => 'Dosage (mL)',
                                    'no_of_visits_monitored' => 'No. of Visits / Monitored',
                                    'no_of_treatments' => 'No. of Treatments',
                                    'actions' => 'Actions',
                                    'body_condition_score' => $module === 'bcs' ? 'BCS Remain' : 'Body Condition Score (BCS)',
                                    'record_date' => $module === 'milk' ? 'Actual Date of Collection' : 'BCS Record Date',
                                    'source_data' => 'Source Worksheet Data',
                                    'carabao_id' => 'Carabao ID',
                                    'cooperative_id' => 'Cooperative ID',
                                    'monitoring_report_detail_id' => 'Monitoring Detail ID',
                                    default => ucwords(str_replace(['_', '-'], ' ', $columnLabel)),
                                };
                            @endphp
                            <th class="th whitespace-nowrap">{{ $columnLabel }}</th>
                        @endforeach
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        <tr class="hover:bg-blue-50/50">
                            <td class="td text-slate-500">
                                {{ method_exists($rows, 'firstItem') ? $rows->firstItem() + $loop->index : $loop->iteration }}
                            </td>

                            @foreach($columns as $column)
                                @php
                                    $columnKey = is_array($column)
                                        ? ($column['key'] ?? $column['name'] ?? '')
                                        : $column;

                                    $value = data_get($row, $columnKey);
                                @endphp

                                <td class="td max-w-xs {{ $module === 'health' && in_array($columnKey,['services','dosage_ml','remarks','actions'],true) ? 'whitespace-normal' : 'whitespace-nowrap' }}">
                                    @if($module === 'health' && $columnKey === 'services')
                                        @forelse($row->services as $service)<div class="mb-1 last:mb-0">{{ $service['type'] === 'Other Services' ? ($service['other_service_name'] ?: 'Other Services') : $service['type'] }}</div>@empty<span class="text-slate-400">—</span>@endforelse
                                    @elseif($module === 'health' && $columnKey === 'dosage_ml')
                                        @forelse($row->services as $service)<div class="mb-1 last:mb-0">{{ $service['dosage_display'] ?: '—' }}</div>@empty<span class="text-slate-400">—</span>@endforelse
                                    @elseif($module === 'health' && $columnKey === 'actions')
                                        <div class="flex gap-2"><button type="button" class="rounded border px-2 py-1 text-slate-700 hover:bg-slate-50" onclick="openHealthEditor({{ $row->id }},true)">View</button><button type="button" class="rounded border px-2 py-1 text-blue-700 hover:bg-blue-50" onclick="openHealthEditor({{ $row->id }})">Edit</button><button type="button" class="rounded border px-2 py-1 text-red-700 hover:bg-red-50" onclick="deleteHealthActivity({{ $row->id }})">Delete</button></div>
                                    @elseif($module === 'health' && $columnKey === 'remarks')
                                        @foreach($row->services as $service)<div class="mb-1 last:mb-0">{{ $service['remarks'] ?: '—' }}</div>@endforeach
                                        @if($row->remarks)<div class="mt-1 border-t pt-1 text-slate-600">{{ $row->remarks }}</div>@endif
                                    @elseif($module === 'milk' && $columnKey === 'record_date')
                                        {{ $value ? \Illuminate\Support\Carbon::parse($value)->format('F j, Y') : '—' }}
                                    @elseif($module === 'milk' && in_array($columnKey, ['price_per_kg','value_total'], true))
                                        {{ $value !== null ? '₱'.number_format((float)$value, 2) : '—' }}
                                    @elseif($module === 'milk' && in_array($columnKey, ['total_liters','milk_traded'], true))
                                        {{ $value !== null ? number_format((float)$value, 2) : '—' }}
                                    @elseif(is_null($value) || $value === '')
                                        <span class="text-slate-400">—</span>
                                    @elseif(is_bool($value))
                                        <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $value ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                            {{ $value ? 'Yes' : 'No' }}
                                        </span>
                                    @elseif(is_array($value) || is_object($value))
                                        {{ json_encode($value) }}
                                    @else
                                        {{ $value }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) + 1 }}" class="px-5 py-12 text-center text-slate-500">
                                {{ $searchValue !== '' ? 'No records found for "' . $searchValue . '".' : 'No records found.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINATION --}}
        <div class="flex flex-col justify-between gap-3 border-t px-5 py-4 sm:flex-row sm:items-center">
            <p class="text-sm text-slate-500">
                Showing <b>{{ $firstItem }}</b>–<b>{{ $lastItem }}</b>
                of <b>{{ number_format($totalRecords) }}</b> records
            </p>

            @if($isPaginator)
                @php
                    $prevUrl = $rows->previousPageUrl();
                    $nextUrl = $rows->nextPageUrl();
                    $qs = request()->except('page');
                    if ($prevUrl) { $prevUrl = $rows->appends($qs)->previousPageUrl(); }
                    if ($nextUrl) { $nextUrl = $rows->appends($qs)->nextPageUrl(); }
                @endphp
                <div class="flex items-center gap-2">
                    @if($prevUrl)
                        <a href="{{ $prevUrl }}" class="page-btn">Previous</a>
                    @else
                        <span class="page-btn" aria-disabled="true" style="opacity:.4;cursor:not-allowed">Previous</span>
                    @endif

                    <span class="text-sm">Page <b>{{ $currentPage }}</b> of <b>{{ $lastPage }}</b></span>

                    @if($nextUrl)
                        <a href="{{ $nextUrl }}" class="page-btn">Next</a>
                    @else
                        <span class="page-btn" aria-disabled="true" style="opacity:.4;cursor:not-allowed">Next</span>
                    @endif
                </div>
            @endif
        </div>
    </section>

    @if($module === 'health')
        <div id="health-editor" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="health-editor-title">
            <div class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="sticky top-0 flex items-center justify-between border-b bg-white px-6 py-4"><div><h2 id="health-editor-title" class="text-xl font-bold text-[#12345a]">Add Health and Nutrition Activity</h2><p class="text-sm text-slate-500">One activity can include multiple services.</p></div><button type="button" onclick="closeHealthEditor()" class="rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100">✕</button></div>
                <form id="health-editor-form" class="space-y-5 p-6" onsubmit="saveHealthActivity(event)">
                    <input type="hidden" id="health-record-id">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-semibold">Animal Registry ID<select id="health-carabao-id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">Select animal</option>@foreach($healthAnimals as $animal)<option value="{{ $animal['id'] }}">{{ $animal['registry_id'] ?: 'N/A' }} · Tag {{ $animal['tag_number'] ?: '—' }} · {{ $animal['cooperative_name'] ?: 'No cooperative' }}</option>@endforeach</select></label>
                        <label class="block text-sm font-semibold">Monitoring Date<input id="health-monitoring-date" type="date" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                        <label class="block text-sm font-semibold">No. of Visits / Monitored<input id="health-visits" type="number" min="0" step="1" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                        <label class="block text-sm font-semibold">No. of Treatments<input id="health-treatments" type="number" min="0" step="1" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                    </div>
                    <datalist id="health-service-options"><option value="Deworming"><option value="Vitamins"><option value="Vaccination"><option value="Uterine Flushing"><option value="Fecalysis"><option value="Ear Tagging"><option value="Recruitment"><option value="Hands-on Milking"><option value="Forage Demo"></datalist>
                    <div class="rounded-xl border border-slate-200 p-4"><div class="mb-3 flex items-center justify-between gap-2"><div><h3 class="font-bold text-slate-800">Type of Service</h3><p class="text-xs text-slate-500">Each service has its own optional dosage and remarks. Add custom service types as needed.</p></div><button id="health-add-service-button" type="button" onclick="addHealthServiceRow()" class="rounded-lg bg-blue-700 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-800"><i class="fa-solid fa-plus mr-1"></i>Add Service</button></div><div id="health-service-rows" class="space-y-3"></div></div>
                    <label class="block text-sm font-semibold">Activity Remarks<textarea id="health-activity-remarks" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></textarea></label>
                    <div class="flex justify-end gap-2"><button type="button" onclick="closeHealthEditor()" class="rounded-lg border px-4 py-2">Close</button><button id="health-save-button" type="submit" class="action-primary bg-blue-700 text-white">Save Activity</button></div>
                </form>
            </div>
        </div>
    @endif

    <footer class="py-5 text-center text-xs text-slate-400">CBED Data System · {{ $pageTitle }}</footer>

</div>

@endsection

@if($module === 'health')
@push('scripts')
<script>
const healthRecordsUrl=@json(url('/admin/health-records'));
let healthServiceRowKey=0;
function closeHealthEditor(){document.getElementById('health-editor').classList.add('hidden');document.getElementById('health-editor').classList.remove('flex');}
function addHealthServiceRow(service={}){const index=healthServiceRowKey++,row=document.createElement('div');row.className='health-service-row grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 md:grid-cols-[1.4fr_.7fr_1.4fr_auto]';row.innerHTML=`<label class="text-xs font-semibold">Service Type<input list="health-service-options" data-service="type" class="mt-1 w-full rounded border px-2 py-2 text-sm" placeholder="Choose or enter a type" required></label><label class="text-xs font-semibold">Dosage (mL)<input data-service="dosage_ml" type="number" min="0" step="0.01" class="mt-1 w-full rounded border px-2 py-2 text-sm" placeholder="Not applicable"></label><label class="text-xs font-semibold">Service Remarks<input data-service="remarks" maxlength="2000" class="mt-1 w-full rounded border px-2 py-2 text-sm"></label><button type="button" class="self-end rounded border border-red-200 px-3 py-2 text-sm text-red-700 hover:bg-red-50" aria-label="Remove service" onclick="this.closest('.health-service-row').remove()">Remove</button>`;row.querySelector('[data-service="type"]').value=service.type==='Other Services'?(service.other_service_name||'Other Services'):(service.type||'');row.querySelector('[data-service="dosage_ml"]').value=service.dosage_ml??'';row.querySelector('[data-service="remarks"]').value=service.remarks??'';document.getElementById('health-service-rows').appendChild(row);}
function openHealthEditor(id=null,readOnly=false){const form=document.getElementById('health-editor-form');form.reset();form.querySelectorAll('input,select,textarea,#health-service-rows button').forEach(control=>control.disabled=false);document.getElementById('health-record-id').value='';document.getElementById('health-service-rows').replaceChildren();document.getElementById('health-monitoring-date').value=new Date().toISOString().slice(0,10);document.getElementById('health-editor-title').textContent=!id?'Add Health and Nutrition Activity':readOnly?'View Health and Nutrition Activity':'Edit Health and Nutrition Activity';document.getElementById('health-save-button').hidden=readOnly;document.getElementById('health-add-service-button').hidden=readOnly;if(!id)addHealthServiceRow();const modal=document.getElementById('health-editor');modal.classList.remove('hidden');modal.classList.add('flex');if(id){fetch(`${healthRecordsUrl}/${id}`,{headers:{Accept:'application/json'}}).then(async response=>{const record=await response.json();if(!response.ok)throw new Error(record.message||'Could not load this activity.');document.getElementById('health-record-id').value=record.id;document.getElementById('health-carabao-id').value=record.carabao_id;document.getElementById('health-monitoring-date').value=String(record.monitoring_date||'').slice(0,10);document.getElementById('health-visits').value=record.no_of_visits_monitored??'';document.getElementById('health-treatments').value=record.no_of_treatments??'';document.getElementById('health-activity-remarks').value=record.remarks||'';document.getElementById('health-service-rows').replaceChildren();(record.services||[]).forEach(service=>addHealthServiceRow(service));if(!(record.services||[]).length)addHealthServiceRow();if(readOnly)form.querySelectorAll('input,select,textarea,#health-service-rows button').forEach(control=>control.disabled=true);}).catch(error=>{alert(error.message);closeHealthEditor();});}else if(readOnly){form.querySelectorAll('input,select,textarea,#health-service-rows button').forEach(control=>control.disabled=true);}}
async function saveHealthActivity(event){event.preventDefault();const services=[...document.querySelectorAll('#health-service-rows .health-service-row')].map(row=>Object.fromEntries([...row.querySelectorAll('[data-service]')].map(input=>[input.dataset.service,input.value.trim()]))).filter(service=>service.type);if(!services.length){alert('Add at least one service.');return;}const payload={carabao_id:Number(document.getElementById('health-carabao-id').value),monitoring_date:document.getElementById('health-monitoring-date').value,no_of_visits_monitored:Number(document.getElementById('health-visits').value),no_of_treatments:Number(document.getElementById('health-treatments').value),remarks:document.getElementById('health-activity-remarks').value,services};const id=document.getElementById('health-record-id').value,token=document.querySelector('meta[name="csrf-token"]')?.content;const response=await fetch(id?`${healthRecordsUrl}/${id}`:healthRecordsUrl,{method:id?'PUT':'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':token},body:JSON.stringify(payload)});const result=await response.json().catch(()=>({}));if(!response.ok){alert(result.message||'Could not save the activity.');return;}location.reload();}
async function deleteHealthActivity(id){if(!confirm('Delete this Health and Nutrition activity and its service entries?'))return;const token=document.querySelector('meta[name="csrf-token"]')?.content;const response=await fetch(`${healthRecordsUrl}/${id}`,{method:'DELETE',headers:{Accept:'application/json','X-CSRF-TOKEN':token}});const result=await response.json().catch(()=>({}));if(!response.ok){alert(result.message||'Could not delete the activity.');return;}location.reload();}
</script>
@endpush
@endif

@push('styles')
<style>
.filter-input{width:100%;border:1px solid #cbd5e1;border-radius:.5rem;background:#fff;padding:.625rem .75rem;font-size:.875rem;color:#1e293b;outline:none}
.filter-input:focus{border-color:#1d4ed8;box-shadow:0 0 0 2px #dbeafe}
.filter-label{display:block;margin-bottom:.375rem;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
.action-primary,.action-secondary{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;border-radius:.5rem;padding:.625rem 1rem;font-size:.875rem;font-weight:600}
.action-primary{background:#0b1f3a;color:#fff}.action-primary:hover{background:#122b4d}
.action-secondary{border:1px solid #cbd5e1;background:#fff;color:#334155}.action-secondary:hover{background:#f1f5f9}
.th{padding:1rem}.td{padding:1rem;vertical-align:top}
.page-btn{display:inline-flex;border:1px solid #cbd5e1;border-radius:.5rem;padding:.5rem .75rem;font-size:.875rem;font-weight:600;color:#334155;background:#fff}
a.page-btn:hover{background:#f1f5f9}
.pcc-mating-hero{position:relative;isolation:isolate;overflow:hidden;background:radial-gradient(circle at 90% 18%,rgba(255,196,56,.25),transparent 19%),linear-gradient(118deg,#082d50 0%,#075785 55%,#0786a5 100%);box-shadow:0 16px 32px rgba(7,62,99,.2)}
.pcc-mating-hero::after{content:"";position:absolute;z-index:-1;width:19rem;height:19rem;right:11%;top:-14rem;border:1px solid rgba(255,214,92,.45);border-radius:50%;box-shadow:0 0 0 26px rgba(255,214,92,.08),0 0 0 54px rgba(255,255,255,.045)}
.pcc-brand-mark{position:relative;flex:none;width:4.35rem;height:4.35rem;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.34);border-radius:1.35rem;background:linear-gradient(145deg,rgba(255,255,255,.22),rgba(255,255,255,.07));color:#ffd05b;font-size:2rem;box-shadow:0 10px 22px rgba(0,28,52,.2)}
.pcc-brand-mark span{position:absolute;right:-.45rem;bottom:-.4rem;display:grid;width:1.65rem;height:1.65rem;place-items:center;border:2px solid #075785;border-radius:999px;background:#ffd05b;color:#12345a;font-size:.75rem}
.pcc-program-tag{display:inline-flex;align-items:center;gap:.4rem;margin-top:.7rem;border:1px solid rgba(255,213,91,.35);border-radius:999px;background:rgba(4,37,68,.24);padding:.35rem .7rem;color:#ffe08a;font-size:.7rem;font-weight:700;letter-spacing:.035em}
.pcc-mating-hero .action-secondary{border-color:rgba(255,255,255,.28);background:rgba(255,255,255,.1);color:#fff;backdrop-filter:blur(8px)}
.pcc-mating-hero .action-secondary:hover{background:rgba(255,255,255,.2)}
.pcc-mating-hero .action-primary{background:#ffd05b;color:#12345a;box-shadow:0 7px 16px rgba(4,37,68,.2)}
.pcc-mating-hero .action-primary:hover{background:#ffdc7d}
.pcc-stat-card{position:relative;overflow:hidden;border:1px solid #dce6ed;border-top:4px solid #0b5e8b;border-radius:1rem;box-shadow:0 5px 18px rgba(15,45,67,.06);transition:transform .16s ease,box-shadow .16s ease}
.pcc-stat-card:hover{transform:translateY(-3px);box-shadow:0 14px 28px rgba(7,62,99,.13)}
.pcc-stat-card:nth-child(2){border-top-color:#e87523}.pcc-stat-card:nth-child(3){border-top-color:#25835b}.pcc-stat-card:nth-child(4){border-top-color:#0786a5}
.pcc-stat-icon{background:linear-gradient(145deg,#082d50,#0b6d9b);color:#fff;border-radius:1rem;box-shadow:0 8px 18px rgba(7,62,99,.2)}
.pcc-stat-card:nth-child(2) .pcc-stat-icon{background:linear-gradient(145deg,#a94b0b,#f28a32)}.pcc-stat-card:nth-child(3) .pcc-stat-icon{background:linear-gradient(145deg,#176342,#38a36f)}.pcc-stat-card:nth-child(4) .pcc-stat-icon{background:linear-gradient(145deg,#075785,#0798b6)}
.pcc-records-panel{border-color:#dce6ed;box-shadow:0 7px 24px rgba(15,45,67,.06)}
.pcc-records-panel>div:first-child{border-bottom-color:#e3ebf0}
.pcc-records-panel thead{background:#103c60!important;color:#fff!important;border-bottom:3px solid #e8b22f}
.pcc-records-panel .th{color:#fff!important}
.pcc-records-panel .filter-input:focus{border-color:#0786a5;box-shadow:0 0 0 3px rgba(7,134,165,.14)}
.pcc-records-panel tbody tr{transition:background-color .14s ease}.pcc-records-panel tbody tr:hover{background:#f0f8fb}
</style>
@endpush
