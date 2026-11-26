@extends('layouts.admin')

@section('title', 'Activity Logs')

@section('content')
    <x-admin-page-header title="Activity Logs" description="Review saved transactions and accomplishments.">
        <a href="{{ route('ui.activity-logs.monitoring') }}"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Monitoring Visits</a>
    </x-admin-page-header>

    @include('partials.activity-metrics')

    <section aria-label="System activity" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <form method="get" action="{{ route('ui.activity-logs') }}" data-auto-filters class="grid gap-4 border-b border-slate-200 bg-slate-50 p-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (['from' => ['From date', 'date'], 'to' => ['To date', 'date'], 'year' => ['Year', 'number'], 'month' => ['Month (1-12)', 'number'], 'animal' => ['Animal ID', 'search'], 'search' => ['Search activity descriptions', 'search']] as $field => [$label, $type])
                <label class="text-sm font-semibold">{{ $label }}
                    <input type="{{ $type }}" name="{{ $field }}" value="{{ $filters[$field] ?? '' }}" @if($field === 'month') min="1" max="12" @elseif($field === 'year') min="1900" max="9998" @endif class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-normal">
                </label>
            @endforeach
            @foreach (['user' => 'User', 'cooperative' => 'Cooperative', 'module' => 'Module', 'action' => 'Action', 'role' => 'Role', 'period' => 'Accomplishment period'] as $field => $label)
                <label class="text-sm font-semibold">{{ $label }}
                    <select name="{{ $field }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-normal">
                        @if ($field !== 'period')
                            <option value="">All</option>
                        @endif
                        @foreach ($options[$field] as $value => $text)
                            <option value="{{ $value }}" @selected((string) ($filters[$field] ?? '') === (string) $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                </label>
            @endforeach
            <div class="flex flex-wrap items-center gap-3 lg:col-span-4">
                <x-reset-filters :href="route('ui.activity-logs')" />
                <button class="rounded-lg border bg-white px-3 py-2 text-sm font-semibold" name="format" value="excel">Export Excel</button>
                <button class="rounded-lg border bg-white px-3 py-2 text-sm font-semibold" name="format" value="print" formtarget="_blank">Printable Report</button>
            </div>
        </form>

        @include('partials.activity-periods')

        <div class="overflow-x-auto">
            <table class="w-full min-w-[54rem] text-left text-sm">
                <thead class="bg-[#103c60] text-xs uppercase text-white">
                    <tr>
                        @foreach (['No.', 'Date and time (Asia/Manila)', 'User', 'First Name', 'Last Name', 'Module', 'Action', 'Cooperative', 'Activity Description'] as $heading)
                            <th scope="col" class="px-4 py-3">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($entries as $entry)
                        @php($details = $entry->details ?? [])
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">{{ $entries->firstItem() + $loop->index }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $entry->localTime()->format('M j, Y g:i:s A') }}</td>
                            <td class="px-4 py-3">{{ $entry->actor_name }} <span class="text-xs text-slate-500">#{{ $entry->actor_id }}</span></td>
                            <td class="px-4 py-3">{{ $entry->actor_first_name ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $entry->actor_last_name ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $entry->module ?? 'System' }}</td>
                            <td class="px-4 py-3">{{ $entry->action }}</td>
                            <td class="px-4 py-3">{{ implode(', ', $details['cooperatives'] ?? []) ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="whitespace-pre-line">{{ $entry->activityDescription() }}</div>
                                <button type="button" class="mt-1 block text-sm font-semibold text-blue-800 underline" aria-haspopup="dialog"
                                    onclick="document.getElementById('activity-{{ $entry->id }}').showModal()">View Details</button>
                                @include('partials.activity-details')
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-5 py-10 text-center text-slate-500">No activity matches these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.server-table-pagination', ['paginator' => $entries, 'filterParams' => $filters])
    </section>
@endsection
