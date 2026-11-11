
@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-heading', 'Dashboard')

@section('content')

<div class="management-page dashboard-page min-w-0 bg-slate-50 text-slate-800">
<div class="management-hero mb-7 flex flex-col justify-between gap-4 rounded-2xl p-5 text-white shadow-lg sm:flex-row sm:items-center sm:p-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-white">
            Welcome to CBED Data System
        </h1>
        <p class="mt-2 text-sm text-slate-200">
            Monitor farmers, carabaos, and enterprise development activities.
        </p>
    </div>

    <div class="rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-sm text-white">
        <i class="fa-regular fa-calendar mr-2"></i>{{ now()->format('F d, Y') }}
    </div>
</div>

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
                <p class="mt-2 text-xs text-slate-400">Recorded production</p>
            </div>
            <div class="dashboard-stat-icon flex h-12 w-12 items-center justify-center rounded-xl bg-orange-50 text-xl text-orange-600">
                <i class="fa-solid fa-droplet"></i>
            </div>
        </div>
    </div>

</div>

<section class="dashboard-status-section mb-7">
    <div class="mb-3"><h2 class="text-lg font-bold text-slate-800">Animal status summary</h2><p class="text-sm text-slate-500">Current counts from the animal inventory</p></div>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        @foreach(['live' => 'Live', 'dead' => 'Dead', 'pregnant' => 'Pregnant', 'lactating' => 'Lactating', 'dry' => 'Cow Dry', 'parity_1' => 'Parity 1+'] as $key => $label)
            <div class="cbed-card dashboard-status-card p-4"><p class="text-xs font-medium text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-bold text-slate-800">{{ number_format(($key === 'parity_1' ? ($animalSummary['parity_1'] + $animalSummary['parity_2'] + $animalSummary['parity_3'] + $animalSummary['parity_4_plus']) : ($animalSummary[$key] ?? 0))) }}</p></div>
        @endforeach
    </div>
    <div class="mt-3 flex flex-wrap gap-2 text-xs">@foreach(['parity_0' => 'Parity 0', 'parity_1' => 'Parity 1', 'parity_2' => 'Parity 2', 'parity_3' => 'Parity 3', 'parity_4_plus' => 'Parity 4+'] as $key => $label)<span class="rounded-full bg-white px-3 py-2 text-slate-600 shadow-sm">{{ $label }}: <b>{{ number_format($animalSummary[$key] ?? 0) }}</b></span>@endforeach</div>
</section>

<!-- Overview and quick actions -->
<div class="grid grid-cols-1 gap-5 xl:grid-cols-3">

    <section class="cbed-card dashboard-panel overflow-hidden rounded-xl border p-6 xl:col-span-2">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h2 class="font-bold text-slate-800">Program Overview</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Summary of your CBED management system
                </p>
            </div>
            <span class="rounded-lg bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700">
                Initial Setup
            </span>
        </div>

        <div class="flex min-h-48 flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/70 px-5 py-8 text-center">
            <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-2xl text-blue-600 shadow-sm">
                <i class="fa-solid fa-chart-line"></i>
            </div>
            <h3 class="font-semibold text-slate-700">Your dashboard is ready</h3>
            <p class="mt-2 max-w-sm text-sm leading-6 text-slate-500">
                Start adding sample records to the system. Statistics can be connected to your records later.
            </p>
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
    .management-hero { position:relative; isolation:isolate; overflow:hidden; background:radial-gradient(circle at 90% 18%,rgba(255,196,56,.25),transparent 19%),linear-gradient(118deg,#082d50 0%,#075785 55%,#0786a5 100%); box-shadow:0 16px 32px rgba(7,62,99,.2); }
    .management-hero::after { content:"";position:absolute;z-index:-1;width:19rem;height:19rem;right:11%;top:-14rem;border:1px solid rgba(255,214,92,.45);border-radius:50%;box-shadow:0 0 0 26px rgba(255,214,92,.08),0 0 0 54px rgba(255,255,255,.045); }
    .management-hero h1,.management-hero p,.management-hero span { color:#fff!important; }
    .management-hero button,.management-hero a { border-color:rgba(255,255,255,.32)!important;background:rgba(255,255,255,.12)!important;color:#fff!important;backdrop-filter:blur(8px); }
    .management-hero button:hover,.management-hero a:hover { background:rgba(255,255,255,.22)!important; }
    .management-hero button[class*="bg-[#0b1f3a]"] { border-color:#ffd05b!important;background:#ffd05b!important;color:#12345a!important; }
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
