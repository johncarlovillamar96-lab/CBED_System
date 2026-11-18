{{-- resources/views/modules/index.blade.php --}}

@extends('layouts.admin')

@section('title', $moduleTitle ?? ucfirst(str_replace('-', ' ', $module ?? 'Module')))
@section('dataSource', 'database')

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

    $searchValue = request('search', in_array($module, ['health', 'bcs'], true) ? request('q', '') : '');
    $isProduct = in_array($module, ['meat', 'manure'], true);
    $productLabel = ucfirst($module);
    $detailedSearch = $isProduct || in_array($module, ['health', 'bcs', 'mtd', 'exits'], true);

    $moduleDescriptions = [
        'bcs' => 'Review dated body condition score measurements and their animal registry references.',
        'milk' => 'Review milk production by cooperative, farmer, actual collection date, and AM/PM period.',
        'fhpr' => 'Manage and monitor farm health and production records.',
        'mtd' => 'Manage milk test day records and production information.',
        'exits' => 'Monitor animal exits, transfers, and disposal records.',
        'meat' => 'Manage meat production and related records.',
        'manure' => 'Manage manure production and related records.',
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
    if ($module === 'bcs') {
        $summaryCards = [
            ['title' => 'BCS Records', 'value' => number_format($bcsSummary->records), 'description' => 'Records matching current filters', 'icon' => 'fa-clipboard-list'],
            ['title' => 'Animals Scored', 'value' => number_format($bcsSummary->animals), 'description' => 'Unique animals linked to records', 'icon' => 'fa-cow'],
            ['title' => 'Average BCS', 'value' => $bcsSummary->average_score === null ? '—' : number_format((float) $bcsSummary->average_score, 2), 'description' => 'Average across recorded scores', 'icon' => 'fa-chart-simple'],
            ['title' => 'Latest Record Date', 'value' => $bcsSummary->latest_date ? \Illuminate\Support\Carbon::parse($bcsSummary->latest_date)->format('d-M-y') : '—', 'description' => 'Most recent matching record', 'icon' => 'fa-calendar-check'],
        ];
    }
    if ($module === 'mtd') {
        $summaryCards = [
            ['title' => 'MTD Records', 'value' => number_format($mtdSummary->records), 'description' => 'Records matching current filters', 'icon' => 'fa-clipboard-list'],
            ['title' => 'Animals Tested', 'value' => number_format($mtdSummary->animals), 'description' => 'Unique animals linked to test records', 'icon' => 'fa-cow'],
            ['title' => 'Total Bulk', 'value' => number_format((float) $mtdSummary->bulk, 2).' kg', 'description' => 'Bulk readings recorded in kilograms', 'icon' => 'fa-droplet'],
        ];
    }
    if ($module === 'exits') {
        $summaryCards = [
            ['title' => 'Exit Records', 'value' => number_format($exitSummary->records), 'description' => 'Records matching current filters', 'icon' => 'fa-arrow-right-from-bracket'],
            ['title' => 'Transferred', 'value' => number_format((int) $exitSummary->transfers), 'description' => 'Animals recorded as transferred', 'icon' => 'fa-truck'],
            ['title' => 'Culled', 'value' => number_format((int) $exitSummary->culled), 'description' => 'Animals recorded as culled', 'icon' => 'fa-ban'],
            ['title' => 'Sold', 'value' => number_format((int) $exitSummary->sold), 'description' => 'Animals recorded as sold', 'icon' => 'fa-tag'],
            ['title' => 'Deaths', 'value' => number_format((int) $exitSummary->deaths), 'description' => 'Animals recorded as dead', 'icon' => 'fa-circle-minus'],
        ];
    }
@endphp

<div class="mating-dashboard management-page min-w-0 bg-slate-50 text-slate-800">

    {{-- PAGE HEADER --}}
    <x-admin-page-header :title="$pageTitle" :description="$pageDescription" :section="in_array($module, ['farmers', 'cooperatives'], true) ? 'Workspace' : 'Animal Management'" :tag="now()->format($module === 'fhpr' ? 'F j,Y' : 'F d, Y')" tag-icon="fa-regular fa-calendar">
        <a href="{{ route('modules.export', $module) }}" class="action-secondary">
            <i class="fa-solid fa-file-export"></i> {{ in_array($module, ['milk', 'bcs', 'meat', 'manure', 'mtd', 'exits'], true) ? 'Export' : 'Export CSV' }}
        </a>
        @unless($module === 'fhpr')
            <button type="button" onclick="document.getElementById('{{ $module }}-csv-import').showModal()" aria-haspopup="dialog" class="action-secondary">
                <i class="fa-solid fa-file-import" aria-hidden="true"></i> {{ $module === 'health' ? 'Import CSV' : 'Import' }}
            </button>
            <a href="{{ route('modules.template', $module) }}" class="{{ in_array($module, ['health', 'milk', 'meat', 'manure', 'mtd', 'exits'], true) ? 'action-secondary' : 'action-primary' }}">
                <i class="fa-solid fa-download" aria-hidden="true"></i> Download Template
            </a>
        @endunless
        @if($module === 'health')
            <button type="button" onclick="openHealthEditor()" class="cbed-add-button action-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add Activity</button>
        @elseif($module === 'milk')
            <button type="button" onclick="document.getElementById('milk-production-form').showModal()" class="cbed-add-button action-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add Milk Production</button>
        @elseif($isProduct)
            <button type="button" onclick="document.getElementById('{{ $module }}-production-form').showModal()" class="cbed-add-button action-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add {{ $productLabel }} Production</button>
        @elseif($module === 'mtd')
            <button type="button" onclick="document.getElementById('mtd-entry').showModal()" class="cbed-add-button action-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Bulk Add MTD Records</button>
        @elseif($module === 'exits')
            <button type="button" x-data @click="$dispatch('exit-record',{mode:'add'})" class="cbed-add-button action-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add Exit</button>
        @endif
    </x-admin-page-header>

    {{-- SUCCESS / ERROR MESSAGES --}}
    @if(session('success') && in_array($module, ['mtd', 'exits'], true))
        <div x-data="{ notice: @js(session('success')), noticeType: 'success' }" x-init="setTimeout(() => notice='', 4500)">
            @include('partials.action-toast')
        </div>
    @elseif(session('success'))
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

        </section>
    @endif

    @if($isProduct)
        <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-xl font-bold text-[#12345a]">Annual {{ $productLabel }} Production</h2>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @forelse($productYearly as $year)
                    <a href="{{ url('/'.$module).'?'.http_build_query(array_replace(request()->except(['page', 'quarter', 'date']), ['year' => $year->year])) }}" class="rounded-xl border p-4 hover:bg-sky-50 {{ (string) ($productFilters['year'] ?? '') === (string) $year->year ? 'border-blue-500 bg-blue-50' : 'border-slate-200' }}">
                        <span class="text-sm font-semibold text-slate-600">{{ $year->year }}</span><span class="mt-2 block text-xl font-bold">{{ number_format((float) $year->volume, 2) }} kg</span>
                    </a>
                @empty
                    <p class="text-sm text-slate-500">No dated {{ $module }} production records match these filters.</p>
                @endforelse
            </div>
        </section>
        <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-xl font-bold text-[#12345a]">Quarterly {{ $productLabel }} Production{{ isset($productFilters['year']) ? ' · '.$productFilters['year'] : ' · All Years' }}</h2>
            <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                @foreach(range(1, 4) as $quarter)
                    <a href="{{ url('/'.$module).'?'.http_build_query(array_replace(request()->except(['page', 'date']), ['quarter' => $quarter])) }}" class="rounded-xl border p-4 hover:bg-sky-50 {{ (int) ($productFilters['quarter'] ?? 0) === $quarter ? 'border-blue-500 bg-blue-50' : 'border-slate-200' }}">
                        <span class="text-sm font-semibold text-slate-600">Q{{ $quarter }}</span><span class="mt-2 block text-xl font-bold">{{ number_format((float) ($productQuarterly[$quarter] ?? 0), 2) }} kg</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- SUMMARY CARDS --}}
    @if($module === 'health')
        <div class="mb-5 grid gap-4 sm:grid-cols-3">
            @foreach([
                ['Animals Monitored', $healthSummary['animals'], 'fa-id-card'],
                ['Total Visits', $healthSummary['visits'], 'fa-calendar-check'],
                ['Total Treatments', $healthSummary['treatments'], 'fa-kit-medical'],
            ] as [$title, $value, $icon])
                <div class="pcc-stat-card flex items-center justify-between gap-3 rounded-xl bg-white p-5">
                    <div><p class="text-sm font-medium text-slate-600">{{ $title }}</p><p class="mt-2 text-3xl font-bold tabular-nums">{{ number_format($value) }}</p></div>
                    <div class="pcc-stat-icon flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" aria-hidden="true"><i class="fa-solid {{ $icon }}"></i></div>
                </div>
            @endforeach
        </div>
        <section class="pcc-records-panel mb-7 rounded-xl border border-slate-200 bg-white p-5" aria-labelledby="health-service-summary">
            <h2 id="health-service-summary" class="mb-4 font-semibold text-slate-900">Service Counts</h2>
            <dl class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-5">
                @foreach($healthSummary['services'] as $service => $count)
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <dt class="text-sm text-slate-600">{{ $service === 'Vitamins Services' ? 'Vitamins / Mineral Supplements' : $service }}</dt>
                        <dd class="mt-1 text-xl font-bold tabular-nums text-slate-900">{{ number_format($count) }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @elseif(in_array($module, ['milk', 'meat', 'manure'], true))
    <div class="mb-7 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($module === 'milk' ? [
            ['Total Milk Production', number_format($milkSummary['total_production'], 2).' kg', 'Filtered collection records', 'fa-droplet'],
            ['Total Milk Traded', number_format($milkSummary['total_traded'], 2).' kg', 'Filtered collection records', 'fa-truck-ramp-box'],
            ['Total Value of Milk Production', '₱'.number_format($milkSummary['total_value'], 2), 'Production × price per kilogram', 'fa-peso-sign'],
        ] : [
            ['Total '.$productLabel.' Production', number_format((float) $productSummary->volume, 2).' kg', 'Filtered production records', 'fa-weight-scale'],
            [$productLabel.' from Other Sources', number_format((float) $productSummary->other_volume, 2).' kg', 'Filtered production records', 'fa-truck-ramp-box'],
            ['Total Production Value', '₱'.number_format((float) $productSummary->value, 2), 'Recorded production value', 'fa-peso-sign'],
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
    <div class="mb-7 grid grid-cols-1 gap-4 sm:grid-cols-2 {{ $module === 'exits' ? 'xl:grid-cols-5' : 'xl:grid-cols-4' }}">
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
    <section @if($module === 'health') id="health-records-panel" @endif class="pcc-records-panel overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        <div class="flex flex-col gap-3 border-b p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-[#12345a]">{{ $module === 'milk' ? 'Milk Production Records' : $pageTitle.' Records' }}</h2>
                @unless(in_array($module, ['health', 'meat', 'manure', 'mtd', 'exits'], true))
                    <p class="mt-1 text-sm text-slate-500">{{ match($module) { 'fhpr' => 'View and search breeding summary records for female animals with a recorded classification.', 'milk' => 'Search and filter milk collection records.', default => 'View, search, import, and manage your records.' } }}</p>
                @endunless
            </div>
            <div class="flex gap-2">
                <span class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold">
                    {{ number_format($totalRecords) }} records
                </span>
                @if(!$detailedSearch && ($searchValue !== '' || ($module === 'milk' && collect($milkFilters)->contains(fn($value)=>$value!==''))))
                    <a href="{{ url()->current() }}" class="action-secondary">Reset Filters</a>
                @endif
            </div>
        </div>

        {{-- SEARCH --}}
        <form method="GET" action="{{ url()->current() }}"
              class="grid grid-cols-1 gap-3 border-b bg-slate-50 p-5 sm:grid-cols-2 {{ $detailedSearch ? 'xl:grid-cols-8' : 'xl:grid-cols-6' }}">
            @if($module === 'health')
                <input type="hidden" name="per_page" value="{{ $rows->perPage() }}">
            @endif
            <div class="sm:col-span-2 {{ $detailedSearch ? 'xl:col-span-2' : 'xl:col-span-4' }}">
                <label for="search" class="filter-label">{{ $detailedSearch ? 'Search Records' : 'Search' }}</label>
                <input id="search" type="search" name="search" value="{{ $searchValue }}"
                       class="filter-input" placeholder="{{ match($module) { 'health' => 'Farmer, registry ID, tag, or service', 'bcs', 'mtd' => 'Farmer, cooperative, animal ID, or tag', 'exits' => 'Farmer, animal, or exit reason', 'milk' => 'Cooperative or farmer name...', 'meat', 'manure' => 'Cooperative name or code', default => 'Search records...' } }}">
            </div>
            @if($module === 'health')
                <div>
                    <label for="health-cooperative-filter" class="filter-label">Cooperative</label>
                    <select id="health-cooperative-filter" name="cooperative_id" class="filter-input">
                        <option value="">All Cooperatives</option>
                        @foreach($healthCooperatives as $cooperative)
                            <option value="{{ $cooperative->id }}" @selected((string) ($healthFilters['cooperative_id'] ?? '') === (string) $cooperative->id)>{{ $cooperative->cooperative_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="health-breed-filter" class="filter-label">Breed</label>
                    <select id="health-breed-filter" name="breed" class="filter-input">
                        <option value="">All Breeds</option>
                        @foreach($healthBreeds as $breed)
                            <option value="{{ $breed }}" @selected(($healthFilters['breed'] ?? '') === $breed)>{{ $breed }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label for="health-date-from" class="filter-label">Monitoring Date From</label><input id="health-date-from" type="date" name="date_from" value="{{ $healthFilters['date_from'] ?? '' }}" class="filter-input"></div>
                <div><label for="health-date-to" class="filter-label">Monitoring Date To</label><input id="health-date-to" type="date" name="date_to" value="{{ $healthFilters['date_to'] ?? '' }}" class="filter-input"></div>
            @elseif($module === 'bcs')
                <div>
                    <label for="bcs-cooperative-filter" class="filter-label">Cooperative</label>
                    <select id="bcs-cooperative-filter" name="cooperative_id" class="filter-input">
                        <option value="">All Cooperatives</option>
                        @foreach($bcsCooperatives as $cooperative)
                            <option value="{{ $cooperative->id }}" @selected((string) ($bcsFilters['cooperative_id'] ?? '') === (string) $cooperative->id)>{{ $cooperative->cooperative_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="bcs-score-filter" class="filter-label">BCS Score</label>
                    <select id="bcs-score-filter" name="score" class="filter-input">
                        <option value="">All Scores</option>
                        @foreach($bcsScores as $score)
                            <option value="{{ $score }}" @selected(isset($bcsFilters['score']) && (float) $bcsFilters['score'] === (float) $score)>{{ $score }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label for="bcs-date-from" class="filter-label">Record Date From</label><input id="bcs-date-from" type="date" name="date_from" value="{{ $bcsFilters['date_from'] ?? '' }}" class="filter-input"></div>
                <div><label for="bcs-date-to" class="filter-label">Record Date To</label><input id="bcs-date-to" type="date" name="date_to" value="{{ $bcsFilters['date_to'] ?? '' }}" class="filter-input"></div>
            @elseif($isProduct)
                <div><label for="product-filter-cooperative" class="filter-label">Cooperative</label><select id="product-filter-cooperative" name="cooperative_id" class="filter-input"><option value="">All Cooperatives</option>@foreach($productCooperatives as $cooperative)<option value="{{ $cooperative->id }}" @selected((string) ($productFilters['cooperative_id'] ?? '') === (string) $cooperative->id)>{{ $cooperative->cooperative_name }}</option>@endforeach</select></div>
                <div><label for="product-filter-year" class="filter-label">Year</label><select id="product-filter-year" name="year" class="filter-input"><option value="">All Years</option>@foreach($productYearly as $year)<option value="{{ $year->year }}" @selected((string) ($productFilters['year'] ?? '') === (string) $year->year)>{{ $year->year }}</option>@endforeach</select></div>
                <div><label for="product-filter-quarter" class="filter-label">Quarter</label><select id="product-filter-quarter" name="quarter" class="filter-input"><option value="">All Quarters</option>@foreach(range(1,4) as $quarter)<option value="{{ $quarter }}" @selected((int) ($productFilters['quarter'] ?? 0) === $quarter)>Q{{ $quarter }}</option>@endforeach</select></div>
                <div><label for="product-filter-date" class="filter-label">Production Date</label><input id="product-filter-date" type="date" name="date" value="{{ $productFilters['date'] ?? '' }}" class="filter-input"></div>
            @elseif($module === 'mtd')
                <div><label for="mtd-filter-cooperative" class="filter-label">Cooperative</label><select id="mtd-filter-cooperative" name="cooperative_id" class="filter-input"><option value="">All Cooperatives</option>@foreach($mtdCooperatives as $cooperative)<option value="{{ $cooperative->id }}" @selected((string) ($filters['cooperative_id'] ?? '') === (string) $cooperative->id)>{{ $cooperative->cooperative_name }}</option>@endforeach</select></div>
                <div><label for="mtd-filter-period" class="filter-label">AM / PM</label><select id="mtd-filter-period" name="period" class="filter-input"><option value="">All Periods</option>@foreach(['AM','PM'] as $period)<option @selected(($filters['period'] ?? '') === $period)>{{ $period }}</option>@endforeach</select></div>
                <div><label for="mtd-filter-date" class="filter-label">Date Conducted</label><input id="mtd-filter-date" type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="filter-input"></div>
            @elseif($module === 'exits')
                <div><label for="exit-filter-cooperative" class="filter-label">Cooperative</label><select id="exit-filter-cooperative" name="cooperative_id" class="filter-input"><option value="">All Cooperatives</option>@foreach($exitCooperatives as $cooperative)<option value="{{ $cooperative->id }}" @selected((string) ($filters['cooperative_id'] ?? '') === (string) $cooperative->id)>{{ $cooperative->cooperative_name }}</option>@endforeach</select></div>
                <div><label for="exit-filter-nature" class="filter-label">Nature of Exit</label><select id="exit-filter-nature" name="nature" class="filter-input"><option value="">All Exit Types</option>@foreach($exitTypes as $type)<option value="{{ $type }}" @selected(($filters['nature'] ?? '') === $type)>{{ $type }}</option>@endforeach</select></div>
                <div><label for="exit-filter-date" class="filter-label">Exit Date</label><input id="exit-filter-date" type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="filter-input"></div>
            @endif
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
                    <select id="period" name="period" class="filter-input h-11">
                        <option value="">All Periods</option>
                        <option value="AM" @selected($milkFilters['period'] === 'AM')>AM</option>
                        <option value="PM" @selected($milkFilters['period'] === 'PM')>PM</option>
                    </select>
                </div>
            @endif
            <div class="flex items-end gap-2 xl:col-span-2">
                <button type="submit" class="action-primary {{ $module === 'milk' ? 'h-11' : '' }}">
                    <i class="fa-solid fa-magnifying-glass"></i> {{ $detailedSearch ? 'Apply Filters' : 'Search' }}
                </button>
                @if($module !== 'milk' && ($searchValue !== '' || ($module === 'health' && array_filter($healthFilters)) || ($module === 'bcs' && array_filter($bcsFilters, fn ($value) => $value !== null)) || ($isProduct && array_filter($productFilters)) || (in_array($module, ['mtd', 'exits'], true) && array_filter($filters))))
                    <a href="{{ url()->current() }}" class="action-secondary">
                        <i class="fa-solid fa-xmark"></i> Clear
                    </a>
                @endif
            </div>
        </form>

        {{-- TABLE --}}
        <div class="overflow-x-auto {{ $module === 'fhpr' ? 'fhpr-table-scroll' : '' }}" @if($module === 'fhpr') tabindex="0" role="region" aria-label="FHPR table with frozen animal details" @endif>
            <table class="w-full min-w-max text-left text-sm {{ $module === 'fhpr' ? 'fhpr-data-table' : '' }}">
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
                                    'cooperative_name_display' => $module === 'fhpr' ? 'Cooperative' : 'Cooperative Name',
                                    'year_collected' => 'Year Collected',
                                    'collection_period' => 'AM / PM',
                                    'bulk_kg' => 'Bulk (kg)',
                                    'bulk_result' => 'Bulk Result',
                                    'right_front' => 'RF - Right Front',
                                    'right_back' => 'RB - Right Back',
                                    'left_front' => 'LF - Left Front',
                                    'left_back' => 'LB - Left Back',
                                    'test_date' => 'Date Conducted',
                                    'total_liters' => 'Total Milk Production (kg)',
                                    'number_of_cows' => 'Number of Cows',
                                    'price_per_kg' => 'Price per Kilogram (₱)',
                                    'milk_traded' => 'Milk Traded (kg)',
                                    'value_total' => 'Value of Total Milk Production (₱)',
                                    'animal_identifier' => match($module) { 'fhpr' => 'Animal ID', 'mtd', 'exits' => 'Animal', default => 'Registry ID / Animal ID' },
                                    'reason_of_exit' => 'Reason for Exit',
                                    'entrustment' => 'Type of Entrustment',
                                    'disposed_to' => 'Sold, Transferred, or Disposed To',
                                    'disposal_province' => 'Disposal Province',
                                    'disposal_municipality' => 'Disposal Municipality',
                                    'disposal_barangay' => 'Disposal Barangay',
                                    'registry_id' => $module === 'health' ? 'Animal Registry ID' : 'Registry ID',
                                    'tag_number' => 'Tag Number',
                                    'cooperative_name' => in_array($module,['health','bcs'],true) ? 'Cooperative Name' : 'Cooperative',
                                    'breed' => 'Breed',
                                    'date_of_birth' => 'Date of Birth',
                                    'status_updated' => $module === 'fhpr' ? 'Classification' : 'Status Updated',
                                    'farmer_name' => in_array($module, ['bcs','milk','fhpr'], true) ? 'Farmer Name' : 'Farmer',
                                    'monitoring_date' => $module === 'health' ? 'Monitoring Date' : 'Date',
                                    'services' => 'Type of Service',
                                    'dosage_ml' => 'Dosage (mL)',
                                    'actual_date' => 'Production Date',
                                    'reported_date' => 'Reported Date',
                                    'volume_kg' => $productLabel.' Production (kg)',
                                    'other_source_volume_kg' => 'Other Sources (kg)',
                                    'value_php' => 'Production Value (₱)',
                                    'deworming' => 'Deworming',
                                    'vitamins_minerals' => 'Vitamins / Mineral Supplements',
                                    'vaccination' => 'Vaccination',
                                    'uterine_flushing' => 'Uterine Flushing',
                                    'ear_tagging' => 'Ear Tagging',
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

                                <td class="td {{ $module === 'mtd' && $columnKey === 'remarks' ? 'min-w-[20rem] max-w-[32rem]' : 'max-w-xs' }} {{ ($module === 'health' && in_array($columnKey,['services','dosage_ml','remarks','actions'],true)) || ($module === 'mtd' && $columnKey === 'remarks') ? 'whitespace-normal' : 'whitespace-nowrap' }}">
                                    @if($module === 'fhpr' && $columnKey === 'days_in_milk')
                                        <div x-data="fhprMilkDate(@js($row->id), @js($value), @js($row->_lactation_start_date), @js($row->_lactation_start_display))" @fhpr-dry-off-updated.window="update($event.detail)">
                                            <div x-text="days===null||days===''?'—':days">{{ $value !== null && $value !== '' ? $value : '—' }}</div>
                                            <div x-show="startDate" x-text="displayDate" title="Lactation start date" class="mt-1 text-xs text-slate-500">{{ $row->_lactation_start_display }}</div>
                                        </div>
                                    @elseif($module === 'fhpr' && $columnKey === 'status_updated')
                                        <span x-data="{id:@js($row->id), classification:@js($value)}" @fhpr-dry-off-updated.window="if(Number($event.detail.id)===Number(id))classification=$event.detail.classification" x-text="classification||'—'">{{ $value ?: '—' }}</span>
                                    @elseif($module === 'fhpr' && $columnKey === 'dry_off_date')
                                        <div x-data="fhprDryOffEditor(@js(route('fhpr.dry-off.update', $row->id)), @js($row->_dry_off_date), @js($value), @js($row->id))">
                                            <div x-show="!editing" class="flex items-center gap-3">
                                                <span x-text="displayDate"></span>
                                                <button type="button" @click="open()" class="rounded border border-blue-200 px-2 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-50" title="Update Dry Off Date" aria-label="Update Dry Off Date for {{ $row->animal_identifier }}"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>
                                            </div>
                                            <form x-show="editing" x-cloak @submit.prevent="save()" class="space-y-2">
                                                <input x-ref="input" type="date" x-model="draftDate" :disabled="saving" aria-label="Dry Off Date for {{ $row->animal_identifier }}" class="rounded-lg border border-slate-300 px-2 py-1 text-sm">
                                                <div class="flex gap-2">
                                                    <button type="submit" :disabled="saving" class="rounded bg-blue-700 px-2 py-1 text-xs font-semibold text-white disabled:opacity-50" x-text="saving?'Saving…':'Save'"></button>
                                                    <button type="button" @click="cancel()" :disabled="saving" class="rounded border px-2 py-1 text-xs text-slate-600">Cancel</button>
                                                </div>
                                            </form>
                                            <p x-show="error" x-text="error" role="alert" class="mt-1 max-w-56 whitespace-normal text-xs text-red-700"></p>
                                            <p x-show="saved" role="status" class="mt-1 text-xs text-emerald-700">Updated</p>
                                        </div>
                                    @elseif($module === 'health' && $columnKey === 'services')
                                        @if($row->worksheet_services)
                                            @foreach($row->worksheet_services as $service)<div class="mb-1 last:mb-0">{{ $service }}</div>@endforeach
                                        @else
                                            @forelse($row->services as $service)<div class="mb-1 last:mb-0">{{ $service['type'] === 'Other Services' ? ($service['other_service_name'] ?: 'Other Services') : $service['type'] }}</div>@empty<span class="text-slate-400">—</span>@endforelse
                                        @endif
                                    @elseif($module === 'health' && $columnKey === 'dosage_ml')
                                        @if($row->worksheet_dosage !== null && $row->worksheet_dosage !== '')
                                            <div class="whitespace-pre-wrap">{{ $row->worksheet_dosage }}</div>
                                        @else
                                            @forelse($row->services as $service)<div class="mb-1 last:mb-0">{{ $service['dosage_display'] ?: '—' }}</div>@empty<span class="text-slate-400">—</span>@endforelse
                                        @endif
                                    @elseif($module === 'health' && $columnKey === 'actions')
                                        <div class="flex items-center gap-1">
                                            <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-slate-700 hover:bg-blue-50" onclick="openHealthEditor({{ $row->id }},true)" aria-label="View health record" title="View details"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>
                                            <button type="button" class="rounded-lg border border-blue-200 px-3 py-2 text-blue-700 hover:bg-blue-50" onclick="openHealthEditor({{ $row->id }})" aria-label="Edit health record" title="Edit health record"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></button>
                                            <button type="button" class="rounded-lg px-3 py-2 text-red-600 hover:bg-red-50" onclick="deleteHealthActivity({{ $row->id }})" aria-label="Delete health record" title="Delete health record"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                                        </div>
                                    @elseif($module === 'health' && $columnKey === 'remarks')
                                        @php
                                            $serviceRemarks = array_filter(array_column($row->services, 'remarks'), fn ($remark) => $remark !== '');
                                        @endphp
                                        @foreach($serviceRemarks as $remark)<div class="mb-1 last:mb-0">{{ $remark }}</div>@endforeach
                                        @if($row->remarks !== null && $row->remarks !== '')<div class="whitespace-pre-wrap text-slate-600">{{ $row->remarks }}</div>@elseif(!$serviceRemarks)<span class="text-slate-400">—</span>@endif
                                    @elseif($module === 'milk' && $columnKey === 'record_date')
                                        {{ $value ? \Illuminate\Support\Carbon::parse($value)->format('F j, Y') : '—' }}
                                    @elseif($module === 'milk' && in_array($columnKey, ['price_per_kg','value_total'], true))
                                        {{ $value !== null ? '₱'.number_format((float)$value, 2) : '—' }}
                                    @elseif($module === 'milk' && in_array($columnKey, ['total_liters','milk_traded'], true))
                                        {{ $value !== null ? number_format((float)$value, 2) : '—' }}
                                    @elseif($isProduct && in_array($columnKey, ['actual_date', 'reported_date'], true))
                                        {{ $value ? \Illuminate\Support\Carbon::parse($value)->format('d-M-y') : '—' }}
                                    @elseif($isProduct && in_array($columnKey, ['volume_kg', 'other_source_volume_kg', 'price_per_kg', 'value_php'], true))
                                        {{ $value === null ? '—' : (in_array($columnKey, ['price_per_kg', 'value_php'], true) ? '₱' : '').number_format((float) $value, 2) }}
                                    @elseif($module === 'mtd' && $columnKey === 'test_date')
                                        {{ $value ? \Illuminate\Support\Carbon::parse($value)->format('d-M-y') : '—' }}
                                    @elseif($module === 'mtd' && $columnKey === 'bulk_kg')
                                        {{ $value ?? ($row->milk_yield_liters !== null ? $row->milk_yield_liters.' L' : '—') }}
                                    @elseif($module === 'mtd' && in_array($columnKey, $row->missing_teats, true))
                                        <span class="text-slate-500">× Missing</span>
                                    @elseif($module === 'mtd' && $columnKey === 'remarks')
                                        <span class="whitespace-pre-wrap break-words">{{ $value !== null && $value !== '' ? $value : '—' }}</span>
                                    @elseif($module === 'mtd' && $columnKey === 'actions')
                                        <div class="flex items-center gap-1" x-data="{ record: @js(array_intersect_key((array) $row, array_flip([...$columns, 'id', 'missing_teats', 'milk_yield_liters']))) }">
                                            <button type="button" @click="$dispatch('mtd-record',{mode:'view',record})" class="rounded-lg border border-slate-200 px-3 py-2 hover:bg-blue-50" title="View details" aria-label="View MTD record"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>
                                            <button type="button" @click="$dispatch('mtd-record',{mode:'edit',record})" class="rounded-lg border border-blue-200 px-3 py-2 text-blue-700 hover:bg-blue-50" title="Edit record" aria-label="Edit MTD record"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></button>
                                            <button type="button" @click="$dispatch('mtd-record',{mode:'delete',record})" class="rounded-lg px-3 py-2 text-rose-600 hover:bg-rose-50" title="Delete record" aria-label="Delete MTD record"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                                        </div>
                                    @elseif($module === 'exits' && $columnKey === 'exit_date')
                                        {{ $value ? \Illuminate\Support\Carbon::parse($value)->format('d-M-y') : '—' }}
                                    @elseif($module === 'exits' && $columnKey === 'actions')
                                        <div class="flex items-center gap-1" x-data="{ record: @js($row) }">
                                            <button type="button" @click="$dispatch('exit-record',{mode:'view',record})" class="rounded-lg border border-slate-200 px-3 py-2 hover:bg-blue-50" title="View details" aria-label="View exit record"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>
                                            @if($row->record_id)
                                                <button type="button" @click="$dispatch('exit-record',{mode:'edit',record})" class="rounded-lg border border-blue-200 px-3 py-2 text-blue-700 hover:bg-blue-50" title="Edit record" aria-label="Edit exit record"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></button>
                                                <button type="button" @click="$dispatch('exit-record',{mode:'delete',record})" class="rounded-lg px-3 py-2 text-rose-600 hover:bg-rose-50" title="Delete record" aria-label="Delete exit record"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                                            @endif
                                        </div>
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
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <p class="text-sm text-slate-500">
                    Showing <b>{{ $firstItem }}</b>–<b>{{ $lastItem }}</b>
                    of <b>{{ number_format($totalRecords) }}</b>
                    @if($module === 'health')
                        (<b>{{ number_format($healthSummary['activities']) }}</b> total)
                    @else
                        records
                    @endif
                </p>
                @if($module === 'health')
                    <form method="get" action="{{ url('/health') }}" data-health-page-size>
                        @foreach(array_filter($healthFilters) as $field => $value)
                            <input type="hidden" name="{{ $field }}" value="{{ $value }}">
                        @endforeach
                        @if($searchValue !== '')
                            <input type="hidden" name="search" value="{{ $searchValue }}">
                        @endif
                        <label class="flex items-center gap-2 text-sm text-slate-500">
                            Rows per page
                            <select name="per_page" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm">
                                @foreach([10, 15, 25, 50, 100] as $size)
                                    <option value="{{ $size }}" @selected($rows->perPage() === $size)>{{ $size }}</option>
                                @endforeach
                            </select>
                        </label>
                        <noscript><button type="submit" class="page-btn">Apply</button></noscript>
                    </form>
                @endif
            </div>

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
                        <a href="{{ $prevUrl }}" @if($module === 'health') data-health-page="previous" @endif class="page-btn">Previous</a>
                    @else
                        <span class="page-btn" aria-disabled="true" style="opacity:.4;cursor:not-allowed">Previous</span>
                    @endif

                    <span class="text-sm" @if($module === 'health') data-health-page-status tabindex="-1" @endif>Page <b>{{ $currentPage }}</b> of <b>{{ $lastPage }}</b></span>

                    @if($nextUrl)
                        <a href="{{ $nextUrl }}" @if($module === 'health') data-health-page="next" @endif class="page-btn">Next</a>
                    @else
                        <span class="page-btn" aria-disabled="true" style="opacity:.4;cursor:not-allowed">Next</span>
                    @endif
                </div>
            @endif
        </div>
        @if($module === 'health')
            <p data-health-pagination-message role="status" class="px-5 text-sm text-slate-600"></p>
        @endif
    </section>

    @unless($module === 'fhpr')
        <dialog id="{{ $module }}-csv-import" aria-labelledby="{{ $module }}-import-title" class="m-auto w-11/12 max-w-lg rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-950/60">
            <form method="post" action="{{ route('modules.import', $module) }}" enctype="multipart/form-data">
                @csrf
                <div class="space-y-4 p-5">
                    <h2 id="{{ $module }}-import-title" class="text-xl font-bold text-[#12345a]">{{ match($module) { 'health' => 'Import Health CSV', 'milk' => 'Import Milk CSV', 'meat', 'manure' => 'Import '.$productLabel.' CSV', 'mtd' => 'Import MTD CSV', 'exits' => 'Import Exit Records CSV', 'bcs' => 'Import BCS CSV', default => 'Import CSV' } }}</h2>
                    <label class="block text-sm font-semibold">CSV file<input type="file" name="file" accept=".csv,.txt" required class="mt-1 block w-full rounded-lg border border-slate-300 text-sm file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2"></label>
                </div>
                <footer class="flex justify-end gap-2 border-t bg-slate-50 p-4">
                    <button type="button" onclick="this.closest('dialog').close()" class="action-secondary">Cancel</button>
                    <button type="submit" class="action-primary">Import</button>
                </footer>
            </form>
        </dialog>
    @endunless
    @if($module === 'milk')
        <dialog id="milk-production-form" aria-labelledby="milk-production-title" class="m-auto max-h-[92vh] w-11/12 max-w-4xl overflow-auto rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-950/60"
            x-data="milkProductionEntry(@js($milkCooperatives), @js($milkFarmers), @js($milkAnimals), @js((string) old('cooperative_id', '')), @js((string) old('farmer_id', '')), @js((string) old('carabao_id', '')), @js(old('number_of_cows', '')))"
            @if($errors->milkProduction->any()) x-init="$el.showModal()" @endif>
            <form method="post" action="{{ route('milk.production.store') }}">
                @csrf
                <header class="flex items-center justify-between gap-3 border-b p-5">
                    <h2 id="milk-production-title" class="text-xl font-bold text-[#12345a]">Add Milk Production</h2>
                    <button type="button" onclick="this.closest('dialog').close()" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100" aria-label="Close milk production form"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </header>
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    @if($errors->milkProduction->any())
                        <ul role="alert" class="rounded-lg bg-rose-50 p-3 text-sm text-rose-800 sm:col-span-2">@foreach($errors->milkProduction->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    @endif
                    @foreach(['cooperative' => ['Cooperative', 'cooperative_id'], 'farmer' => ['Farmer', 'farmer_id'], 'animal' => ['Animal No.', 'carabao_id']] as $picker => [$title, $field])
                        @if($picker === 'animal')
                            <label class="text-sm font-semibold">Number of Cows<input type="number" name="number_of_cows" x-model.number="cows" @input="if(Number($event.target.value)!==1){animal='';if(openPicker==='animal')openPicker=''}" min="0" step="1" required class="filter-input mt-1"></label>
                        @endif
                        <div class="relative text-sm" @if($picker === 'animal') x-show="Number(cows)===1" @endif
                            @click.outside="if(openPicker==='{{ $picker }}')openPicker=''"
                            @keydown.escape="if(openPicker==='{{ $picker }}'){$event.stopPropagation();$event.preventDefault();openPicker='';$el.querySelector('button').focus()}">
                            <span id="milk-{{ $picker }}-label" class="font-semibold">{{ $title }}</span>
                            <input type="hidden" name="{{ $field }}" :value="{{ $picker }}" @if($picker === 'animal') :disabled="Number(cows)!==1" @endif>
                            <button type="button" class="filter-input mt-1 flex items-center justify-between gap-2 text-left disabled:opacity-60"
                                aria-labelledby="milk-{{ $picker }}-label milk-{{ $picker }}-value" aria-controls="milk-{{ $picker }}-choices" :aria-expanded="openPicker==='{{ $picker }}'"
                                :disabled="pickerDisabled('{{ $picker }}')" @click="togglePicker('{{ $picker }}', $el.parentElement)">
                                <span id="milk-{{ $picker }}-value" x-text="pickerValue('{{ $picker }}')"></span>
                                <i class="fa-solid fa-chevron-down text-xs text-slate-500" aria-hidden="true"></i>
                            </button>
                            <div id="milk-{{ $picker }}-choices" x-cloak x-show="openPicker==='{{ $picker }}'" class="absolute inset-x-0 z-10 mt-1 rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                                <input type="search" x-model="query" aria-label="Search {{ strtolower($title) }}" placeholder="Search {{ strtolower($title) }}…" class="filter-input" @keydown.enter.prevent="if(matches('{{ $picker }}').length===1)choose('{{ $picker }}', matches('{{ $picker }}')[0], $el.closest('.relative'))">
                                <ul class="mt-1 max-h-40 overflow-y-auto">
                                    @if($picker === 'animal')
                                        <li x-show="animal && !query"><button type="button" @click="choose('animal', {id:''}, $el.closest('.relative'))" class="w-full rounded-md px-2 py-2 text-left text-slate-600 hover:bg-sky-50 focus:bg-sky-50">Clear animal selection</button></li>
                                    @endif
                                    <template x-for="item in matches('{{ $picker }}')" :key="item.id"><li><button type="button" @click="choose('{{ $picker }}', item, $el.closest('.relative'))" class="w-full rounded-md px-2 py-2 text-left hover:bg-sky-50 focus:bg-sky-50" x-text="item.name"></button></li></template>
                                </ul>
                                <p x-show="!matches('{{ $picker }}').length" class="p-2 text-slate-500">No matches found.</p>
                            </div>
                        </div>
                    @endforeach
                    <label class="text-sm font-semibold">Collection date<input type="date" name="record_date" value="{{ old('record_date', now()->toDateString()) }}" required class="filter-input mt-1"></label>
                    <label class="text-sm font-semibold">AM / PM
                        <select name="collection_period" required class="filter-input mt-1"><option value="">Select period</option>@foreach(['AM','PM'] as $period)<option value="{{ $period }}" @selected(old('collection_period') === $period)>{{ $period }}</option>@endforeach</select>
                    </label>
                    <label class="text-sm font-semibold">Total Milk Production (kg)<input type="number" name="total_liters" min="0" max="999999.99" step="0.01" value="{{ old('total_liters') }}" required class="filter-input mt-1"></label>
                    <label class="text-sm font-semibold">Price per Kilogram (₱)<input type="number" name="price_per_kg" min="0" step="0.01" value="{{ old('price_per_kg') }}" required class="filter-input mt-1"></label>
                    <label class="text-sm font-semibold">Milk Traded (kg)<input type="number" name="milk_traded" min="0" step="0.01" value="{{ old('milk_traded') }}" required class="filter-input mt-1"></label>
                </div>
                <footer class="flex justify-end gap-2 border-t bg-slate-50 p-4">
                    <button type="button" onclick="this.closest('dialog').close()" class="action-secondary">Cancel</button>
                    <button type="submit" :disabled="!cooperative || !farmer" class="action-primary disabled:opacity-50">Save Milk Production</button>
                </footer>
            </form>
        </dialog>
    @elseif($isProduct)
        <dialog id="{{ $module }}-production-form" aria-labelledby="product-production-title" class="m-auto max-h-[92vh] w-11/12 max-w-4xl overflow-auto rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-950/60" @if($errors->productProduction->any()) x-data x-init="$el.showModal()" @endif>
            <form method="post" action="{{ route($module.'.production.store') }}">
                @csrf
                <header class="flex items-center justify-between gap-3 border-b p-5">
                    <h2 id="product-production-title" class="text-xl font-bold text-[#12345a]">Add {{ $productLabel }} Production</h2>
                    <button type="button" onclick="this.closest('dialog').close()" class="rounded-lg p-2 hover:bg-slate-100" aria-label="Close {{ $module }} production form"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </header>
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    @if($errors->productProduction->any())<ul role="alert" class="rounded-lg bg-rose-50 p-3 text-sm text-rose-800 sm:col-span-2">@foreach($errors->productProduction->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
                    <label class="text-sm font-semibold sm:col-span-2">Cooperative<select name="cooperative_id" required class="filter-input mt-1"><option value="">Select cooperative</option>@foreach($productCooperatives as $cooperative)<option value="{{ $cooperative->id }}" @selected((string) old('cooperative_id') === (string) $cooperative->id)>{{ $cooperative->cooperative_name }}</option>@endforeach</select></label>
                    <label class="text-sm font-semibold">Production Date<input type="date" name="actual_date" value="{{ old('actual_date', now()->toDateString()) }}" required class="filter-input mt-1"></label>
                    <label class="text-sm font-semibold">Reported Date<input type="date" name="reported_date" value="{{ old('reported_date') }}" class="filter-input mt-1"></label>
                    <label class="text-sm font-semibold">{{ $productLabel }} Production (kg)<input type="number" name="volume_kg" value="{{ old('volume_kg') }}" min="0" step="0.01" required class="filter-input mt-1"></label>
                    <label class="text-sm font-semibold">Other Sources (kg)<input type="number" name="other_source_volume_kg" value="{{ old('other_source_volume_kg') }}" min="0" step="0.01" class="filter-input mt-1"></label>
                    <label class="text-sm font-semibold">Farm Gate Price per kg (₱)<input type="number" name="price_per_kg" value="{{ old('price_per_kg') }}" min="0" step="0.01" class="filter-input mt-1"></label>
                    <label class="text-sm font-semibold">Production Value (₱)<input type="number" name="value_php" value="{{ old('value_php') }}" min="0" step="0.01" class="filter-input mt-1"></label>
                </div>
                <footer class="flex justify-end gap-2 border-t bg-slate-50 p-4"><button type="button" onclick="this.closest('dialog').close()" class="action-secondary">Cancel</button><button type="submit" class="action-primary">Save {{ $productLabel }} Production</button></footer>
            </form>
        </dialog>
    @elseif($module === 'health')
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
                    <div class="rounded-xl border border-slate-200 p-4"><div class="mb-3 flex items-center justify-between gap-2"><div><h3 class="font-bold text-slate-800">Type of Service</h3><p class="text-xs text-slate-500">Each service has its own optional dosage and remarks. Add custom service types as needed.</p></div><button id="health-add-service-button" type="button" onclick="addHealthServiceRow()" class="cbed-add-button rounded-lg px-3 py-2 text-sm font-semibold"><i class="fa-solid fa-plus mr-1"></i>Add Service</button></div><div id="health-service-rows" class="space-y-3"></div></div>
                    <label class="block text-sm font-semibold">Activity Remarks<textarea id="health-activity-remarks" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></textarea></label>
                    <div class="flex justify-end gap-2"><button type="button" onclick="closeHealthEditor()" class="rounded-lg border px-4 py-2">Close</button><button id="health-save-button" type="submit" class="action-primary bg-blue-700 text-white">Save Activity</button></div>
                </form>
            </div>
        </div>
    @endif

    <footer class="py-5 text-center text-xs text-slate-400">CBED Data System · {{ $pageTitle }}</footer>

</div>

    @if($module === 'mtd')
        @include('partials.mtd-entry')
        @include('partials.mtd-record-actions')
    @elseif($module === 'exits')
        @include('partials.animal-exit-dialog')
    @endif
@endsection

@if(in_array($module, ['milk', 'mtd', 'exits'], true))
@push('scripts')
<script>
function milkProductionEntry(cooperatives, farmers, animals, cooperative, farmer, animal, cows) {
    return {
        cooperative, farmer, animal, cows, openPicker:'', query:'',
        options(picker) {
            if(picker==='cooperative')return cooperatives.map(item=>({id:item.id,name:item.cooperative_name}));
            if(picker==='farmer')return farmers.filter(item=>String(item.cooperative_id)===this.cooperative);
            return animals.filter(item=>String(item.farmer_id)===this.farmer)
                .map(item=>({id:item.id,name:item.animal_name ? item.animal_number+' — '+item.animal_name : item.animal_number}));
        },
        matches(picker) {
            const terms=this.query.toLowerCase().trim().split(/\s+/).filter(Boolean);
            return this.options(picker).filter(item=>terms.every(term=>String(item.name).toLowerCase().includes(term)));
        },
        pickerDisabled(picker) {
            return (picker==='farmer' && !this.cooperative) || (picker==='animal' && !this.farmer);
        },
        pickerValue(picker) {
            const selected=this.options(picker).find(item=>String(item.id)===this[picker]);
            if(selected)return selected.name;
            if(picker==='cooperative')return 'Select cooperative';
            if(picker==='farmer')return this.cooperative ? 'Select farmer' : 'Select a cooperative first';
            return this.farmer ? 'Select animal (optional)' : 'Select a farmer first';
        },
        togglePicker(picker, wrapper) {
            this.openPicker=this.openPicker===picker ? '' : picker;
            this.query='';
            if(this.openPicker)this.$nextTick(()=>wrapper.querySelector('input[type="search"]').focus());
        },
        choose(picker, item, wrapper) {
            const id=String(item.id);
            if(this[picker]!==id){
                this[picker]=id;
                if(picker==='cooperative')this.farmer='';
                if(picker!=='animal')this.animal='';
            }
            this.openPicker='';
            wrapper.querySelector('button').focus();
        }
    };
}
function mtdEntry(cooperatives, farmers, animals, cooperative, farmer, previousRows, today) {
    const teats=['right_front','right_back','left_front','left_back'];
    const inventory=animals.map(animal=>({...animal,animal_number:animal.animal_id || animal.registry_number || animal.tag_number || String(animal.id)}));
    const directory=milkProductionEntry(cooperatives,farmers,inventory,cooperative,farmer,'',0);
    const rows=Object.values(previousRows || {}).filter(row=>row && typeof row==='object').map((row,index)=>({...row,key:index+1,missing_teats:Array.isArray(row.missing_teats) ? row.missing_teats : []}));
    if(!rows.length)rows.push({key:1,carabao_id:'',test_date:today,collection_period:'',missing_teats:[]});
    return {...directory,rows,nextKey:rows.length+1,
        availableAnimals(row){
            const selected=new Set(this.rows.filter(other=>other.key!==row.key && other.carabao_id)
                .map(other=>String(other.carabao_id)));
            return this.options('animal').filter(animal=>!selected.has(String(animal.id)));
        },
        addRow(){
            const previous=this.rows.at(-1);
            this.rows.push({key:this.nextKey++,carabao_id:'',test_date:previous?.test_date || today,collection_period:previous?.collection_period || '',missing_teats:[]});
        },
        loadTeatAvailability(row,id){
            const animal=inventory.find(item=>String(item.id)===String(id));
            row.carabao_id=id;
            row.missing_teats=[...(animal?.mtd_missing_teats || [])];
            teats.forEach(teat=>{row[teat]='';});
        },
        setMissingTeat(row,teat,missing){
            row.missing_teats=row.missing_teats.filter(value=>value!==teat);
            if(missing){
                row.missing_teats.push(teat);
                row[teat]='';
            }
        },
        choose(picker,item,wrapper){
            const changed=this[picker]!==String(item.id);
            directory.choose.call(this,picker,item,wrapper);
            if(changed)this.rows.forEach(row=>this.loadTeatAvailability(row,''));
        }
    };
}
function mtdRecordActions(baseUrl, results) {
    const resultFields=['bulk_result','right_front','right_back','left_front','left_back'];
    const fields=['test_date','collection_period','bulk_kg',...resultFields,'remarks'];
    return {
        results, resultFields, fields, mode:'view', record:{}, draft:{}, error:'', saving:false,
        labels:{cooperative_name:'Cooperative',farmer_name:'Farmer',animal_identifier:'Animal',test_date:'Date Conducted',
            collection_period:'AM / PM',bulk_kg:'Bulk (kg)',bulk_result:'Bulk Result',right_front:'RF - Right Front',
            right_back:'RB - Right Back',left_front:'LF - Left Front',left_back:'LB - Left Back',remarks:'Remarks'},
        open({mode,record}) {
            this.mode=mode;
            this.record=record;
            this.error='';
            this.draft=Object.fromEntries(fields.map(field=>[field,resultFields.includes(field) && !results.includes(record[field]) ? '' : (record[field] ?? '')]));
            this.$root.showModal();
            this.$nextTick(()=>this.$refs.cancel.focus());
        },
        isMissing(field) {
            return (this.record.missing_teats || []).includes(field);
        },
        display(field) {
            if(this.isMissing(field))return '× Missing';
            if(field==='bulk_kg' && this.record.bulk_kg==null && this.record.milk_yield_liters!=null)return this.record.milk_yield_liters+' L';
            return this.record[field] == null || this.record[field]==='' ? '—' : this.record[field];
        },
        async save() {
            if(this.saving || !['edit','delete'].includes(this.mode) || !this.record.id)return;
            this.saving=true;
            this.error='';
            try {
                const response=await fetch(baseUrl+'/'+this.record.id,{
                    method:this.mode==='edit' ? 'PUT' : 'DELETE',
                    headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || ''},
                    body:this.mode==='edit' ? JSON.stringify(Object.fromEntries(fields.map(field=>[field,this.draft[field]==='' ? null : this.draft[field]]))) : undefined
                });
                if(!response.ok){
                    const result=await response.json().catch(()=>({}));
                    throw Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Could not update the MTD record.');
                }
                this.$root.close();
                window.location.reload();
            } catch(error) {
                this.error=error.message;
            } finally {
                this.saving=false;
            }
        }
    };
}
function animalExitDialog(cooperatives, farmers, animals, labels, natureOptions, baseUrl, today) {
    const directory=milkProductionEntry(cooperatives,farmers,animals,'','','',0);
    const fields=Object.keys(labels);
    return {...directory, labels, fields, mode:'add', record:{}, draft:{}, natureOptions, error:'', saving:false,
        titles:{add:'Add Exit',view:'Exit Record Details',edit:'Edit Exit Record',delete:'Delete exit record?'},
        options(picker){
            const choices=directory.options.call(this,picker);
            if(picker!=='animal')return choices;
            const allowed=new Set(animals.filter(item=>String(item.cooperative_id)===this.cooperative).map(item=>item.id));
            return choices.filter(item=>allowed.has(item.id));
        },
        choose(picker,item,wrapper){
            const changed=this[picker]!==String(item.id);
            directory.choose.call(this,picker,item,wrapper);
            if(changed && picker!=='animal')this.selectAnimal('');
        },
        selectAnimal(id){
            const selected=animals.find(item=>String(item.id)===id);
            this.animal=id;
            this.draft.entrustment=selected?.entrustment || '';
            this.draft.ownership_status=selected?.ownership_status || '';
        },
        showExit({mode,record={}}){
            if(this.saving)return;
            this.mode=mode;
            this.record=record;
            this.error='';
            this.cooperative=this.farmer=this.animal=this.openPicker='';
            this.draft=Object.fromEntries(fields.map(field=>[field,record[field] ?? (mode==='add' && field==='exit_date' ? today : '')]));
            this.natureOptions=[...new Set([...natureOptions,...(record.nature_of_exit ? [record.nature_of_exit] : [])])];
            this.$root.showModal();
            this.$nextTick(()=>this.$refs.cancel.focus());
        },
        async save(){
            if(this.saving || this.mode==='view' || (this.mode==='add' ? !this.animal : !this.record.record_id))return;
            const adding=this.mode==='add';
            const deleting=this.mode==='delete';
            const payload=adding ? {...this.draft,cooperative_id:this.cooperative,farmer_id:this.farmer,carabao_id:this.animal} : this.draft;
            this.saving=true;
            this.error='';
            try{
                const response=await fetch(adding ? baseUrl : baseUrl+'/'+this.record.record_id,{
                    method:adding ? 'POST' : (deleting ? 'DELETE' : 'PUT'),
                    headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || ''},
                    body:deleting ? undefined : JSON.stringify(payload)
                });
                if(!response.ok){
                    const result=await response.json().catch(()=>({}));
                    throw Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Could not save the exit record.');
                }
                this.$root.close();
                if(adding)window.location.assign(baseUrl);
                else window.location.reload();
            }catch(error){
                this.error=error.message;
            }finally{
                this.saving=false;
            }
        }
    };
}
</script>
@endpush
@endif

@if($module === 'health')
@push('scripts')
<script>
const healthRecordsUrl=@json(url('/admin/health-records'));
const healthPanel=document.getElementById('health-records-panel');
if(healthPanel){
    const address=new URL(window.location.href);
    if(address.searchParams.has('page') || address.searchParams.has('per_page')){
        address.searchParams.delete('page');
        address.searchParams.delete('per_page');
        window.history.replaceState(window.history.state,'',address);
    }

    let pageRequest;
    async function loadHealthPage(url, focusSelector){
        pageRequest?.abort();
        const request=new AbortController();
        pageRequest=request;
        const message=healthPanel.querySelector('[data-health-pagination-message]');
        message.textContent='Loading records…';
        healthPanel.setAttribute('aria-busy','true');
        try{
            const response=await fetch(url,{signal:request.signal});
            if(!response.ok)throw new Error('Could not load records.');
            const html=await response.text();
            if(request.signal.aborted)return;
            const page=new DOMParser().parseFromString(html,'text/html').getElementById(healthPanel.id);
            if(!page)throw new Error('Could not load records.');
            healthPanel.replaceChildren(...page.childNodes);
            (healthPanel.querySelector(focusSelector) || healthPanel.querySelector('[data-health-page-status]'))?.focus({preventScroll:true});
        }catch(error){
            if(request.signal.aborted)return;
            message.textContent='Could not load records. Please try again.';
            const size=healthPanel.querySelector('[data-health-page-size] select');
            const original=[...size.options].find(option=>option.defaultSelected);
            if(original)size.value=original.value;
        }finally{
            if(pageRequest===request){
                healthPanel.removeAttribute('aria-busy');
                pageRequest=null;
            }
        }
    }

    healthPanel.addEventListener('click',event=>{
        const link=event.target.closest('[data-health-page]');
        if(!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey)return;
        event.preventDefault();
        loadHealthPage(link.href,'[data-health-page="'+link.dataset.healthPage+'"]');
    });
    healthPanel.addEventListener('change',event=>{
        const form=event.target.closest('[data-health-page-size]');
        if(!form)return;
        const url=new URL(form.action);
        url.search=new URLSearchParams(new FormData(form));
        loadHealthPage(url,'[data-health-page-size] select');
    });
}
let healthServiceRowKey=0;
function closeHealthEditor(){document.getElementById('health-editor').classList.add('hidden');document.getElementById('health-editor').classList.remove('flex');}
function addHealthServiceRow(service={}){const index=healthServiceRowKey++,row=document.createElement('div');row.className='health-service-row grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 md:grid-cols-[1.4fr_.7fr_1.4fr_auto]';row.innerHTML=`<label class="text-xs font-semibold">Service Type<input list="health-service-options" data-service="type" class="mt-1 w-full rounded border px-2 py-2 text-sm" placeholder="Choose or enter a type" required></label><label class="text-xs font-semibold">Dosage (mL)<input data-service="dosage_ml" type="number" min="0" step="0.01" class="mt-1 w-full rounded border px-2 py-2 text-sm" placeholder="Not applicable"></label><label class="text-xs font-semibold">Service Remarks<input data-service="remarks" maxlength="2000" class="mt-1 w-full rounded border px-2 py-2 text-sm"></label><button type="button" class="self-end rounded border border-red-200 px-3 py-2 text-sm text-red-700 hover:bg-red-50" aria-label="Remove service" onclick="this.closest('.health-service-row').remove()">Remove</button>`;row.querySelector('[data-service="type"]').value=service.type==='Other Services'?(service.other_service_name||'Other Services'):(service.type||'');row.querySelector('[data-service="dosage_ml"]').value=service.dosage_ml??'';row.querySelector('[data-service="remarks"]').value=service.remarks??'';document.getElementById('health-service-rows').appendChild(row);}
function openHealthEditor(id=null,readOnly=false){const form=document.getElementById('health-editor-form');form.reset();form.querySelectorAll('input,select,textarea,#health-service-rows button').forEach(control=>control.disabled=false);document.getElementById('health-record-id').value='';document.getElementById('health-service-rows').replaceChildren();document.getElementById('health-monitoring-date').value=new Date().toISOString().slice(0,10);document.getElementById('health-editor-title').textContent=!id?'Add Health and Nutrition Activity':readOnly?'View Health and Nutrition Activity':'Edit Health and Nutrition Activity';document.getElementById('health-save-button').hidden=readOnly;document.getElementById('health-add-service-button').hidden=readOnly;if(!id)addHealthServiceRow();const modal=document.getElementById('health-editor');modal.classList.remove('hidden');modal.classList.add('flex');if(id){fetch(`${healthRecordsUrl}/${id}`,{headers:{Accept:'application/json'}}).then(async response=>{const record=await response.json();if(!response.ok)throw new Error(record.message||'Could not load this activity.');document.getElementById('health-record-id').value=record.id;document.getElementById('health-carabao-id').value=record.carabao_id;document.getElementById('health-monitoring-date').value=String(record.monitoring_date||'').slice(0,10);document.getElementById('health-visits').value=record.no_of_visits_monitored??'';document.getElementById('health-treatments').value=record.no_of_treatments??'';document.getElementById('health-activity-remarks').value=record.remarks||'';document.getElementById('health-service-rows').replaceChildren();(record.services||[]).forEach(service=>addHealthServiceRow(service));if(!(record.services||[]).length)addHealthServiceRow();if(readOnly)form.querySelectorAll('input,select,textarea,#health-service-rows button').forEach(control=>control.disabled=true);}).catch(error=>{alert(error.message);closeHealthEditor();});}else if(readOnly){form.querySelectorAll('input,select,textarea,#health-service-rows button').forEach(control=>control.disabled=true);}}
async function saveHealthActivity(event){event.preventDefault();const services=[...document.querySelectorAll('#health-service-rows .health-service-row')].map(row=>Object.fromEntries([...row.querySelectorAll('[data-service]')].map(input=>[input.dataset.service,input.value.trim()]))).filter(service=>service.type);if(!services.length){alert('Add at least one service.');return;}const payload={carabao_id:Number(document.getElementById('health-carabao-id').value),monitoring_date:document.getElementById('health-monitoring-date').value,no_of_visits_monitored:Number(document.getElementById('health-visits').value),no_of_treatments:Number(document.getElementById('health-treatments').value),remarks:document.getElementById('health-activity-remarks').value,services};const id=document.getElementById('health-record-id').value,token=document.querySelector('meta[name="csrf-token"]')?.content;const response=await fetch(id?`${healthRecordsUrl}/${id}`:healthRecordsUrl,{method:id?'PUT':'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':token},body:JSON.stringify(payload)});const result=await response.json().catch(()=>({}));if(!response.ok){alert(result.message||'Could not save the activity.');return;}location.reload();}
async function deleteHealthActivity(id){if(!confirm('Delete this Health and Nutrition activity and its service entries?'))return;const token=document.querySelector('meta[name="csrf-token"]')?.content;const response=await fetch(`${healthRecordsUrl}/${id}`,{method:'DELETE',headers:{Accept:'application/json','X-CSRF-TOKEN':token}});const result=await response.json().catch(()=>({}));if(!response.ok){alert(result.message||'Could not delete the activity.');return;}location.reload();}
</script>
@endpush
@endif

@if($module === 'fhpr')
@push('scripts')
<script>
function fhprMilkDate(id, days, startDate, displayDate) {
    return {
        id, days, startDate:startDate||'', displayDate:displayDate||'',
        update(detail) {
            if(Number(detail.id)!==Number(this.id))return;
            this.days=detail.days_in_milk;
            this.startDate=detail.lactation_start_date||'';
            this.displayDate=detail.lactation_start_display||'';
        }
    };
}
function fhprDryOffEditor(url, date, displayDate, recordId) {
    return {
        date:date||'', draftDate:date||'', displayDate:displayDate||'-',
        editing:false, saving:false, saved:false, error:'',
        open() {
            this.draftDate=this.date;
            this.editing=true; this.saved=false; this.error='';
            this.$nextTick(()=>this.$refs.input.focus());
        },
        cancel() {
            this.draftDate=this.date; this.editing=false; this.error='';
        },
        async save() {
            if(this.saving)return;
            this.saving=true; this.error=''; this.saved=false;
            try {
                const response=await fetch(url,{
                    method:'PATCH',
                    headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''},
                    body:JSON.stringify({dry_off_date:this.draftDate||null})
                });
                const result=await response.json().catch(()=>({}));
                if(!response.ok)throw new Error(result.errors?.dry_off_date?.[0]||result.message||'Could not update Dry Off Date.');
                this.date=result.dry_off_date||'';
                this.displayDate=result.display_date||'-';
                this.$dispatch('fhpr-dry-off-updated',{...result,id:recordId,date:this.date});
                this.editing=false; this.saved=true;
            }catch(error){
                this.error=error.message||'Could not update Dry Off Date.';
            }finally{
                this.saving=false;
            }
        }
    };
}
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
.fhpr-table-scroll{max-height:70vh;overflow:auto;isolation:isolate;--fhpr-no:56px;--fhpr-cooperative:220px;--fhpr-farmer:200px;--fhpr-animal:170px}
.fhpr-data-table{border-collapse:separate;border-spacing:0}
.fhpr-data-table thead th{position:sticky;top:0;z-index:20;background:#103c60}
.fhpr-data-table tr>:nth-child(-n+4){position:sticky;box-sizing:border-box;white-space:normal;overflow-wrap:anywhere}
.fhpr-data-table tr>:nth-child(1){left:0;width:var(--fhpr-no);min-width:var(--fhpr-no);max-width:var(--fhpr-no)}
.fhpr-data-table tr>:nth-child(2){left:var(--fhpr-no);width:var(--fhpr-cooperative);min-width:var(--fhpr-cooperative);max-width:var(--fhpr-cooperative)}
.fhpr-data-table tr>:nth-child(3){left:calc(var(--fhpr-no) + var(--fhpr-cooperative));width:var(--fhpr-farmer);min-width:var(--fhpr-farmer);max-width:var(--fhpr-farmer)}
.fhpr-data-table tr>:nth-child(4){left:calc(var(--fhpr-no) + var(--fhpr-cooperative) + var(--fhpr-farmer));width:var(--fhpr-animal);min-width:var(--fhpr-animal);max-width:var(--fhpr-animal);box-shadow:3px 0 5px -2px #94a3b8}
.fhpr-data-table tbody tr>td:nth-child(-n+4){z-index:10;background:#fff}
.fhpr-data-table tbody tr:hover>td:nth-child(-n+4){background:#eff6ff}
.fhpr-data-table thead th:nth-child(-n+4){z-index:30;background:#103c60}
.fhpr-data-table tbody td[colspan]{position:static;width:auto;max-width:none;box-shadow:none}
@media(max-width:640px){.fhpr-table-scroll{--fhpr-no:40px;--fhpr-cooperative:80px;--fhpr-farmer:80px;--fhpr-animal:96px}.fhpr-data-table tr>:nth-child(-n+4){padding:.75rem .375rem;font-size:.7rem}}
.page-btn{display:inline-flex;border:1px solid #cbd5e1;border-radius:.5rem;padding:.5rem .75rem;font-size:.875rem;font-weight:600;color:#334155;background:#fff}
a.page-btn:hover{background:#f1f5f9}
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
