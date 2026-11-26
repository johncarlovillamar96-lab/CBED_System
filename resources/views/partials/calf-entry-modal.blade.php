    <div x-cloak x-show="showAdd" x-transition.opacity class="shared-calf-modal fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-5">
        <form @submit.prevent="saveCalf()" role="dialog" aria-modal="true" aria-labelledby="add-calf-title" class="flex max-h-[94vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <header class="flex shrink-0 items-center justify-between border-b border-slate-200 p-5">
                <h3 id="add-calf-title" class="text-xl font-bold text-slate-900">Add Calf Record</h3>
                <button type="button" @click="closeCalfForm()" :disabled="savingCalf" aria-label="Close add calf form" class="cbed-close-button"><span aria-hidden="true">&times;</span></button>
            </header>
            <div class="min-h-0 overflow-y-auto p-4 sm:p-5">
                <div class="mb-4 flex flex-wrap gap-2" role="group" aria-label="Calf entry method">
                    <button type="button" @click="setEntryMode(true)" :aria-pressed="showMatingPicker" :disabled="savingCalf" class="rounded-lg border px-3 py-2 text-sm font-semibold" :class="showMatingPicker ? 'border-sky-700 bg-sky-50 text-sky-900' : 'border-slate-200 text-slate-600'">Use a mating record</button>
                    <button type="button" @click="setEntryMode(false)" :aria-pressed="!showMatingPicker" :disabled="savingCalf" class="rounded-lg border px-3 py-2 text-sm font-semibold" :class="!showMatingPicker ? 'border-sky-700 bg-sky-50 text-sky-900' : 'border-slate-200 text-slate-600'">Manual Entry</button>
                </div>
                <section x-show="showMatingPicker && !monitoringCalf" class="mb-4 rounded-xl border border-slate-200 bg-white p-4" aria-labelledby="calf-drop-due-title">
                    <h4 id="calf-drop-due-title" class="font-semibold text-slate-900">Latest AI records with positive pregnancy results at 280+ days</h4>
                    <p class="mt-1 text-sm text-slate-500">Select a mother awaiting a calf-drop report to prefill the form.</p>
                    <p x-show="loadingDueMatings" role="status" class="mt-3 text-sm text-slate-500">Loading mating records…</p>
                    <div x-show="dueMatingsError" class="mt-3 flex items-center gap-3 text-sm text-rose-700">
                        <span role="alert" x-text="dueMatingsError"></span>
                        <button type="button" @click="loadDueMatings()" class="font-semibold underline">Retry</button>
                    </div>
                    <p x-show="!loadingDueMatings && !dueMatingsError && !dueMatings.length" class="mt-3 text-sm text-slate-500">No matching pregnancies are awaiting a calf-drop report.</p>
                    <ul x-show="dueMatings.length" class="mt-3 max-h-48 divide-y divide-slate-100 overflow-y-auto">
                        <template x-for="mating in dueMatings" :key="mating.id">
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                                <div>
                                    <span class="font-semibold text-slate-900" x-text="mating.animal_id || mating.carabao_id"></span>
                                    <span class="ml-2 text-slate-600" x-text="mating.cooperative_name || ''"></span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="mating.calf_drop_overdue ? 'bg-amber-100 text-amber-900' : 'bg-blue-50 text-blue-800'" x-text="mating.pregnancy_days + ' days'"></span>
                                    <button type="button" @click="addFromMating(mating)" :disabled="savingCalf" class="rounded-lg border border-slate-300 px-3 py-2 font-semibold text-slate-800 hover:bg-slate-50">Use mating record</button>
                                </div>
                            </li>
                        </template>
                    </ul>
                </section>
                <section x-show="monitoringCalf ? showMatingPicker : (!showMatingPicker && selectedDamMatings.length)" class="mb-4 rounded-xl border border-slate-200 p-4">
                    <h4 class="font-semibold text-slate-900">Latest AI record for the selected animal</h4>
                    <p x-show="loadingDueMatings" role="status" class="mt-2 text-sm text-slate-500">Loading mating records…</p>
                    <p x-show="dueMatingsError" role="alert" class="mt-2 text-sm text-rose-700" x-text="dueMatingsError"></p>
                    <p x-show="!loadingDueMatings && !dueMatingsError && !selectedDamMatings.length" class="mt-2 text-sm text-slate-600">No latest AI record with a positive pregnancy result. You can use Manual Entry.</p>
                    <ul x-show="selectedDamMatings.length" class="mt-2 max-h-48 divide-y divide-slate-100 overflow-y-auto">
                        <template x-for="record in selectedDamMatings" :key="record.id">
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                                <span x-text="[record.mating_date || 'Undated', record.mating_type, record.bull_id ? 'Sire ' + record.bull_id : 'Sire unavailable'].filter(Boolean).join(' · ')"></span>
                                <button type="button" @click="useDamMating(record)" :disabled="savingCalf" class="rounded-lg border border-slate-300 px-3 py-2 font-semibold text-slate-800">Use mating record</button>
                            </li>
                        </template>
                    </ul>
                </section>
                <fieldset :disabled="savingCalf" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <template x-if="!showMatingPicker && !monitoringCalf">
                        <div class="grid gap-4 sm:col-span-2 sm:grid-cols-2 lg:col-span-3">
                            <div class="relative text-sm" @click.outside="cooperativePickerOpen=false" @keydown.escape.stop="if(cooperativePickerOpen){cooperativePickerOpen=false;$refs.cooperativeTrigger.focus()}">
                                <span id="calf-cooperative-label" class="font-semibold">Cooperative <span class="text-rose-600">*</span></span>
                                <button type="button" x-ref="cooperativeTrigger" aria-labelledby="calf-cooperative-label calf-cooperative-value" aria-controls="calf-cooperative-picker" :aria-expanded="cooperativePickerOpen" @click="toggleCooperativePicker()" class="form-input mt-1 flex items-center justify-between gap-2 text-left">
                                    <span id="calf-cooperative-value" x-text="addForm.cooperative || (inventoryLoading ? 'Loading cooperatives…' : 'Select cooperative')"></span>
                                    <i class="fa-solid fa-chevron-down text-xs text-slate-500" aria-hidden="true"></i>
                                </button>
                                <div id="calf-cooperative-picker" x-show="cooperativePickerOpen" class="absolute inset-x-0 z-10 mt-1 rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                                    <input type="search" x-ref="cooperativeSearch" x-model="cooperativeQuery" @keydown.enter.prevent="if(matchingCooperatives.length===1)selectManualCooperative(matchingCooperatives[0])" aria-label="Search cooperatives" placeholder="Search cooperatives…" class="form-input">
                                    <ul class="mt-1 max-h-40 overflow-y-auto">
                                        <template x-for="cooperative in matchingCooperatives" :key="cooperative.id">
                                            <li><button type="button" @click="selectManualCooperative(cooperative)" class="w-full rounded-md px-2 py-2 text-left hover:bg-sky-50 focus:bg-sky-50" x-text="cooperative.cooperative_name"></button></li>
                                        </template>
                                    </ul>
                                    <p x-show="!matchingCooperatives.length" class="px-2 py-2 text-slate-500" x-text="inventoryLoading ? 'Loading cooperatives…' : 'No matching cooperatives.'"></p>
                                </div>
                            </div>
                            <div class="relative text-sm" @click.outside="farmerPickerOpen=false" @keydown.escape.stop="if(farmerPickerOpen){farmerPickerOpen=false;$refs.farmerTrigger.focus()}">
                                <span id="calf-farmer-label" class="font-semibold">Farmer <span class="text-rose-600">*</span></span>
                                <button type="button" x-ref="farmerTrigger" aria-labelledby="calf-farmer-label calf-farmer-value" aria-controls="calf-farmer-picker" :aria-expanded="farmerPickerOpen" :disabled="!manualFarmers.length" @click="toggleFarmerPicker()" class="form-input mt-1 flex items-center justify-between gap-2 text-left disabled:opacity-60">
                                    <span id="calf-farmer-value" x-text="addForm.farmer || (!addForm.cooperative_id ? 'Select a cooperative first' : (manualFarmers.length ? 'Select farmer' : 'No farmers in this cooperative'))"></span>
                                    <i class="fa-solid fa-chevron-down text-xs text-slate-500" aria-hidden="true"></i>
                                </button>
                                <div id="calf-farmer-picker" x-show="farmerPickerOpen" class="absolute inset-x-0 z-10 mt-1 rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                                    <input type="search" x-ref="farmerSearch" x-model="farmerQuery" @keydown.enter.prevent="if(matchingManualFarmers.length===1)selectManualFarmer(matchingManualFarmers[0])" aria-label="Search farmers" placeholder="Search farmers…" class="form-input">
                                    <ul class="mt-1 max-h-40 overflow-y-auto">
                                        <template x-for="farmer in matchingManualFarmers" :key="farmer.id">
                                            <li><button type="button" @click="selectManualFarmer(farmer)" class="w-full rounded-md px-2 py-2 text-left hover:bg-sky-50 focus:bg-sky-50" x-text="farmerLabel(farmer)"></button></li>
                                        </template>
                                    </ul>
                                    <p x-show="!matchingManualFarmers.length" class="px-2 py-2 text-slate-500">No matching farmers.</p>
                                </div>
                            </div>
                        </div>
                    </template>
                    <template x-if="monitoringCalf">
                        <div class="grid gap-4 sm:col-span-2 sm:grid-cols-2 lg:col-span-3">
                            <label class="text-sm font-semibold">Cooperative<input :value="addForm.cooperative" readonly class="form-input mt-1"></label>
                            <label class="text-sm font-semibold">Farmer<input :value="addForm.farmer" readonly class="form-input mt-1"></label>
                        </div>
                    </template>
                    <div>
                        <label id="calf-mother-label" :for="(showMatingPicker || monitoringCalf) ? 'calf-mating-mother-id' : 'calf-manual-mother-id'" class="text-sm font-semibold">Mother ID <span class="text-rose-600">*</span></label>
                        <input id="calf-mating-mother-id" x-show="showMatingPicker || monitoringCalf" :value="motherIdDisplay" readonly class="form-input mt-1">
                        <template x-if="!showMatingPicker && !monitoringCalf">
                            <div class="relative" @click.outside="motherPickerOpen = false" @keydown.escape.stop="if (motherPickerOpen) { motherPickerOpen = false; $refs.motherTrigger.focus(); }">
                                <div class="relative mt-1">
                                    <input id="calf-manual-mother-id" :value="motherInput" @input="updateManualMother($event.target.value)" required aria-describedby="manual-dam-mating-warning manual-dam-eligibility-warning" placeholder="Enter Mother ID" class="form-input !pr-12">
                                    <button type="button" x-ref="motherTrigger" @click="toggleMotherPicker()" :disabled="!manualMothers.length" aria-label="Select mother" aria-controls="calf-mother-picker" :aria-expanded="motherPickerOpen" class="absolute inset-y-0 right-0 rounded-r-lg px-4 text-slate-600 disabled:opacity-50">
                                        <i class="fa-solid fa-chevron-down text-xs" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <div id="calf-mother-picker" x-show="motherPickerOpen" class="absolute inset-x-0 z-10 mt-1 rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                                    <input type="search" x-ref="motherSearch" x-model="motherQuery" @keydown.enter.prevent="chooseMotherFromSearch()" aria-label="Search Mother ID" placeholder="Search Mother ID or name" class="form-input">
                                    <ul class="mt-1 max-h-40 overflow-y-auto">
                                        <template x-for="mother in matchingManualMothers" :key="mother.id">
                                            <li><button type="button" @click="selectManualMother(mother)" class="w-full rounded-md px-2 py-2 text-left hover:bg-sky-50 focus:bg-sky-50" x-text="[mother.animal_id, mother.animal_name].filter(Boolean).join(' — ')"></button></li>
                                        </template>
                                    </ul>
                                    <p x-show="!matchingManualMothers.length" class="px-2 py-2 text-sm text-slate-500">No matching dams.</p>
                                </div>
                                <p id="manual-dam-mating-warning" x-show="manualDamHasMating" role="alert" class="mt-1 text-xs font-medium text-amber-800">Mating record found. Select the matching record to fill in saved details.</p>
                                <p id="manual-dam-eligibility-warning" x-show="manualMotherIneligibility" x-text="manualMotherIneligibility" role="alert" class="mt-1 text-xs font-medium text-amber-800"></p>
                            </div>
                        </template>
                    </div>
                    <label class="text-sm font-semibold">Birth or event date <span class="text-rose-600">*</span>
                        <input x-model="addForm.date_of_birth" @change="$nextTick(() => checkManualDamDate())" type="date" required class="form-input mt-1">
                        <span x-show="formattedBirthDate" x-text="formattedBirthDate" class="mt-1 block text-xs font-normal text-slate-600"></span>
                    </label>
                    <label class="text-sm font-semibold">Birth outcome
                        <select x-model="addForm.birth_outcome" class="form-input mt-1"><option>Live Birth</option><option>Stillbirth</option><option>Abortion</option></select>
                    </label>
                    <template x-if="addForm.birth_outcome === 'Live Birth' && calfHerdOptions.length && !monitoringCalf?.data?.calf_id">
                        <label class="text-sm font-semibold">Herd Code
                            <input x-show="assignedCalfHerdCode" :value="assignedCalfHerdCode" readonly class="form-input mt-1">
                            <select x-show="!assignedCalfHerdCode" x-model="addForm.herd_code" :required="!assignedCalfHerdCode" :disabled="!!assignedCalfHerdCode" class="form-input mt-1">
                                <option value="">Select herd code</option>
                                <template x-for="code in calfHerdOptions" :key="code">
                                    <option :value="code" x-text="code"></option>
                                </template>
                            </select>
                        </label>
                    </template>
                    <template x-if="monitoringCalf">
                        <label class="text-sm font-semibold">Mating method
                            <select x-model="addForm.mating_method" class="form-input mt-1">
                                <option value="">Select mating method</option>
                                <option value="AI">Artificial Insemination (AI)</option>
                                <option value="NM">Natural Mating (NM)</option>
                            </select>
                        </label>
                    </template>
                    <div>
                        <label class="text-sm font-semibold">Calf ID
                            <input :value="canResolveCalfFromSire ? previewCalfId : ''" placeholder="Assigned on save" readonly class="form-input mt-1">
                        </label>
                        <button x-show="addForm.birth_outcome === 'Live Birth' && !monitoringCalf?.data?.calf_id" type="button" @click="verifyCalfSeries()" :disabled="!seriesQuery || checkingSeries"
                            class="mt-2 block text-sm font-semibold text-sky-800 underline disabled:opacity-50">Check Available Series</button>
                        <label x-show="currentSeries?.available_series?.length" class="mt-2 inline-flex items-center gap-2 text-sm font-semibold">
                            <input type="checkbox" :checked="showSeriesOptions" @change="toggleSeriesOptions($event.target.checked)"
                                x-init="$watch('seriesQuery', query => { if (showSeriesOptions && query !== seriesOptionsQuery) toggleSeriesOptions(false) })">
                            Calf series number
                        </label>
                        <p x-show="checkingSeries" role="status" class="mt-1 text-xs text-slate-600">Checking series…</p>
                        <template x-if="showSeriesOptions && currentSeries?.available_series?.length">
                                <select aria-label="Calf series number" class="form-input mt-1" @change="selectCalfSeries($event.target.value)">
                                    <option value="" disabled :selected="!selectedSeries">Select a series</option>
                                    <option value="">Assign next available</option>
                                    <template x-if="selectedSeries && !currentSeries.available_series.includes(selectedSeries)">
                                        <option :value="selectedSeries" selected disabled x-text="selectedSeries + ' (no longer available)'"></option>
                                    </template>
                                    <template x-for="number in currentSeries?.available_series || []" :key="number">
                                        <option :value="number" :selected="number === selectedSeries" x-text="number"></option>
                                    </template>
                                </select>
                        </template>
                        <p x-show="currentSeries?.message || currentSeries?.available_series?.length === 0" role="status" class="mt-1 text-xs text-slate-600" x-text="currentSeries?.message || 'No calf series numbers are available for this year.'"></p>
                        <template x-if="showSeriesOptions && currentSeries?.reserved_ranges?.length">
                            <details class="mt-2 text-xs text-slate-600">
                                <summary class="cursor-pointer font-semibold">Reserved series</summary>
                                <ul class="mt-1 space-y-1">
                                    <template x-for="range in currentSeries.reserved_ranges" :key="range.section">
                                        <li x-text="range.first + '–' + range.last + ' reserved for ' + range.section"></li>
                                    </template>
                                </ul>
                            </details>
                        </template>
                    </div>
                    <label class="text-sm font-semibold">Sex <span x-show="addForm.birth_outcome === 'Live Birth'" class="text-rose-600">*</span>
                        <select x-model="addForm.sex" :required="addForm.birth_outcome === 'Live Birth'" class="form-input mt-1"><option value="">Select sex</option><option>Male</option><option>Female</option></select>
                    </label>
                    <label class="text-sm font-semibold">Breed
                        <input :value="canResolveCalfFromSire ? inferredCalfBreed(addForm) : ''" placeholder="Select a dam and enter a sire" readonly class="form-input mt-1">
                    </label>
                    <div class="relative" @click.outside="sirePickerOpen = false" @keydown.escape.stop="if (sirePickerOpen) { sirePickerOpen = false; $refs.sireTrigger.focus(); }">
                        <label for="calf-sire-id" class="text-sm font-semibold">Sire / Bull ID <span class="text-rose-600">*</span></label>
                        <div class="relative mt-1">
                            <input id="calf-sire-id" :value="addForm.sire_id" @input="updateManualSire($event.target)" :readonly="unknownSire" :required="!unknownSire" aria-describedby="calf-sire-prefix-alert" placeholder="Enter a bull ID" class="form-input !pr-12">
                            <button type="button" x-ref="sireTrigger" @click="toggleSirePicker()" :disabled="unknownSire" aria-label="Select registered sire" aria-controls="calf-sire-picker" :aria-expanded="sirePickerOpen" class="absolute inset-y-0 right-0 rounded-r-lg px-4 text-slate-600 disabled:opacity-50">
                                <i class="fa-solid fa-chevron-down text-xs" aria-hidden="true"></i>
                            </button>
                        </div>
                        <p id="calf-sire-prefix-alert" x-show="invalidSirePrefix" role="alert" class="mt-1 text-xs text-rose-700" x-text="invalidNumericSirePrefix ? 'A numeric sire ID must start with 2, 5, 6, 7, or 8.' : 'Start the sire ID with 2, 5, 6, 7, or 8, or select a registered bull from the dropdown.'"></p>
                        <div id="calf-sire-picker" x-show="sirePickerOpen && !unknownSire" class="absolute inset-x-0 z-10 mt-1 rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                            <input type="search" x-ref="sireSearch" x-model="sireQuery" @keydown.enter.prevent="if (matchingSemenBulls.length === 1) selectSemenBull(matchingSemenBulls[0])" aria-label="Search registered bull IDs" placeholder="Search bull IDs" class="form-input">
                            <ul class="mt-1 max-h-40 overflow-y-auto">
                                <template x-for="bull in matchingSemenBulls" :key="bull.id">
                                    <li><button type="button" @click="selectSemenBull(bull)" class="w-full rounded-md px-2 py-2 text-left hover:bg-sky-50 focus:bg-sky-50" x-text="bull.id"></button></li>
                                </template>
                            </ul>
                            <p x-show="!matchingSemenBulls.length" class="px-2 py-2 text-sm text-slate-500">No matching bull IDs.</p>
                        </div>
                        <label class="mt-2 flex items-center gap-2 text-sm font-medium">
                            <input type="checkbox" :checked="unknownSire" @change="sirePickerOpen = false; addForm.sire_id = $event.target.checked ? 'UNKNOWN' : ''" class="rounded border-slate-300">
                            Unknown sire
                        </label>
                        <p x-show="enteredSireBreed" aria-live="polite" class="mt-1 text-xs text-slate-600">
                            Sire breed <strong x-text="enteredSireBreed"></strong>
                        </p>
                        <span x-show="canResolveCalfFromSire && cb50Parentage(addForm)" class="mt-1 block text-xs font-normal text-slate-500" x-text="'Sire on record: ' + cb50Parentage(addForm)?.sireId"></span>
                    </div>
                    <label x-show="showMatingPicker && !monitoringCalf" class="text-sm font-semibold">Farmer
                        <input :value="addForm.farmer" readonly class="form-input mt-1">
                    </label>
                    <label x-show="showMatingPicker && !monitoringCalf" class="text-sm font-semibold">Cooperative
                        <input :value="addForm.cooperative" readonly class="form-input mt-1">
                    </label>
                    <label class="text-sm font-semibold">Province
                        <input x-model.trim="addForm.province" list="calf-provinces" class="form-input mt-1">
                        <datalist id="calf-provinces"><template x-for="province in provinceOptions" :key="province"><option :value="province"></option></template></datalist>
                    </label>
                    <label class="text-sm font-semibold">Municipality<input x-model.trim="addForm.municipality" class="form-input mt-1"></label>
                    <label class="text-sm font-semibold">Barangay<input x-model.trim="addForm.barangay" class="form-input mt-1"></label>
                    <label class="text-sm font-semibold">Monitored date
                        <input x-model="addForm.monitored_date" type="date" class="form-input mt-1">
                    </label>
                    <label class="text-sm font-semibold">Reported year
                        <input x-model="addForm.reported_year" type="number" min="2000" max="2100" class="form-input mt-1">
                    </label>
                    <label x-show="addForm.birth_outcome === 'Live Birth'" class="text-sm font-semibold">Animal status
                        <select x-model="addForm.animal_status"  class="form-input mt-1"><option>Live</option><option>Out</option><option>Dead</option><option>Not Applicable</option></select>
                    </label>
                    <label x-show="addForm.sire_id?.trim() && (calfSeries(addForm) || addForm.birth_outcome !== 'Live Birth')" class="text-sm font-semibold">Breed series
                        <input :value="canResolveCalfFromSire ? (calfSeries(addForm) || addForm.breed_series) : ''" readonly class="form-input mt-1">
                    </label>
                    <label class="text-sm font-semibold sm:col-span-2 lg:col-span-3">Remarks<textarea x-model="addForm.remarks" rows="2" class="form-input mt-1"></textarea></label>
                </fieldset>
            </div>
            <p x-show="monitoringCalf && notice" x-text="notice" role="alert" class="px-5 pb-3 text-sm text-rose-800"></p>
            <footer class="flex shrink-0 justify-end gap-2 border-t border-slate-200 bg-slate-50 p-4">
                <button x-show="monitoringCalf?.hasService" type="button" @click="finishMonitoringCalf(null)" class="mr-auto rounded-lg px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Remove service</button>
                <button type="button" @click="closeCalfForm()" :disabled="savingCalf" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Cancel</button>
                <button type="submit" :disabled="savingCalf || invalidNumericSirePrefix" class="cbed-save-button" x-text="savingCalf ? 'Saving&hellip;' : 'Save Record'"></button>
            </footer>
        </form>
    </div>
