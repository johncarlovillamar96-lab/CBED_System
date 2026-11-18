@props(['label', 'placeholder', 'items', 'selected', 'onSelect'])

<div x-data="matingSearchPicker()" x-id="['mating-picker-options']"
    @click.outside="open = false"
    @keydown.escape="if (open) { $event.preventDefault(); $event.stopPropagation(); closePicker(); }">
    <button type="button" x-ref="trigger" @click="togglePicker()" aria-label="{{ $label }}"
        :aria-expanded="open" :aria-controls="$id('mating-picker-options')"
        class="form-input flex h-11 items-center justify-between gap-2 text-left">
        <span class="truncate" x-text="({{ $items }}).find(item => String(item.id) === String({{ $selected }}))?.name || @js($placeholder)"></span>
        <i class="fa-solid fa-chevron-down shrink-0 text-xs text-slate-500" aria-hidden="true"></i>
    </button>
    <div x-cloak x-show="open" :id="$id('mating-picker-options')" class="mt-1 rounded-lg border border-slate-200 bg-white p-2 shadow-sm">
        <input type="search" x-ref="search" x-model="query" aria-label="Search {{ strtolower($label) }}"
            placeholder="Search {{ strtolower($label) }}…" class="form-input"
            @keydown.enter.prevent="if (matches({{ $items }}).length === 1) choose(matches({{ $items }})[0].id, {{ $selected }}, {{ $onSelect }})">
        <ul class="mt-1 max-h-40 overflow-y-auto">
            <template x-for="option in matches({{ $items }})" :key="option.id">
                <li>
                    <button type="button" class="w-full rounded-md px-2 py-2 text-left hover:bg-blue-50 focus:bg-blue-50"
                        @click="choose(option.id, {{ $selected }}, {{ $onSelect }})" x-text="option.name"></button>
                </li>
            </template>
        </ul>
        <p x-show="!matches({{ $items }}).length" class="p-2 text-sm text-slate-500">No matches found.</p>
        <button x-show="{{ $selected }}" type="button" class="px-2 py-1 text-xs font-semibold text-slate-600 hover:underline"
            @click="choose('', {{ $selected }}, {{ $onSelect }})">Clear selection</button>
    </div>
</div>
