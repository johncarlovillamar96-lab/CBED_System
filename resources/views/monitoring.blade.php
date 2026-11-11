@extends('layouts.admin')

@section('title', 'Monitoring Management | CBED')
@section('page-heading', 'Monitoring Management')

@section('content')
<div x-data="monitoringPage()" x-init="init()" x-cloak class="management-page monitoring-page min-w-0 bg-slate-50 text-slate-800">

    <div class="management-hero mb-6 flex flex-col gap-4 rounded-2xl p-5 text-white shadow-lg lg:flex-row lg:items-center lg:justify-between sm:p-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">Monitoring Management</h1>
            <p class="mt-1 text-sm text-slate-200">Monitor animals by cooperative, farmer, and date.</p>
        </div>
    </div>

    <div class="mt-6 flex flex-wrap gap-2">
        <button type="button" @click="exportPDF()" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-100"><i class="fa-solid fa-file-pdf text-red-600"></i> PDF Report</button>
        <button type="button" @click="exportCSV()" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-100"><i class="fa-solid fa-file-export"></i> Export CSV</button>
        <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-100"><i class="fa-solid fa-file-import"></i> Import CSV<input type="file" accept=".csv,text/csv" class="hidden" @change="importCSV($event)"></label>
        <button type="button" @click="openBulk()" class="inline-flex items-center gap-2 rounded-lg bg-[#0b1f3a] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#122b4d]"><i class="fa-solid fa-plus"></i> Bulk Add</button>
    </div>

</div>
@endsection
