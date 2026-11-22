@extends('layouts.admin')

@section('title', 'Activity Logs')

@section('content')
    <x-admin-page-header title="Activity Logs" description="Review activity across admin, technician, and non-admin accounts.">
        <a href="{{ route('ui.activity-logs.monitoring') }}"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Monitoring Visits</a>
    </x-admin-page-header>

    <section aria-label="System activity" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <form method="get" action="{{ route('ui.activity-logs') }}" class="grid gap-4 border-b border-slate-200 bg-slate-50 p-5 sm:grid-cols-2 xl:grid-cols-4">
            <label class="text-sm font-semibold">Search name or route
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-normal">
            </label>
            <label class="text-sm font-semibold">Role
                <select name="role" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-normal">
                    <option value="">All roles</option>
                    @foreach ($roles as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-semibold">Action
                <select name="action" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-normal">
                    <option value="">All actions</option>
                    @foreach ($actions as $action)
                        <option @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex items-end gap-2">
                <button type="submit" class="cbed-save-button">Apply Filters</button>
                <x-reset-filters :href="route('ui.activity-logs')" />
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[48rem] text-left text-sm">
                <thead class="bg-[#103c60] text-xs uppercase text-white">
                    <tr>
                        <th scope="col" class="px-5 py-3">Date and time (SGT)</th>
                        <th scope="col" class="px-5 py-3">Account</th>
                        <th scope="col" class="px-5 py-3">Role</th>
                        <th scope="col" class="px-5 py-3">Action</th>
                        <th scope="col" class="px-5 py-3">Route</th>
                        <th scope="col" class="px-5 py-3">Result</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($entries as $entry)
                        @php
                            $result = match (true) {
                                (bool) $entry->validation_failed => 'Validation failed',
                                (int) $entry->status === 403 => 'Access denied',
                                (int) $entry->status >= 400 => 'HTTP '.$entry->status,
                                default => 'Completed',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-5 py-3">{{ \Illuminate\Support\Carbon::parse($entry->occurred_at, config('app.timezone'))->setTimezone('Asia/Singapore')->format('M j, Y g:i:s A') }}</td>
                            <td class="px-5 py-3"><span class="font-semibold">{{ $entry->actor_name }}</span> <span class="whitespace-nowrap text-xs text-slate-500">#{{ $entry->actor_id }}</span></td>
                            <td class="whitespace-nowrap px-5 py-3">{{ $roles[$entry->actor_role] ?? $entry->actor_role }}</td>
                            <td class="whitespace-nowrap px-5 py-3">{{ $entry->action }}</td>
                            <td class="px-5 py-3">{{ $entry->route }} @if ($entry->target)<span class="block text-xs text-slate-500">{{ $entry->target }}</span>@endif</td>
                            <td class="whitespace-nowrap px-5 py-3">{{ $result }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">No activity matches these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.server-table-pagination', ['paginator' => $entries, 'filterParams' => $filters])
    </section>
@endsection
