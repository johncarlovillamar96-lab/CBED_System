@extends('layouts.admin')

@section('title', 'Activity Logs | CBED')
@section('page-heading', 'Activity Logs')

@section('content')
    <x-admin-page-header title="Activity Logs" section="Monitoring" description="Track saved monitoring visits and open reports to review or print.">
        <a href="{{ route('ui.monitoring') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Monitoring</a>
    </x-admin-page-header>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-label="Saved visits">
        <form method="get" action="{{ route('ui.activity-logs') }}" class="grid grid-cols-1 gap-3 border-b border-slate-200 bg-slate-50 p-5 sm:grid-cols-2 lg:grid-cols-4">
            <label class="text-xs font-semibold text-slate-700">Search cooperative or creator
                <input type="search" name="report_search" value="{{ $filters['report_search'] ?? '' }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
            </label>
            <label class="text-xs font-semibold text-slate-700">Cooperative
                <select name="cooperative_id" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="">All cooperatives</option>
                    @foreach($cooperatives as $cooperative)
                        <option value="{{ $cooperative->id }}" @selected((string) ($filters['cooperative_id'] ?? '') === (string) $cooperative->id)>{{ $cooperative->cooperative_name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-semibold text-slate-700">Visit date
                <input type="date" name="visit_date" value="{{ $filters['visit_date'] ?? '' }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
            </label>
            <label class="text-xs font-semibold text-slate-700">Service
                <select name="report_specification" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="">All services</option>
                    @foreach($specifications as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['report_specification'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-semibold text-slate-700">Availability
                <select name="report_availability" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="">All availability</option>
                    @foreach(['available' => 'Available', 'unavailable' => 'Unavailable'] as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['report_availability'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-semibold text-slate-700">Monitoring status
                <select name="report_status" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="">All statuses</option>
                    @foreach(['completed' => 'Completed', 'pending' => 'Pending', 'unavailable' => 'Unavailable'] as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['report_status'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <input type="hidden" name="per_page" value="{{ $reports->perPage() }}">
            <div class="flex items-end gap-2 lg:col-span-2">
                <button type="submit" class="rounded-lg bg-[#0b1f3a] px-4 py-2 text-sm font-semibold text-white hover:bg-[#12345a]">Apply filters</button>
                <a href="{{ route('ui.activity-logs') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Clear</a>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[750px] text-left text-sm">
                <thead class="bg-[#103c60] text-xs uppercase text-white">
                    <tr>
                        <th scope="col" class="px-5 py-3">Visit date</th>
                        <th scope="col" class="px-5 py-3">Cooperative / Association</th>
                        <th scope="col" class="px-5 py-3">Total / Available / Unavailable</th>
                        <th scope="col" class="px-5 py-3">Services</th>
                        <th scope="col" class="px-5 py-3">Created by</th>
                        <th scope="col" class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($reports as $report)
                        <tr class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-5 py-3">{{ \Carbon\Carbon::parse($report->report_date)->format('F j, Y') }}</td>
                            <td class="px-5 py-3 font-medium text-slate-800">{{ $report->cooperative_name }}</td>
                            <td class="whitespace-nowrap px-5 py-3 tabular-nums">{{ $report->animal_count }} / {{ $report->animal_count - $report->unavailable_count }} / {{ $report->unavailable_count }}</td>
                            <td class="px-5 py-3">{{ $report->service_labels->implode(', ') ?: '—' }}</td>
                            <td class="px-5 py-3">{{ $report->created_by_name ?: '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                <a href="{{ route('ui.monitoring.reports.show', $report->id) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 font-semibold text-slate-700 hover:bg-slate-100"><i class="fa-regular fa-folder-open" aria-hidden="true"></i>Open report</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">No saved visits match the filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.server-table-pagination', ['paginator' => $reports])
    </section>
@endsection
