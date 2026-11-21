@if (filled($recordDate))
    <time datetime="{{ $recordDate }}" class="block text-xs text-slate-500">{{ \Carbon\Carbon::parse($recordDate)->format('M j, Y') }}</time>
@endif
