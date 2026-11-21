<dialog id="mtd-entry" aria-labelledby="mtd-entry-title" class="m-auto max-h-[94vh] w-[96vw] max-w-[96rem] overflow-auto rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-950/60"
    x-data="mtdEntry(@js($mtdCooperatives), @js($mtdFarmers), @js($mtdAnimals), @js((string) old('cooperative_id', '')), @js((string) old('farmer_id', '')), @js(old('rows', [])), @js(now()->toDateString()))"
    @if($errors->mtdEntry->any()) x-init="$el.showModal()" @endif>
    <form method="post" action="{{ route('mtd.store') }}">
        @csrf
        <header class="flex items-center justify-between gap-3 border-b p-5">
            <h2 id="mtd-entry-title" class="text-xl font-bold text-[#12345a]">Bulk Add MTD Records</h2>
            <button type="button" onclick="this.closest('dialog').close()" class="cbed-close-button" aria-label="Close bulk MTD entry"><span aria-hidden="true">&times;</span></button>
        </header>
        <div class="space-y-4 p-5">
            @if($errors->mtdEntry->any())
                <ul role="alert" class="rounded-lg bg-rose-50 p-3 text-sm text-rose-800">@foreach($errors->mtdEntry->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach(['cooperative' => 'Cooperative', 'farmer' => 'Farmer'] as $picker => $title)
                    <div class="relative text-sm" @click.outside="if(openPicker==='{{ $picker }}')openPicker=''" @keydown.escape="if(openPicker==='{{ $picker }}'){$event.preventDefault();openPicker='';$el.querySelector('button').focus()}">
                        <span id="mtd-{{ $picker }}-label" class="font-semibold">{{ $title }}</span>
                        <input type="hidden" name="{{ $picker }}_id" :value="{{ $picker }}">
                        <button type="button" aria-labelledby="mtd-{{ $picker }}-label mtd-{{ $picker }}-value" aria-controls="mtd-{{ $picker }}-choices" :aria-expanded="openPicker==='{{ $picker }}'" :disabled="pickerDisabled('{{ $picker }}')" @click="togglePicker('{{ $picker }}', $el.parentElement)" class="filter-input mt-1 flex items-center justify-between gap-2 text-left disabled:opacity-60">
                            <span id="mtd-{{ $picker }}-value" x-text="pickerValue('{{ $picker }}')"></span><i class="fa-solid fa-chevron-down text-xs text-slate-500" aria-hidden="true"></i>
                        </button>
                        <div id="mtd-{{ $picker }}-choices" x-cloak x-show="openPicker==='{{ $picker }}'" class="absolute inset-x-0 z-10 mt-1 rounded-lg border bg-white p-2 shadow-lg">
                            <input type="search" x-model="query" placeholder="Search {{ strtolower($title) }}…" aria-label="Search {{ strtolower($title) }}" class="filter-input" @keydown.enter.prevent="if(matches('{{ $picker }}').length===1)choose('{{ $picker }}', matches('{{ $picker }}')[0], $el.closest('.relative'))">
                            <ul class="mt-1 max-h-40 overflow-y-auto">
                                <template x-for="item in matches('{{ $picker }}')" :key="item.id"><li><button type="button" @click="choose('{{ $picker }}', item, $el.closest('.relative'))" class="w-full rounded-md px-2 py-2 text-left hover:bg-sky-50 focus:bg-sky-50" x-text="item.name"></button></li></template>
                            </ul>
                            <p x-show="!matches('{{ $picker }}').length" class="p-2 text-slate-500">No matches found.</p>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600">Mark a missing teat to cross it out in future reports for this animal. Leave an untested teat blank.</p>
                <button type="button" @click="addRow()" :disabled="rows.length>=100" class="cbed-add-button inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add Row</button>
            </div>
            <div class="overflow-x-auto rounded-lg border" role="region" aria-label="MTD entry rows" tabindex="0">
                <table class="w-full min-w-[90rem] table-fixed text-left text-sm">
                    <colgroup>
                        <col class="w-12"><col class="w-44"><col class="w-24"><col class="w-24"><col class="w-28">
                        <col span="4" class="w-[6.5rem]"><col class="w-36"><col class="w-[19rem]"><col class="w-12">
                    </colgroup>
                    <thead class="bg-slate-100 text-xs uppercase text-slate-600"><tr>
                        @foreach(['No.', 'Animal', 'AM / PM', 'Bulk (kg)', 'Bulk Result', 'RF', 'RB', 'LF', 'LB', 'Date Conducted', 'Remarks', 'Remove'] as $heading)<th scope="col" class="px-2 py-3 {{ $loop->last ? 'text-center' : '' }}">@if($loop->last)<span class="sr-only">{{ $heading }}</span>@else{{ $heading }}@endif</th>@endforeach
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(row, index) in rows" :key="row.key">
                            <tr class="align-top">
                                <td class="p-2"><span class="flex h-11 items-center tabular-nums" x-text="index+1"></span></td>
                                <td class="p-2">
                                    <input type="hidden" :name="'rows['+index+'][teat_availability_set]'" value="1">
                                    <select x-model="row.carabao_id" @change="loadTeatAvailability(row, $event.target.value)" :name="'rows['+index+'][carabao_id]'" :disabled="!farmer" required aria-label="Animal" class="filter-input h-11"><option value="" x-text="!farmer ? 'Select farmer first' : (availableAnimals(row).length ? 'Select animal' : 'No animals available')"></option><template x-for="item in availableAnimals(row)" :key="item.id"><option :value="String(item.id)" x-text="item.name"></option></template></select>
                                </td>
                                <td class="p-2"><select x-model="row.collection_period" :name="'rows['+index+'][collection_period]'" required aria-label="AM or PM" class="filter-input h-11"><option value="">Select</option><option>AM</option><option>PM</option></select></td>
                                <td class="p-2"><input x-model="row.bulk_kg" :name="'rows['+index+'][bulk_kg]'" type="number" min="0" max="999999.99" step="0.01" aria-label="Bulk (kg)" class="filter-input h-11"></td>
                                @foreach(['bulk_result' => 'Bulk Result', 'right_front' => 'Right Front', 'right_back' => 'Right Back', 'left_front' => 'Left Front', 'left_back' => 'Left Back'] as $field => $title)
                                    <td class="p-2">
                                        <select x-model="row.{{ $field }}" :name="'rows['+index+'][{{ $field }}]'" @if($field !== 'bulk_result') :disabled="!row.carabao_id || row.missing_teats.includes('{{ $field }}')" @endif aria-label="{{ $title }}" class="filter-input h-11 disabled:bg-slate-100 disabled:text-slate-400">
                                            <option value="" @if($field !== 'bulk_result') x-text="row.missing_teats.includes('{{ $field }}') ? '× Missing' : '—'" @endif>—</option>
                                            @foreach(\App\Http\Controllers\MtdController::TEST_RESULTS as $result)<option value="{{ $result }}">{{ $result }}</option>@endforeach
                                        </select>
                                        @if($field !== 'bulk_result')
                                            <label class="mt-1 flex h-5 items-center gap-1.5 whitespace-nowrap text-xs text-slate-600">
                                                <input type="checkbox" :name="'rows['+index+'][missing_teats][]'" value="{{ $field }}" :checked="row.missing_teats.includes('{{ $field }}')" :disabled="!row.carabao_id" @change="setMissingTeat(row, '{{ $field }}', $event.target.checked)" aria-label="Mark {{ $title }} teat missing">
                                                Missing
                                            </label>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="p-2"><input x-model="row.test_date" :name="'rows['+index+'][test_date]'" type="date" required aria-label="Date Conducted" class="filter-input h-11"></td>
                                <td class="p-2"><input x-model="row.remarks" :name="'rows['+index+'][remarks]'" maxlength="2000" aria-label="Remarks" class="filter-input h-11"></td>
                                <td class="p-2"><button type="button" @click="rows.splice(index,1)" :disabled="rows.length===1" class="flex h-11 w-full items-center justify-center rounded-lg text-rose-700 hover:bg-rose-50 disabled:opacity-40" aria-label="Remove row"><i class="fa-solid fa-trash" aria-hidden="true"></i></button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
        <footer class="flex justify-end gap-2 border-t bg-slate-50 p-4">
            <button type="button" onclick="this.closest('dialog').close()" class="action-secondary">Cancel</button>
            <button type="submit" :disabled="!cooperative || !farmer" class="cbed-save-button">Save All Records</button>
        </footer>
    </form>
</dialog>
