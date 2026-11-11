@extends('layouts.admin')

@section('title', 'Monitoring | CBED')
@section('page-heading', 'Monitoring')

@section('content')
<div class="management-page monitoring-page min-w-0 bg-slate-50 text-slate-800">
    <div class="management-hero mb-7 flex flex-col justify-between gap-4 rounded-2xl p-5 text-white shadow-lg sm:flex-row sm:items-center sm:p-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">Monitoring</h1>
            <p class="mt-2 text-sm text-slate-200">Review animal health monitoring records by cooperative, farmer, registry ID, and date.</p>
        </div>
        <div class="rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-sm text-white">
            <i class="fa-regular fa-calendar mr-2"></i>{{ now()->format('F j, Y') }}
        </div>
    </div>

    <div class="mb-7 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['title' => 'Monitoring Records', 'value' => $counts['records'], 'description' => 'Total saved records', 'icon' => 'fa-clipboard-check', 'iconClass' => 'bg-blue-50 text-blue-600'],
            ['title' => 'Animals Monitored', 'value' => $counts['animals'], 'description' => 'Unique linked animals', 'icon' => 'fa-cow', 'iconClass' => 'bg-emerald-50 text-emerald-600'],
            ['title' => 'Upcoming Follow-ups', 'value' => $counts['follow_ups'], 'description' => 'Due today or later', 'icon' => 'fa-calendar-check', 'iconClass' => 'bg-orange-50 text-orange-600'],
            ['title' => 'Cooperatives', 'value' => $counts['cooperatives'], 'description' => 'Represented in records', 'icon' => 'fa-people-group', 'iconClass' => 'bg-violet-50 text-violet-600'],
        ] as $card)
            <div class="cbed-card dashboard-stat-card management-summary-card p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">{{ $card['title'] }}</p>
                        <h3 class="mt-3 text-3xl font-bold text-slate-800">{{ number_format($card['value']) }}</h3>
                        <p class="mt-2 text-xs text-slate-400">{{ $card['description'] }}</p>
                    </div>
                    <div class="dashboard-stat-icon flex h-12 w-12 items-center justify-center rounded-xl text-xl {{ $card['iconClass'] }}">
                        <i class="fa-solid {{ $card['icon'] }}"></i>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <section class="cbed-card dashboard-panel overflow-hidden rounded-xl border bg-white">
        <div class="flex flex-col gap-3 border-b p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-bold text-slate-800">Health Monitoring Records</h2>
                <p class="mt-1 text-xs text-slate-500">Animal records retrieved from the CBED database.</p>
            </div>
            <span class="rounded-lg bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700">{{ number_format($rows->total()) }} records</span>
        </div>

        <form method="GET" action="{{ route('ui.monitoring') }}" class="flex flex-col gap-3 border-b bg-slate-50 p-5 sm:flex-row">
            <input type="search" name="search" value="{{ $search }}" placeholder="Search cooperative, farmer, registry ID, or status"
                   class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#0b1f3a] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#122b4d]">
                <i class="fa-solid fa-magnifying-glass"></i> Search
            </button>
            @if($search !== '')
                <a href="{{ route('ui.monitoring') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">Clear</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1050px] text-left text-sm">
                <thead class="bg-slate-100 text-xs uppercase text-slate-600">
                    <tr>
                        <th class="px-4 py-3">No.</th>
                        <th class="px-4 py-3">Cooperative</th>
                        <th class="px-4 py-3">Farmer</th>
                        <th class="px-4 py-3">Registry ID</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Health Status</th>
                        <th class="px-4 py-3">Findings</th>
                        <th class="px-4 py-3">Treatment</th>
                        <th class="px-4 py-3">Follow-up Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        <tr class="hover:bg-blue-50/50">
                            <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $rows->firstItem() + $loop->index }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $row->cooperative_name ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $row->farmer_name ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $row->registry_id ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $row->monitoring_date ? \Carbon\Carbon::parse($row->monitoring_date)->format('F j, Y') : '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $row->health_status ?: '—' }}</td>
                            <td class="max-w-xs px-4 py-3">{{ $row->findings ?: '—' }}</td>
                            <td class="max-w-xs px-4 py-3">{{ $row->treatment ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $row->follow_up_date ? \Carbon\Carbon::parse($row->follow_up_date)->format('F j, Y') : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-5 py-12 text-center text-slate-500">
                                {{ $search !== '' ? 'No monitoring records match your search.' : 'No monitoring records found.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($rows->hasPages())
            <div class="border-t p-5">{{ $rows->links() }}</div>
        @endif
    </section>
</div>
@endsection
