{{-- resources/views/modules/index.blade.php --}}

@extends('layouts.admin')

@section('title', $moduleTitle ?? ucfirst(str_replace('-', ' ', $module ?? 'Module')))

@section('content')

@php
    $pageTitle = $moduleTitle ?? ucfirst(str_replace('-', ' ', $module ?? 'Module'));
    $isPaginator = method_exists($rows, 'total');
    $totalRecords = $isPaginator ? $rows->total() : $rows->count();
    $currentPageRows = $rows->count();
    $totalFields = count($columns ?? []);
    $currentPage = method_exists($rows, 'currentPage') ? $rows->currentPage() : 1;
    $lastPage = method_exists($rows, 'lastPage') ? $rows->lastPage() : 1;
    $firstItem = method_exists($rows, 'firstItem') ? ($rows->firstItem() ?? 0) : ($currentPageRows ? 1 : 0);
    $lastItem = method_exists($rows, 'lastItem') ? ($rows->lastItem() ?? 0) : $currentPageRows;

    $searchValue = request('search', '');

    $moduleDescriptions = [
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
            <a href="{{ route('modules.template', $module) }}" class="action-primary">
                <i class="fa-solid fa-download"></i> Download Template
            </a>
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

    {{-- SUMMARY CARDS --}}
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

    {{-- RECORDS TABLE --}}
    <section class="pcc-records-panel overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        <div class="flex flex-col gap-3 border-b p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-[#12345a]">{{ $pageTitle }} Records</h2>
                <p class="mt-1 text-sm text-slate-500">View, search, import, and manage your records.</p>
            </div>
            <div class="flex gap-2">
                <span class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold">
                    {{ number_format($totalRecords) }} records
                </span>
                @if($searchValue !== '')
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
                       class="filter-input" placeholder="Search records...">
            </div>
            <div class="flex items-end gap-2 xl:col-span-2">
                <button type="submit" class="action-primary">
                    <i class="fa-solid fa-magnifying-glass"></i> Search
                </button>
                @if($searchValue !== '')
                    <a href="{{ url()->current() }}" class="action-secondary">
                        <i class="fa-solid fa-xmark"></i> Clear
                    </a>
                @endif
            </div>
        </form>

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
                                    'registry_id' => 'Registry ID',
                                    'cooperative_name' => 'Cooperative',
                                    'farmer_name' => 'Farmer',
                                    'monitoring_date' => 'Date',
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

                                <td class="td max-w-xs whitespace-nowrap">
                                    @if(is_null($value) || $value === '')
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

    <footer class="py-5 text-center text-xs text-slate-400">CBED Data System · {{ $pageTitle }}</footer>

</div>

@endsection

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