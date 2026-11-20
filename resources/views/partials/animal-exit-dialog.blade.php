@php
    $exitFormFields = \App\Support\AnimalExitRecord::LABELS;
    $exitDetails = array_intersect_key($exitFormFields, array_flip(['reason_of_exit', 'entrustment', 'ownership_status', 'disposed_to']));
    $disposalFields = array_intersect_key($exitFormFields, array_flip(['disposal_province', 'disposal_municipality', 'disposal_barangay']));
    $exitNatureOptions = ['Dead', 'Culled', 'Sold', 'Transferred', 'Disposed', 'Out'];
@endphp

<dialog id="animal-exit-dialog" x-data="animalExitDialog(@js($exitCooperatives ?? []), @js($exitFarmers ?? []), @js($exitAnimals ?? []), @js($exitFormFields), @js($exitNatureOptions), @js(route('exits.store')), @js(now()->toDateString()), @js($monitoringExit ?? false))"
    @exit-record.window="showExit($event.detail)" @open-monitoring-exit.window="showMonitoringExit($event.detail)" @cancel="if(saving)$event.preventDefault()" aria-labelledby="animal-exit-title"
    class="m-auto max-h-[94vh] w-[96vw] max-w-6xl overflow-auto rounded-2xl bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-950/60">
    <form @submit.prevent="save()">
        <header class="flex items-center justify-between gap-3 border-b p-5">
            <h2 id="animal-exit-title" class="text-xl font-bold text-[#12345a]" x-text="titles[mode]"></h2>
            <button type="button" @click="$root.close()" :disabled="saving" aria-label="Close exit dialog" class="rounded-lg p-2 hover:bg-slate-100"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </header>
        <div class="space-y-4 p-5">
            <p x-show="mode==='delete'" class="text-sm text-slate-600">This permanently deletes the selected exit record.</p>
            <dl x-show="mode!=='add'" class="grid gap-3 rounded-lg bg-slate-50 p-4 text-sm sm:grid-cols-3">
                @foreach(['cooperative_name' => 'Cooperative', 'farmer_name' => 'Farmer', 'animal_identifier' => 'Animal'] as $field => $title)
                    <div><dt class="text-slate-500">{{ $title }}</dt><dd class="break-words font-semibold" x-text="record.{{ $field }} || '—'"></dd></div>
                @endforeach
                <div x-show="mode==='delete'"><dt class="text-slate-500">Exit Date</dt><dd class="font-semibold" x-text="record.exit_date || '—'"></dd></div>
            </dl>
            @unless($monitoringExit ?? false)
            <fieldset x-show="mode==='add'" :disabled="mode!=='add' || saving" class="grid gap-4 sm:grid-cols-3">
                @foreach(['cooperative' => 'Cooperative', 'farmer' => 'Farmer'] as $picker => $title)
                    <div class="relative text-sm" @click.outside="if(openPicker==='{{ $picker }}')openPicker=''" @keydown.escape="if(openPicker==='{{ $picker }}'){$event.preventDefault();openPicker='';$el.querySelector('button').focus()}">
                        <span id="exit-{{ $picker }}-label" class="font-semibold">{{ $title }}</span>
                        <button type="button" aria-labelledby="exit-{{ $picker }}-label exit-{{ $picker }}-value" aria-controls="exit-{{ $picker }}-choices" :aria-expanded="openPicker==='{{ $picker }}'" :disabled="pickerDisabled('{{ $picker }}')" @click="togglePicker('{{ $picker }}', $el.parentElement)" class="filter-input mt-1 flex items-center justify-between gap-2 text-left disabled:opacity-60">
                            <span id="exit-{{ $picker }}-value" x-text="pickerValue('{{ $picker }}')"></span><i class="fa-solid fa-chevron-down text-xs text-slate-500" aria-hidden="true"></i>
                        </button>
                        <div id="exit-{{ $picker }}-choices" x-show="openPicker==='{{ $picker }}'" class="absolute inset-x-0 z-10 mt-1 rounded-lg border bg-white p-2 shadow-lg">
                            <input type="search" x-model="query" placeholder="Search {{ strtolower($title) }}…" aria-label="Search {{ strtolower($title) }}" class="filter-input" @keydown.enter.prevent="if(matches('{{ $picker }}').length===1)choose('{{ $picker }}', matches('{{ $picker }}')[0], $el.closest('.relative'))">
                            <ul class="mt-1 max-h-40 overflow-y-auto"><template x-for="item in matches('{{ $picker }}')" :key="item.id"><li><button type="button" @click="choose('{{ $picker }}', item, $el.closest('.relative'))" class="w-full rounded-md px-2 py-2 text-left hover:bg-sky-50" x-text="item.name"></button></li></template></ul>
                            <p x-show="!matches('{{ $picker }}').length" class="p-2 text-slate-500">No matches found.</p>
                        </div>
                    </div>
                @endforeach
                <label class="text-sm font-semibold">Animal<select x-model="animal" @change="selectAnimal($event.target.value)" :disabled="!farmer" required class="filter-input mt-1"><option value="" x-text="farmer ? 'Select animal' : 'Select a farmer first'"></option><template x-for="item in options('animal')" :key="item.id"><option :value="String(item.id)" x-text="item.name"></option></template></select></label>
            </fieldset>
            @endunless
            <dl x-show="mode==='view'" class="grid gap-4 text-sm sm:grid-cols-3">
                <template x-for="field in fields" :key="field"><div :class="field==='remarks' ? 'sm:col-span-3' : ''"><dt class="text-slate-500" x-text="labels[field]"></dt><dd class="whitespace-pre-wrap break-words font-semibold" x-text="record[field] || '—'"></dd></div></template>
                <div x-show="record.place_of_disposal" class="sm:col-span-3"><dt class="text-slate-500">Imported Disposal Location</dt><dd class="break-words font-semibold" x-text="record.place_of_disposal"></dd></div>
            </dl>
            <fieldset x-show="entryMode" :disabled="!entryMode || saving" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm font-semibold">Exit Date<input type="date" x-model="draft.exit_date" required class="filter-input mt-1"></label>
                    <label class="text-sm font-semibold">Nature of Exit<select x-model="draft.nature_of_exit" required class="filter-input mt-1"><option value="">Select nature of exit</option><template x-for="type in natureOptions" :key="type"><option :value="type" x-text="type"></option></template></select></label>
                    @foreach($exitDetails as $field => $title)
                        <label class="text-sm font-semibold">{{ $title }}<input x-model="draft.{{ $field }}" maxlength="255" class="filter-input mt-1"></label>
                    @endforeach
                </div>
                <div class="rounded-lg border bg-slate-50 p-4">
                    <h3 class="mb-3 font-semibold">Place of Disposal / Transfer</h3>
                    <div class="grid gap-4 sm:grid-cols-3">
                        @foreach($disposalFields as $field => $title)
                            <label class="text-sm font-semibold">{{ $title }}<input x-model="draft.{{ $field }}" maxlength="255" class="filter-input mt-1"></label>
                        @endforeach
                    </div>
                </div>
                <label class="block text-sm font-semibold">Remarks<textarea x-model="draft.remarks" rows="3" maxlength="2000" class="filter-input mt-1"></textarea></label>
            </fieldset>
            <p x-show="error" role="alert" class="rounded-lg bg-rose-50 p-3 text-sm text-rose-800" x-text="error"></p>
        </div>
        <footer class="flex justify-end gap-2 border-t bg-slate-50 p-4">
            <button x-show="mode==='monitoring' && monitoringContext?.hasService" type="button" @click="finishMonitoringExit(null)" class="mr-auto rounded-lg px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Remove service</button>
            <button type="button" x-ref="cancel" @click="$root.close()" :disabled="saving" class="action-secondary" x-text="mode==='view' ? 'Close' : 'Cancel'"></button>
            <button x-show="entryMode" type="submit" :disabled="saving || (mode==='add' && !animal) || !entryMode" class="action-primary disabled:opacity-50" x-text="saving ? 'Saving…' : (mode==='edit' ? 'Update Record' : 'Save Exit')"></button>
            <button x-show="mode==='delete'" type="submit" :disabled="saving || mode!=='delete'" class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800 disabled:opacity-50" x-text="saving ? 'Deleting…' : 'Delete record'"></button>
        </footer>
    </form>
</dialog>
