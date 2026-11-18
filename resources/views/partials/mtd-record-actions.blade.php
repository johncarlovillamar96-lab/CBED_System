<dialog id="mtd-record-dialog" x-data="mtdRecordActions(@js(url('/mtd')), @js(\App\Http\Controllers\MtdController::TEST_RESULTS))" @mtd-record.window="open($event.detail)"
    @cancel="if(saving)$event.preventDefault()" aria-labelledby="mtd-record-dialog-title" class="m-auto max-h-[92vh] w-11/12 max-w-3xl overflow-auto rounded-2xl bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-950/60">
    <form @submit.prevent="save()">
        <header class="flex items-center justify-between gap-3 border-b p-5">
            <h2 id="mtd-record-dialog-title" class="text-xl font-bold text-[#12345a]" x-text="mode==='edit' ? 'Edit MTD Record' : (mode==='delete' ? 'Delete MTD record?' : 'MTD Record Details')"></h2>
            <button type="button" @click="$root.close()" :disabled="saving" class="rounded-lg p-2 hover:bg-slate-100" aria-label="Close record dialog"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </header>
        <div class="space-y-4 p-5">
            <p x-show="mode==='delete'" class="text-sm text-slate-600">This permanently deletes the selected Milk Test Day record.</p>
            <dl class="grid gap-3 rounded-lg bg-slate-50 p-4 text-sm sm:grid-cols-3">
                <template x-for="field in ['cooperative_name','farmer_name','animal_identifier']" :key="field"><div><dt class="text-slate-500" x-text="labels[field]"></dt><dd class="break-words font-semibold" x-text="record[field] || '—'"></dd></div></template>
                <template x-if="mode==='delete'"><div><dt class="text-slate-500">Date Conducted</dt><dd class="font-semibold" x-text="record.test_date || '—'"></dd></div></template>
                <template x-if="mode==='delete'"><div><dt class="text-slate-500">AM / PM</dt><dd class="font-semibold" x-text="record.collection_period || '—'"></dd></div></template>
            </dl>
            <dl x-show="mode==='view'" class="grid gap-4 text-sm sm:grid-cols-3">
                <template x-for="field in fields" :key="field"><div :class="field==='remarks' ? 'sm:col-span-3' : ''"><dt class="text-slate-500" x-text="labels[field]"></dt><dd class="whitespace-pre-wrap break-words font-semibold" x-text="display(field)"></dd></div></template>
            </dl>
            <fieldset x-show="mode==='edit'" :disabled="mode!=='edit' || saving" class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-semibold">Date Conducted<input type="date" x-model="draft.test_date" required class="filter-input mt-1"></label>
                <label class="text-sm font-semibold">AM / PM<select x-model="draft.collection_period" :required="Boolean(record.collection_period)" class="filter-input mt-1"><option value="">Select period</option><option>AM</option><option>PM</option></select></label>
                <label class="text-sm font-semibold">Bulk (kg)<input type="number" x-model="draft.bulk_kg" min="0" max="999999.99" step="0.01" class="filter-input mt-1"></label>
                <template x-for="field in resultFields" :key="field">
                    <label class="text-sm font-semibold"><span x-text="labels[field]"></span>
                        <select x-model="draft[field]" :disabled="isMissing(field)" class="filter-input mt-1 disabled:opacity-50">
                            <option value="" x-text="isMissing(field) ? '× Missing' : '—'"></option>
                            <template x-for="result in results" :key="result"><option :value="result" x-text="result"></option></template>
                        </select>
                    </label>
                </template>
                <label class="text-sm font-semibold sm:col-span-2">Remarks<textarea x-model="draft.remarks" rows="4" maxlength="2000" class="filter-input mt-1"></textarea></label>
            </fieldset>
            <p x-show="error" role="alert" class="rounded-lg bg-rose-50 p-3 text-sm text-rose-800" x-text="error"></p>
        </div>
        <footer class="flex justify-end gap-2 border-t bg-slate-50 p-4">
            <button type="button" x-ref="cancel" @click="$root.close()" :disabled="saving" class="action-secondary" x-text="mode==='view' ? 'Close' : 'Cancel'"></button>
            <button x-show="mode==='edit'" type="submit" :disabled="mode!=='edit' || saving" class="action-primary" x-text="saving ? 'Saving…' : 'Update Record'"></button>
            <button x-show="mode==='delete'" type="submit" :disabled="mode!=='delete' || saving" class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800 disabled:opacity-50" x-text="saving ? 'Deleting…' : 'Delete record'"></button>
        </footer>
    </form>
</dialog>
