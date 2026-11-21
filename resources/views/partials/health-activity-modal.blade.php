        <div id="health-editor" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="health-editor-title">
            <div class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="sticky top-0 flex items-center justify-between border-b bg-white px-6 py-4"><div><h2 id="health-editor-title" class="text-xl font-bold text-[#12345a]">Add Health and Nutrition Activity</h2><p class="text-sm text-slate-500">One activity can include multiple services.</p></div><button type="button" onclick="closeHealthEditor()" class="cbed-close-button" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
                <form id="health-editor-form" class="space-y-5 p-6" onsubmit="saveHealthActivity(event)">
                    <input type="hidden" id="health-record-id">
                    <div class="grid gap-4 md:grid-cols-2">
                        <x-monitoring-picker id="health-carabao-id" label="Animal Registry ID">
                            <option value="" disabled selected>Select an animal registry ID</option>
                            @foreach(($healthAnimals ?? []) as $animal)
                                <option value="{{ $animal['id'] }}" data-bolus-id="{{ $animal['bolus_id'] ?? '' }}">{{ $animal['registry_id'] ?: 'N/A' }} &middot; {{ $animal['cooperative_name'] ?: 'No cooperative' }}</option>
                            @endforeach
                        </x-monitoring-picker>
                        <label class="block text-sm font-semibold">Monitoring Date<input id="health-monitoring-date" type="date" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                        <label class="block text-sm font-semibold">Body Condition Score (BCS)
                            <input id="health-body-condition-score" type="number" min="0" max="10" step="0.01" placeholder="Optional" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        </label>
                        <label class="block text-sm font-semibold">No. of Visits / Monitored<input id="health-visits" type="number" value="1" readonly class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                        <label class="block text-sm font-semibold">No. of Treatments<input id="health-treatments" type="number" value="0" readonly class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                    </div>
                    <section aria-labelledby="health-services-title" class="rounded-xl border border-slate-200 p-4">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <h3 id="health-services-title" class="font-bold text-slate-800">Type of Service</h3>
                            <button id="health-add-service-button" type="button" onclick="addHealthServiceRow({}, true)" class="cbed-add-button rounded-lg px-3 py-2 text-sm font-semibold">
                                <i class="fa-solid fa-plus mr-1" aria-hidden="true"></i>Add Service
                            </button>
                        </div>
                        <div id="health-service-rows" class="space-y-3"></div>
                        <template id="health-service-row-template">
                            <div class="health-service-row grid items-end gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="min-w-0 text-xs font-semibold text-slate-700">Service Type
                                        <select data-service-choice class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-100" required>
                                            <option value="" disabled>Select a service type</option>
                                            <optgroup label="Health and treatment">
                                                <option>Deworming</option>
                                                <option>Vaccination</option>
                                                <option>Vitamins</option>
                                                <option>Fecalysis</option>
                                            </optgroup>
                                            <optgroup label="Training and outreach">
                                                <option>Recruitment</option>
                                                <option>Hands-on Milking</option>
                                                <option>Forage Demo</option>
                                            </optgroup>
                                            <optgroup label="Other Services">
                                                <option>Uterine Flushing</option>
                                                <option>Ear Tagging</option>
                                                <option value="Bolus Application">Bolus</option>
                                                <option value="__other__">Other service</option>
                                            </optgroup>
                                        </select>
                                    </label>
                                    <label hidden class="min-w-0 text-xs font-semibold text-slate-700">Service Name
                                        <input data-custom-service type="text" maxlength="100" pattern=".*\S.*" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="Enter service name">
                                    </label>
                                    <label hidden class="min-w-0 text-xs font-semibold text-slate-700">Dosage (mL)
                                        <input data-service="dosage_ml" type="number" min="0" step="0.01" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="Optional">
                                    </label>
                                    <div data-bolus-field hidden class="min-w-0">
                                        <label class="text-xs font-semibold text-slate-700">Bolus Number
                                            <input data-service="bolus_number" type="text" maxlength="100" pattern=".*\S.*" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="Enter bolus number">
                                        </label>
                                        <p data-bolus-warning role="status" hidden class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-950"></p>
                                    </div>
                                </div>
                                <input type="hidden" data-service="remarks">
                                <button type="button" data-remove-service class="justify-self-start rounded-lg border border-red-200 px-3 py-2 text-sm text-red-700 hover:bg-red-50" aria-label="Remove service">Remove</button>
                            </div>
                        </template>
                    </section>
                    <label class="block text-sm font-semibold">Activity Remarks<textarea id="health-activity-remarks" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></textarea></label>
                    <div class="flex justify-end gap-2">
                        <button id="health-remove-service" type="button" onclick="finishMonitoringHealth(null)" hidden class="mr-auto rounded-lg px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Remove service</button>
                        <button type="button" onclick="closeHealthEditor()" class="rounded-lg border px-4 py-2">Close</button>
                        <button id="health-save-button" type="submit" class="cbed-save-button">Save Record</button>
                    </div>
                </form>
            </div>
        </div>
