<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Monitoring Calendar Report | {{ $calendarYear }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f7fb; color: #17263a; font-size: 14px; line-height: 1.5; }
        .container { max-width: 1440px; margin: 0 auto; padding-inline: clamp(16px, 3vw, 32px); }
        .report-toolbar { position: sticky; top: 0; z-index: 1; background: #fff; border-bottom: 1px solid #dbe3ed; }
        .toolbar-inner, .report-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; }
        .toolbar-inner { justify-content: space-between; gap: 8px; padding-block: 8px; }
        .report-actions { gap: 6px; }
        .back-link, .print-button, .month-select { display: inline-flex; align-items: center; gap: 6px; min-height: 34px; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 9px; font: inherit; font-size: 12px; font-weight: 600; }
        .back-link { background: white; color: #12345a; text-decoration: none; }
        .back-link:hover { background: #eef3f8; border-color: #94a3b8; }
        .print-button { border-color: #0b1f3a; background: #0b1f3a; color: white; cursor: pointer; }
        .print-button:hover { background: #122b4d; }
        .month-select { background: white; color: #17263a; }
        .toolbar-inner :focus-visible { outline: 2px solid #2563eb; outline-offset: 3px; }
        main { padding-block: 24px; }
        .report-intro { margin-bottom: 20px; border-radius: 12px; background: #0b1f3a; padding: clamp(20px, 4vw, 32px); color: white; }
        .report-intro h1 { margin: 0; font-size: clamp(24px, 4vw, 32px); line-height: 1.2; }
        .report-intro p { margin: 8px 0 0; color: #cbd8e8; }
        .month { scroll-margin-top: 64px; margin-bottom: 18px; overflow: hidden; border: 1px solid #dbe3ed; border-radius: 12px; background: white; }
        .month-heading { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; }
        .month-heading h2 { margin: 0; font-size: 21px; line-height: 1.2; }
        .print-context { display: none; }
        .empty-month { margin: 0; padding: 16px 20px; color: #475569; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { padding: 10px 20px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; overflow-wrap: anywhere; }
        th { background: #f8fafc; color: #475569; font-size: 12px; }
        th:first-child { width: 18%; }
        th:nth-child(2) { width: 34%; }
        tbody tr:last-child td { border-bottom: 0; }
        tbody tr:nth-child(even) { background: #fafbfd; }
        td time { font-variant-numeric: tabular-nums; color: #475569; }
        .cooperative-name { font-weight: 600; }
        .services { display: flex; flex-wrap: wrap; gap: 5px; margin: 0; padding: 0; list-style: none; }
        .services li { border-radius: 5px; padding: 2px 7px; background: #edf3fa; color: #12345a; font-size: 12px; }
        .services strong { margin-inline-start: 3px; font-variant-numeric: tabular-nums; }
        @media screen {
            body { zoom: .8; }
            .container { max-width: calc(1440px / .8); }
        }
        @media screen and (max-width: 600px) {
            .month { scroll-margin-top: 104px; }
            th, td { padding-inline: 8px; }
            th:first-child { width: 24%; }
            th:nth-child(2) { width: 33%; }
        }
        @page { size: A4 portrait; margin: 14mm; }
        @media print {
            body { background: white; color: black; font-size: 10pt; }
            .report-toolbar, .report-intro { display: none; }
            .container { max-width: none; padding: 0; }
            .month { margin: 0; border: 0; border-radius: 0; overflow: visible; }
            .month + .month { break-before: page; }
            .month-heading { padding: 0 0 10pt; border: 0; }
            .print-context { display: block; margin: 0 0 3pt; }
            .empty-month { padding: 0; }
            th, td { border: 1px solid #cbd5e1; padding: 5pt 6pt; color: black; }
            .services { display: block; }
            .services li { padding: 0; background: transparent; color: black; font-size: inherit; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; }
        }
    </style>
    @include('partials.typography')
</head>
<body>
    <nav class="report-toolbar" aria-label="Report actions">
        <div class="container toolbar-inner">
            <a class="back-link" href="{{ route('ui.monitoring', request()->only(['cooperative_id', 'report_search']) + ['view' => 'year']) }}"><span aria-hidden="true">&larr;</span> Monitoring Calendar</a>
            <div class="report-actions">
                <select class="month-select" aria-label="Jump to month" onchange="if (this.value) document.getElementById(this.value).scrollIntoView()">
                    <option value="">Jump to month</option>
                    @for ($month = 1; $month <= 12; $month++)
                        <option value="month-{{ $month }}">{{ \Carbon\Carbon::create($calendarYear, $month, 1)->format('F') }}</option>
                    @endfor
                </select>
                <button class="print-button" type="button" onclick="window.print()">Print / Save PDF</button>
            </div>
        </div>
    </nav>
    <main class="container">
        <header class="report-intro">
            <h1>Monitoring Calendar Report</h1>
            <p>{{ $calendarYear }} &middot; {{ $calendarCooperative ?: 'All cooperatives' }}@if ($reportSearch !== '') &middot; Search: {{ $reportSearch }}@endif</p>
        </header>
        @for ($month = 1; $month <= 12; $month++)
            @php
                $monthKey = sprintf('%04d-%02d', $calendarYear, $month);
                $days = $calendarMonths->get($monthKey, collect());
            @endphp
            <section id="month-{{ $month }}" class="month" aria-labelledby="month-title-{{ $month }}">
                <header class="month-heading">
                    <p class="print-context">Monitoring Calendar Report</p>
                    <h2 id="month-title-{{ $month }}">{{ \Carbon\Carbon::create($calendarYear, $month, 1)->format('F Y') }}</h2>
                    @if ($calendarCooperative)
                        <p class="print-context"><strong>Cooperative:</strong> {{ $calendarCooperative }}</p>
                    @endif
                    @if ($reportSearch !== '')
                        <p class="print-context"><strong>Cooperative search:</strong> {{ $reportSearch }}</p>
                    @endif
                </header>
                @if ($days->isEmpty())
                    <p class="empty-month">No services were recorded for this month.</p>
                @else
                    <table>
                        <thead>
                            <tr><th scope="col">Date</th><th scope="col">Cooperative</th><th scope="col">Services</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($days as $date => $cooperatives)
                                @foreach ($cooperatives as $cooperative => $services)
                                    <tr>
                                        <td><time datetime="{{ $date }}">{{ \Carbon\Carbon::parse($date)->format('M j, Y') }}</time></td>
                                        <td class="cooperative-name">{{ $cooperative }}</td>
                                        <td>
                                            <ul class="services">
                                                @foreach ($services as $service => $count)
                                                    <li>{{ $service }} <strong>{{ number_format($count) }}</strong></li>
                                                @endforeach
                                            </ul>
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        @endfor
    </main>
</body>
</html>
