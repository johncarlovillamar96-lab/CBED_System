@if ($periods)
    <section class="border-b border-slate-200 p-5" aria-labelledby="activity-periods">
        <h2 id="activity-periods" class="mb-3 text-base font-semibold">{{ ucfirst($period) }} accomplishments</h2>
        @foreach ($periods as $date => $totals)
            <details class="mb-2 rounded-lg border border-slate-200 p-3" @if($printReport ?? false) open @endif>
                <summary class="cursor-pointer font-semibold">{{ $date }} · {{ $totals->activities }} {{ \Illuminate\Support\Str::plural('activity', $totals->activities) }}</summary>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                    @foreach ($totals->metrics() as $name => $count)
                        @if ($count > 0 && $name !== 'Total Activities')
                            <li>{{ $name }} · {{ number_format($count) }}</li>
                        @endif
                    @endforeach
                    @foreach ($totals->serviceStatements() as $statement)
                        <li>{{ $statement }}</li>
                    @endforeach
                </ul>
            </details>
        @endforeach
    </section>
@endif
