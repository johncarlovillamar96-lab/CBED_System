
@extends('layouts.admin')

@section('title', 'Dashboard')
@section('dataSource', 'database')
@section('page-heading', 'Dashboard')

@section('content')

<div class="management-page dashboard-page min-w-0 bg-slate-50 text-slate-800">
<x-admin-page-header title="Welcome to CBED Data System" description="Monitor farmers, carabaos, and enterprise development activities." breadcrumb="Dashboard" />

<!-- Statistics -->
<div class="mb-7 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

    <div class="cbed-card dashboard-stat-card management-summary-card p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-slate-500">Total Farmers</p>
                <h3 class="mt-3 text-3xl font-bold text-slate-800">{{ number_format($counts['farmers'] ?? 0) }}</h3>
                <p class="mt-2 text-xs text-slate-400">Registered farmers</p>
            </div>
            <div class="dashboard-stat-icon flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-xl text-blue-600">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>
    </div>

    <div class="cbed-card dashboard-stat-card management-summary-card p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-slate-500">Cooperatives</p>
                <h3 class="mt-3 text-3xl font-bold text-slate-800">{{ number_format($counts['cooperatives'] ?? 0) }}</h3>
                <p class="mt-2 text-xs text-slate-400">Partner organizations</p>
            </div>
            <div class="dashboard-stat-icon flex h-12 w-12 items-center justify-center rounded-xl bg-violet-50 text-xl text-violet-600">
                <i class="fa-solid fa-people-group"></i>
            </div>
        </div>
    </div>

    <div class="cbed-card dashboard-stat-card management-summary-card p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-slate-500">Total Carabaos</p>
                <h3 class="mt-3 text-3xl font-bold text-slate-800">{{ number_format($animalSummary['animals'] ?? 0) }}</h3>
                <p class="mt-2 text-xs text-slate-400">Animal inventory</p>
            </div>
            <div class="dashboard-stat-icon flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-xl text-emerald-600">
                <i class="fa-solid fa-cow"></i>
            </div>
        </div>
    </div>

    <div class="cbed-card dashboard-stat-card management-summary-card p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-slate-500">Milk Production</p>
                <h3 class="mt-3 text-3xl font-bold text-slate-800">{{ number_format($milkTotal ?? 0, 1) }} L</h3>
                <p class="mt-2 text-xs text-slate-400">Recorded production in {{ $milkYear }}</p>
            </div>
            <div class="dashboard-stat-icon flex h-12 w-12 items-center justify-center rounded-xl bg-orange-50 text-xl text-orange-600">
                <i class="fa-solid fa-droplet"></i>
            </div>
        </div>
    </div>

</div>

<section class="dashboard-status-section mb-7">
    <div class="mb-3"><h2 class="text-lg font-bold text-slate-800">Animal status summary</h2><p class="text-sm text-slate-500">Current inventory classifications and animal counts</p></div>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-7">
        @foreach([
            'live' => ['Live', 'border-emerald-200 bg-emerald-50', 'text-emerald-700'],
            'dead' => ['Dead', 'border-rose-200 bg-rose-50', 'text-rose-700'],
            'pregnant' => ['Pregnant', 'border-violet-200 bg-violet-50', 'text-violet-700'],
            'breedable' => ['Breedable', 'border-indigo-200 bg-indigo-50', 'text-indigo-700'],
            'lactating' => ['Lactating', 'border-sky-200 bg-sky-50', 'text-sky-700'],
            'dry' => ['Cow Dry', 'border-amber-200 bg-amber-50', 'text-amber-700'],
            'parity_1' => ['Parity 1+', 'border-indigo-200 bg-indigo-50', 'text-indigo-700'],
        ] as $key => [$label, $cardColor, $textColor])
            <div class="cbed-card dashboard-status-card border {{ $cardColor }} p-4"><p class="text-xs font-medium {{ $textColor }}">{{ $label }}</p><p class="mt-2 text-2xl font-bold {{ $textColor }}">{{ number_format(($key === 'parity_1' ? ($animalSummary['parity_1'] + $animalSummary['parity_2'] + $animalSummary['parity_3'] + $animalSummary['parity_4_plus']) : ($animalSummary[$key] ?? 0))) }}</p></div>
        @endforeach
    </div>
    <div class="mt-3 flex flex-wrap gap-2 text-xs">@foreach(['parity_0' => 'Parity 0', 'parity_1' => 'Parity 1', 'parity_2' => 'Parity 2', 'parity_3' => 'Parity 3', 'parity_4_plus' => 'Parity 4+'] as $key => $label)<span class="rounded-full bg-white px-3 py-2 text-slate-600 shadow-sm">{{ $label }}: <b>{{ number_format($animalSummary[$key] ?? 0) }}</b></span>@endforeach</div>
</section>

<section class="dashboard-status-section mb-7">
    <div class="mb-3">
        <h2 class="text-lg font-bold text-slate-800">Animal classification breakdown</h2>
        <p class="text-sm text-slate-500">Counts for every classification recorded in the animal inventory</p>
    </div>
    @if (count($classificationSummary))
        <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-7">
            @foreach ($classificationSummary as $classification)
                <div class="cbed-card dashboard-status-card border border-slate-200 bg-white p-4">
                    <dt class="break-words text-xs font-medium text-slate-600">{{ $classification->classification }}</dt>
                    <dd class="mt-2 text-2xl font-bold tabular-nums text-slate-800">{{ number_format($classification->total) }}</dd>
                </div>
            @endforeach
        </dl>
    @else
        <p class="cbed-card border border-slate-200 bg-white p-4 text-sm text-slate-500">No classified animals in the inventory.</p>
    @endif
</section>

<section class="dashboard-status-section mb-7">
    <div class="mb-3">
        <h2 class="text-lg font-bold text-slate-800">Animal summary by province</h2>
        <p class="text-sm text-slate-500">Live, exited, breedable, pregnant, and lactating animals in each province</p>
    </div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 font-semibold">Province</th>
                    <th class="px-4 py-3 font-semibold text-emerald-700">Live</th>
                    <th class="px-4 py-3 font-semibold text-rose-700">Dead or Out</th>
                    <th class="px-4 py-3 font-semibold text-indigo-700">Breedable</th>
                    <th class="px-4 py-3 font-semibold text-violet-700">Pregnant</th>
                    <th class="px-4 py-3 font-semibold text-sky-700">Lactating</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($provincialSummary as $province)
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-700">{{ $province['province'] }}</td>
                        <td class="px-4 py-3 font-semibold text-emerald-700">{{ number_format($province['live']) }}</td>
                        <td class="px-4 py-3 font-semibold text-rose-700">{{ number_format($province['dead_or_out']) }}</td>
                        <td class="px-4 py-3 font-semibold text-indigo-700">{{ number_format($province['breedable']) }}</td>
                        <td class="px-4 py-3 font-semibold text-violet-700">{{ number_format($province['pregnant']) }}</td>
                        <td class="px-4 py-3 font-semibold text-sky-700">{{ number_format($province['lactating']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">No animal records to summarize by province.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<!-- Overview and quick actions -->
<div class="grid grid-cols-1 gap-5 xl:grid-cols-3">

    <section id="dashboard-milk-production" class="cbed-card dashboard-panel overflow-hidden rounded-xl border p-6 xl:col-span-2">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-slate-800">Milk Production</h2>
                <p class="mt-1 text-xs text-slate-500">Annual milk yield by cooperative</p>
            </div>
            <form method="get" action="{{ url()->current() }}#dashboard-milk-production" data-auto-filters>
                <label class="flex items-center gap-2 text-sm font-medium text-slate-600">
                    Year
                    <select name="milk_year" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800">
                        @foreach ($milkYears as $year)
                            <option value="{{ $year }}" @selected($year === $milkYear)>{{ $year }}</option>
                        @endforeach
                    </select>
                </label>
            </form>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-semibold">Cooperative</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-right font-semibold text-blue-700">{{ $milkYear }} Total (L)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($cooperativeMilk as $cooperative)
                        <tr class="hover:bg-slate-50">
                            <th scope="row" class="px-4 py-3 font-semibold text-slate-700">{{ $cooperative->cooperative_name ?: 'Cooperative not assigned' }}</th>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-slate-800">{{ number_format($cooperative->total_liters, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="px-4 py-8 text-center text-slate-500">No milk production recorded for {{ $milkYear }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="cbed-card dashboard-panel overflow-hidden rounded-xl border p-6">
        <h2 class="font-bold text-slate-800">Quick Actions</h2>
        <p class="mt-1 text-xs text-slate-500">Navigate to a management page</p>

        <div class="mt-5 space-y-3">

            <a href="{{ url('/farmers') }}"
               class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-blue-200 hover:bg-blue-50/60">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                    <i class="fa-solid fa-user-plus"></i>
                </span>
                <span>
                    <span class="block text-sm font-semibold text-slate-700">Farmer Management</span>
                    <span class="mt-1 block text-xs text-slate-500">View farmer records</span>
                </span>
                <i class="fa-solid fa-chevron-right ml-auto text-xs text-slate-400"></i>
            </a>

            <a href="{{ url('/cooperatives') }}"
               class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-violet-200 hover:bg-violet-50/60">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-violet-50 text-violet-600">
                    <i class="fa-solid fa-people-group"></i>
                </span>
                <span>
                    <span class="block text-sm font-semibold text-slate-700">Cooperatives</span>
                    <span class="mt-1 block text-xs text-slate-500">Manage organizations</span>
                </span>
                <i class="fa-solid fa-chevron-right ml-auto text-xs text-slate-400"></i>
            </a>

            <a href="{{ route('admin.carabao-inventory') }}"
               class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-emerald-200 hover:bg-emerald-50/60">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                    <i class="fa-solid fa-cow"></i>
                </span>
                <span>
                    <span class="block text-sm font-semibold text-slate-700">Carabao Inventory</span>
                    <span class="mt-1 block text-xs text-slate-500">Manage animal records</span>
                </span>
                <i class="fa-solid fa-chevron-right ml-auto text-xs text-slate-400"></i>
            </a>

            <a href="{{ url('/milk') }}"
               class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-orange-200 hover:bg-orange-50/60">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-orange-50 text-orange-600">
                    <i class="fa-solid fa-droplet"></i>
                </span>
                <span>
                    <span class="block text-sm font-semibold text-slate-700">Milk Production</span>
                    <span class="mt-1 block text-xs text-slate-500">View production records</span>
                </span>
                <i class="fa-solid fa-chevron-right ml-auto text-xs text-slate-400"></i>
            </a>

        </div>
    </section>

</div>

</div>

@push('styles')
<style>
    [x-cloak]{display:none!important}
    .management-page { background: radial-gradient(circle at 92% 0%,rgba(11,31,58,.06),transparent 25%),#f5f8fc; }
    .management-page .management-summary-card,.semen-bull-page .dashboard-stat-card { border-top:4px solid #0b5e8b!important;border-radius:1rem!important;box-shadow:0 5px 18px rgba(15,45,67,.06)!important;transition:transform .16s ease,box-shadow .16s ease; }
    .management-page .management-summary-card:nth-child(2),.semen-bull-page .dashboard-stat-card:nth-child(2) { border-top-color:#e87523!important; }
    .management-page .management-summary-card:nth-child(3),.semen-bull-page .dashboard-stat-card:nth-child(3) { border-top-color:#25835b!important; }
    .management-page .management-summary-card:nth-child(4),.semen-bull-page .dashboard-stat-card:nth-child(4) { border-top-color:#0786a5!important; }
    .management-page .management-summary-card:hover,.semen-bull-page .dashboard-stat-card:hover { transform:translateY(-3px);box-shadow:0 14px 28px rgba(7,62,99,.13)!important; }
    .management-page .management-summary-card > div > div:last-child,.semen-bull-page .dashboard-stat-icon { background:linear-gradient(145deg,#082d50,#0b6d9b)!important;color:#fff!important;border-radius:1rem;box-shadow:0 8px 18px rgba(7,62,99,.2); }
    .management-page .management-summary-card:nth-child(2) > div > div:last-child,.semen-bull-page .dashboard-stat-card:nth-child(2) .dashboard-stat-icon { background:linear-gradient(145deg,#a94b0b,#f28a32)!important; }
    .management-page .management-summary-card:nth-child(3) > div > div:last-child,.semen-bull-page .dashboard-stat-card:nth-child(3) .dashboard-stat-icon { background:linear-gradient(145deg,#176342,#38a36f)!important; }
    .management-page .management-summary-card:nth-child(4) > div > div:last-child,.semen-bull-page .dashboard-stat-card:nth-child(4) .dashboard-stat-icon { background:linear-gradient(145deg,#075785,#0798b6)!important; }
    .management-page .management-summary-card > div > div:last-child [class*="text-"],.semen-bull-page .dashboard-stat-icon [class*="text-"] { color:#fff!important; }
    .management-page .overflow-hidden.rounded-xl.border,.semen-bull-page .dashboard-panel { border-color:#dce6ed!important;border-radius:1rem!important;box-shadow:0 7px 24px rgba(15,45,67,.06)!important; }
    .management-page .management-summary-card [class*="text-blue-"],.management-page .management-summary-card [class*="text-emerald-"],.management-page .management-summary-card [class*="text-violet-"] { color:#0b1f3a!important; }
    .dashboard-status-section h2 { color:#0b1f3a; }
</style>
@endpush

@endsection
