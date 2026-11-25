<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activity and Accomplishment Report</title>
    <style>
        body { color: #172435; font: 14px/1.5 system-ui, sans-serif; margin: 2rem auto; max-width: 76rem; padding: 0 1rem; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border-bottom: 1px solid #cbd5e1; padding: .45rem; text-align: left; vertical-align: top; }
        dt, summary { font-weight: 600; }
        dd { margin-left: 0; }
        .activity-metrics { display: flex; flex-wrap: wrap; gap: .75rem 1.5rem; }
        @media print { .print-button { display: none; } body { margin: 0; max-width: none; } tr { break-inside: avoid; } }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">Print Report</button>
    <h1>Activity and Accomplishment Report</h1>
    @include('partials.activity-metrics')
    @include('partials.activity-periods', ['printReport' => true])

    <h2>Filtered activities</h2>
    <table>
        <thead><tr><th>No.</th><th>Date and time (Asia/Manila)</th><th>User</th><th>First Name</th><th>Last Name</th><th>Module</th><th>Action</th><th>Cooperative</th><th>Activity Description</th></tr></thead>
        <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td>{{ $loop->iteration }}</td><td>{{ $entry->localTime()->format('M j, Y g:i A') }}</td>
                    <td>{{ $entry->actor_name }} (#{{ $entry->actor_id }})</td>
                    <td>{{ $entry->actor_first_name ?: '—' }}</td><td>{{ $entry->actor_last_name ?: '—' }}</td>
                    <td>{{ $entry->module ?? 'System' }}</td><td>{{ $entry->action }}</td>
                    <td>{{ implode(', ', $entry->details['cooperatives'] ?? []) }}</td><td style="white-space: pre-line">{{ $entry->activityDescription() }}</td>
                </tr>
            @empty
                <tr><td colspan="9">No activity matches these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
