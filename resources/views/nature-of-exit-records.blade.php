@extends('layouts.admin')

@section('title', 'Nature of Exit | CBED')
@section('page-heading', 'Nature of Exit')

@section('content')
<div class="management-page dashboard-page min-w-0 bg-slate-50 text-slate-800">
    <div class="management-hero mb-7 flex flex-col justify-between gap-4 rounded-2xl p-5 text-white shadow-lg sm:flex-row sm:items-center sm:p-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-white">Nature of Exit</h1>
            <p class="mt-2 text-sm text-slate-200">Review cooperative animal exit records and summaries.</p>
        </div>
        <div class="rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-sm text-white">
            <i class="fa-regular fa-calendar mr-2"></i>{{ now()->format('F d, Y') }}
        </div>
    </div>

    <section class="dashboard-status-section mb-7">
        <div class="mb-3">
            <h2 class="text-lg font-bold text-slate-800">Summary by Nature of Exit</h2>
            <p class="mt-1 text-sm text-slate-500">Total records for each exit category.</p>
        </div>
        @if ($summaryCards->isNotEmpty())
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($summaryCards as $card)
                    <div class="cbed-card dashboard-stat-card management-summary-card p-5">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-slate-500">{{ $card->nature ?: 'Not specified' }}</p>
                                <p class="mt-3 text-3xl font-bold text-slate-800">{{ number_format($card->total) }}</p>
                                <p class="mt-2 text-xs text-slate-400">Exit record(s)</p>
                            </div>
                            <div class="dashboard-stat-icon flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-xl text-blue-600">
                                <i class="fa-solid fa-arrow-right-from-bracket text-xl"></i>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="cbed-card rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500">No exit records to summarize.</p>
        @endif
    </section>

    <section class="dashboard-status-section mb-7">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-slate-800">Nature of Exit Records</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $rows->total() }} record(s)</p>
            </div>
            <form method="GET" action="{{ route('ui.nature-of-exit') }}" class="flex gap-2">
                <label for="exit-search" class="sr-only">Search exit records</label>
                <input id="exit-search" type="search" name="search" value="{{ $search }}"
                    placeholder="Search cooperative, farmer, registry..."
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm sm:w-80">
                <button type="submit" class="rounded-lg bg-[#0b1f3a] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#122b4d]">
                    Search
                </button>
            </form>
        </div>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">No.</th>
                        <th class="px-4 py-3 font-semibold">Cooperative</th>
                        <th class="px-4 py-3 font-semibold">Farmer</th>
                        <th class="px-4 py-3 font-semibold">Registry</th>
                        <th class="px-4 py-3 font-semibold">Exit Date</th>
                        <th class="px-4 py-3 font-semibold">Nature of Exit</th>
                        <th class="px-4 py-3 font-semibold">Remarks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse ($rows as $index => $record)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-500">{{ $rows->firstItem() + $index }}</td>
                            <td class="px-4 py-3">{{ $record->cooperative_name ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $record->farmer_name ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-700">{{ $record->registry_number ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $record->exit_date ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $record->nature_of_exit ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $record->remarks ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-500">No Nature of Exit records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            {{ $rows->links() }}
        </div>
    </section>
</div>
@endsection

@push('styles')
<style>
    .management-page { background: radial-gradient(circle at 92% 0%,rgba(11,31,58,.06),transparent 25%),#f5f8fc; }
    .management-hero { position:relative; isolation:isolate; overflow:hidden; background:radial-gradient(circle at 90% 18%,rgba(255,196,56,.25),transparent 19%),linear-gradient(118deg,#082d50 0%,#075785 55%,#0786a5 100%); box-shadow:0 16px 32px rgba(7,62,99,.2); }
    .management-hero::after { content:"";position:absolute;z-index:-1;width:19rem;height:19rem;right:11%;top:-14rem;border:1px solid rgba(255,214,92,.45);border-radius:50%;box-shadow:0 0 0 26px rgba(255,214,92,.08),0 0 0 54px rgba(255,255,255,.045); }
    .management-page .management-summary-card { border-top:4px solid #0b5e8b!important;border-radius:1rem!important;box-shadow:0 5px 18px rgba(15,45,67,.06)!important;transition:transform .16s ease,box-shadow .16s ease; }
    .management-page .management-summary-card:nth-child(2) { border-top-color:#e87523!important; }
    .management-page .management-summary-card:nth-child(3) { border-top-color:#25835b!important; }
    .management-page .management-summary-card:nth-child(4) { border-top-color:#0786a5!important; }
    .management-page .management-summary-card:hover { transform:translateY(-3px);box-shadow:0 14px 28px rgba(7,62,99,.13)!important; }
    .management-page .management-summary-card .dashboard-stat-icon { background:linear-gradient(145deg,#082d50,#0b6d9b)!important;color:#fff!important;border-radius:1rem;box-shadow:0 8px 18px rgba(7,62,99,.2); }
    .management-page .management-summary-card:nth-child(2) .dashboard-stat-icon { background:linear-gradient(145deg,#a94b0b,#f28a32)!important; }
    .management-page .management-summary-card:nth-child(3) .dashboard-stat-icon { background:linear-gradient(145deg,#176342,#38a36f)!important; }
    .management-page .management-summary-card:nth-child(4) .dashboard-stat-icon { background:linear-gradient(145deg,#075785,#0798b6)!important; }
    .dashboard-status-section h2 { color:#0b1f3a; }
    .dashboard-status-section > .overflow-x-auto { border-color:#dce6ed!important;border-radius:1rem!important;box-shadow:0 7px 24px rgba(15,45,67,.06)!important; }
</style>
@endpush
