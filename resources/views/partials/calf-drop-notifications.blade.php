@php($overdueAnimalCount = $calfDropNotifications->sum('count'))
<div class="{{ ($inline ?? false) ? 'ml-auto flex shrink-0 justify-end' : 'mb-4 flex justify-end' }}">
    <details class="relative z-40">
        <summary aria-label="Calf-drop notifications: {{ $overdueAnimalCount }} overdue animal(s)" title="Calf-drop notifications" class="flex cursor-pointer list-none items-center gap-2 rounded-xl border px-4 py-2 text-sm font-semibold shadow-sm {{ $overdueAnimalCount ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-slate-200 bg-white text-slate-600' }}">
            <i class="fa-solid fa-bell" aria-hidden="true"></i> @unless($inline ?? false) Calf-drop notifications @endunless
            <span class="rounded-full bg-white px-2 py-0.5 tabular-nums">{{ $overdueAnimalCount }}</span>
        </summary>
        <div class="absolute right-0 mt-2 max-h-[70vh] w-[min(90vw,28rem)] overflow-auto rounded-xl border border-slate-200 bg-white p-4 shadow-xl">
            <h2 class="font-bold text-slate-900">Calf drop not reported</h2>
            <p class="mt-1 text-xs text-slate-500">Latest positive PD, more than 300 pregnancy days, and no matching calf report. Each animal is counted once.</p>
            @forelse($calfDropNotifications as $notification)
                <div class="mt-3 rounded-lg border border-amber-100 bg-amber-50 p-3">
                    <a class="font-semibold text-blue-800 hover:underline" href="{{ url('/mating').'?'.http_build_query(['overdue_calves'=>1,'cooperative_id'=>$notification['cooperative_id']]) }}">{{ $notification['cooperative_name'] }}</a>
                    <p class="mt-1 text-xs font-semibold text-amber-900">{{ $notification['count'] }} animal(s) awaiting a calf-drop report</p>
                    <ul class="mt-2 space-y-1 text-xs text-slate-700">
                        @foreach($notification['animals'] as $animal)
                            <li><a class="text-blue-800 hover:underline" href="{{ url('/mating').'?'.http_build_query(['overdue_calves'=>1,'cooperative_id'=>$notification['cooperative_id'],'animal_id'=>$animal['animal_id']]) }}">{{ $animal['animal_id'] }}</a> · {{ $animal['days'] }} days</li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <p class="mt-4 text-sm text-emerald-700">No overdue unreported calf drops.</p>
            @endforelse
        </div>
    </details>
</div>
