<dl class="activity-metrics mb-5 grid grid-cols-2 gap-3 lg:grid-cols-6">
    @foreach ($metrics as $label => $value)
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <dt class="text-sm text-slate-600">{{ $label }}</dt>
            <dd class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format($value) }}</dd>
        </div>
    @endforeach
</dl>
