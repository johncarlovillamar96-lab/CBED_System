<div x-show="editingId">
    <p x-show="!bulkRows.length" role="status" class="py-8 text-center text-sm text-slate-500">Loading record…</p>
    <template x-for="r in (editingId?bulkRows:[])" :key="r._key">
        <div class="space-y-5">
            <fieldset class="rounded-xl border border-slate-200 p-4">
                <legend class="px-2 text-sm font-bold text-slate-800">Animal &amp; Mating</legend>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="text-sm font-medium text-slate-700">Farmer
                        <input readonly :value="r.farmer || farmers.find(f=>String(f.id)===String(r.farmer_id))?.name || ''" class="form-input mt-1 bg-slate-50">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Animal ID *
                        <select x-show="animalsForFarmer(r).length" x-model="r.carabao_id" @change="animalChanged(r)" class="form-input mt-1">
                            <option value="">Select female animal</option>
                            <template x-for="a in animalsForFarmer(r)" :key="a.id"><option :value="a.id" x-text="a.label"></option></template>
                        </select>
                        <input x-show="!animalsForFarmer(r).length" x-model.trim="r.animal_id" class="form-input mt-1" placeholder="Animal ID">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Mating Date *<input type="date" x-model="r.breeding_date" @change="dateChanged(r)" class="form-input mt-1"></label>
                    <label class="text-sm font-medium text-slate-700">Mating Type *
                        <select x-model="r.mating_type" @change="typeChanged(r)" class="form-input mt-1"><option value="AI">Artificial Insemination (AI)</option><option value="NM">Natural Mating (NM)</option></select>
                    </label>
                    <label class="text-sm font-medium text-slate-700">Bull ID
                        <input x-model="r.bull_id" @input="matchBullField(r,'bull_id')" list="editMatingBullOptions" class="form-input mt-1" placeholder="Select or enter Bull ID">
                        <datalist id="editMatingBullOptions"><template x-for="b in semenBulls" :key="b.id"><option :value="b.bull_id" :label="b.bull_name||b.bull_id"></option></template></datalist>
                    </label>
                    <label class="text-sm font-medium text-slate-700">Bull Name<input x-model="r.bull_name" @input="matchBullField(r,'bull_name')" class="form-input mt-1" placeholder="Optional"></label>
                    <label x-show="r.mating_type==='AI'" class="text-sm font-medium text-slate-700">Straws<input type="number" min="0" step="1" x-model="r.straws" class="form-input mt-1"></label>
                    <label x-show="r.mating_type==='AI'" class="text-sm font-medium text-slate-700">Estrus / Heat *
                        <select x-model="r.heat_status" class="form-input mt-1"><option value="">Select</option><option>ES</option><option>NH</option><option>FTAI</option></select>
                    </label>
                    <label class="text-sm font-medium text-slate-700 sm:col-span-2">Mating Technician<input x-model.trim="r.mating_technician" class="form-input mt-1" placeholder="Technician name"></label>
                </div>
            </fieldset>

            <fieldset class="rounded-xl border border-slate-200 p-4">
                <legend class="px-2 text-sm font-bold text-slate-800">Pregnancy Diagnosis</legend>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="text-sm font-medium text-slate-700">PD Date<input type="date" x-model="r.pd_date" @change="pdDateChanged(r)" class="form-input mt-1"></label>
                    <label class="text-sm font-medium text-slate-700">PD Result<select x-model="r.pd_result" @change="pdChanged(r)" class="form-input mt-1"><option>Pending</option><option>Negative</option><option>Positive</option></select></label>
                    <label x-show="r.pd_result==='Positive'" class="text-sm font-medium text-slate-700">Months of Pregnancy *<input type="number" min="1" max="11" step="1" x-model="r.pregnancy_months" class="form-input mt-1"></label>
                    <label x-show="r.pd_result!=='Pending'" class="text-sm font-medium text-slate-700">PD Technician *<input x-model.trim="r.pd_technician" class="form-input mt-1" placeholder="Confirming technician"></label>
                </div>
            </fieldset>

            <fieldset class="rounded-xl border border-slate-200 p-4">
                <legend class="px-2 text-sm font-bold text-slate-800">Calving &amp; Notes</legend>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label x-show="r.pd_result==='Positive'" class="text-sm font-medium text-slate-700">Expected Calving<input type="date" x-model="r.expected_calving" class="form-input mt-1"></label>
                    <label class="text-sm font-medium text-slate-700">Actual Calving<input type="date" x-model="r.actual_calving" class="form-input mt-1"></label>
                    <label class="text-sm font-medium text-slate-700 sm:col-span-2">Remarks<textarea x-model="r.remarks" rows="2" class="form-input mt-1" placeholder="Optional notes"></textarea></label>
                </div>
            </fieldset>

            <details class="rounded-xl bg-slate-50 p-4 text-sm">
                <summary class="cursor-pointer font-semibold text-slate-700">Calculated follow-up dates</summary>
                <dl class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div><dt class="text-xs text-slate-500">2nd Cycle (18–21 Days)</dt><dd class="mt-1 font-medium" x-text="secondCycleRange(r.breeding_date)"></dd></div>
                    <div><dt class="text-xs text-slate-500">Expected PD (60 Days)</dt><dd class="mt-1 font-medium" x-text="expectedPDDate(r.breeding_date,60)"></dd></div>
                    <div><dt class="text-xs text-slate-500">Expected PD (90 Days)</dt><dd class="mt-1 font-medium" x-text="expectedPDDate(r.breeding_date,90)"></dd></div>
                </dl>
            </details>
        </div>
    </template>
</div>
