@props(['id', 'label', 'name' => null])

<div x-data="monitoringPicker()" @click.outside="open = false"
    @keydown.escape="if (open) { $event.preventDefault(); $event.stopPropagation(); closePicker(); }"
    class="relative min-w-0 text-sm">
    <span id="{{ $id }}-label" class="font-semibold">{{ $label }}</span>
    <select id="{{ $id }}" @if ($name) name="{{ $name }}" @endif x-ref="select" hidden>
        {{ $slot }}
    </select>
    <button type="button" x-ref="trigger" :disabled="options.length <= 1"
        aria-labelledby="{{ $id }}-label {{ $id }}-value" aria-controls="{{ $id }}-options"
        :aria-expanded="open" @click="togglePicker()"
        class="mt-1 flex min-h-10 w-full items-center justify-between gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-left disabled:bg-slate-50 disabled:text-slate-500">
        <span id="{{ $id }}-value" class="truncate" x-text="selectedLabel"></span>
        <i class="fa-solid fa-chevron-down shrink-0 text-xs text-slate-500" aria-hidden="true"></i>
    </button>
    <div id="{{ $id }}-options" x-cloak x-show="open" class="absolute inset-x-0 z-30 mt-1 rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
        <input type="search" x-ref="search" x-model="search" aria-label="Search {{ strtolower($label) }}"
            placeholder="Search {{ strtolower($label) }}…" @keydown.enter.prevent="if (matches.length === 1) choose(matches[0].value)"
            class="w-full rounded-lg border border-slate-300 px-3 py-2">
        <ul class="mt-1 max-h-40 overflow-y-auto">
            <template x-for="option in matches" :key="option.value">
                <li><button type="button" class="w-full rounded-md px-2 py-2 text-left hover:bg-sky-50 focus:bg-sky-50" @click="choose(option.value)" x-text="option.label"></button></li>
            </template>
        </ul>
        <p x-show="matches.length === 0" class="p-2 text-slate-500">No matches found.</p>
    </div>
</div>
