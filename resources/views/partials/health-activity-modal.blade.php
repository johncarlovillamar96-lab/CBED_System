        <div id="health-editor" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="health-editor-title">
            <div class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="sticky top-0 flex items-center justify-between border-b bg-white px-6 py-4"><div><h2 id="health-editor-title" class="text-xl font-bold text-[#12345a]">Add Health and Nutrition Activity</h2><p class="text-sm text-slate-500">One activity can include multiple services.</p></div><button type="button" onclick="closeHealthEditor()" class="rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100">✕</button></div>
                <form id="health-editor-form" class="space-y-5 p-6" onsubmit="saveHealthActivity(event)">
                    <input type="hidden" id="health-record-id">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="relative">
                            <span id="health-animal-label" class="block text-sm font-semibold">Animal Registry ID</span>
                            <input type="hidden" id="health-carabao-id">
                            <details id="health-animal-picker" class="mt-1" ontoggle="if (this.open) document.getElementById('health-animal-search').focus()">
                                <summary id="health-animal-selection" aria-labelledby="health-animal-label health-animal-selection" class="cursor-pointer rounded-lg border border-slate-300 px-3 py-2">Select animal</summary>
                                <div class="absolute z-10 mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 shadow-lg" onkeydown="if (event.key === 'Escape') { event.stopPropagation(); closeHealthAnimalPicker(); }">
                                    <input id="health-animal-search" type="search" aria-label="Search Animal Registry ID" placeholder="Search registry IDs" oninput="filterHealthAnimals()" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                    <div id="health-animal-options" class="mt-1 max-h-48 overflow-y-auto">
                                        @foreach(($healthAnimals ?? []) as $animal)
                                            <button type="button" data-animal-id="{{ $animal['id'] }}" data-registry-id="{{ $animal['registry_id'] }}" onclick="selectHealthAnimal(this.dataset.animalId)" class="w-full rounded px-3 py-2 text-left text-sm hover:bg-slate-100">{{ $animal['registry_id'] ?: 'N/A' }} · {{ $animal['cooperative_name'] ?: 'No cooperative' }}</button>
                                        @endforeach
                                    </div>
                                    <p id="health-animal-no-results" hidden class="px-3 py-2 text-sm text-slate-500">No matching registry IDs</p>
                                </div>
                            </details>
                        </div>
                        <label class="block text-sm font-semibold">Monitoring Date<input id="health-monitoring-date" type="date" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                        <label class="block text-sm font-semibold">No. of Visits / Monitored<input id="health-visits" type="number" value="1" readonly class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                        <label class="block text-sm font-semibold">No. of Treatments<input id="health-treatments" type="number" value="0" readonly class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                    </div>
                    <datalist id="health-service-options"><option value="Deworming"><option value="Vitamins"><option value="Vaccination"><option value="Uterine Flushing"><option value="Fecalysis"><option value="Ear Tagging"><option value="Recruitment"><option value="Hands-on Milking"><option value="Forage Demo"></datalist>
                    <div class="rounded-xl border border-slate-200 p-4"><div class="mb-3 flex items-center justify-between gap-2"><div><h3 class="font-bold text-slate-800">Type of Service</h3><p class="text-xs text-slate-500">Add a service type and enter a dosage when applicable.</p></div><button id="health-add-service-button" type="button" onclick="addHealthServiceRow()" class="cbed-add-button rounded-lg px-3 py-2 text-sm font-semibold"><i class="fa-solid fa-plus mr-1"></i>Add Service</button></div><div id="health-service-rows" class="space-y-3"></div></div>
                    <label class="block text-sm font-semibold">Activity Remarks<textarea id="health-activity-remarks" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></textarea></label>
                    <div class="flex justify-end gap-2">
                        <button id="health-remove-service" type="button" onclick="finishMonitoringHealth(null)" hidden class="mr-auto rounded-lg px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Remove service</button>
                        <button type="button" onclick="closeHealthEditor()" class="rounded-lg border px-4 py-2">Close</button>
                        <button id="health-save-button" type="submit" class="action-primary bg-blue-700 text-white">Save Activity</button>
                    </div>
                </form>
            </div>
        </div>
