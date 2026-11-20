@push('scripts')
<script>
const healthRecordsUrl=@json(url('/admin/health-records'));
let healthMonitoringContext = null;
function closeHealthEditor() {
    const editor = document.getElementById('health-editor');
    editor.classList.add('hidden');
    editor.classList.remove('flex');
    document.getElementById('monitoring-health-dialog')?.close();
    healthMonitoringContext = null;
}
function finishMonitoringHealth(data) {
    const index = healthMonitoringContext.index;
    closeHealthEditor();
    window.dispatchEvent(new CustomEvent('monitoring-health-draft', {detail: {index, data}}));
}
function updateHealthTreatmentCount() {
    const types = [...document.querySelectorAll('#health-service-rows [data-service="type"]')];
    document.getElementById('health-treatments').value = types.filter(input => input.value.trim()).length;
}
function updateHealthServiceDosage(row) {
    const type = row.querySelector('[data-service="type"]').value.toLowerCase().replace(/[^a-z0-9]/g, '');
    const allowsDosage = /^(deworm|vaccin|vitamin)/.test(type);
    const dosage = row.querySelector('[data-service="dosage_ml"]');
    dosage.closest('label').hidden = !allowsDosage;
    dosage.disabled = !allowsDosage;
}

function addHealthServiceRow(service={}){const row=document.createElement('div');row.className='health-service-row grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 md:grid-cols-[1.4fr_.7fr_auto]';row.innerHTML=`<label class="text-xs font-semibold">Service Type<input list="health-service-options" data-service="type" oninput="updateHealthServiceDosage(this.closest('.health-service-row')); updateHealthTreatmentCount()" class="mt-1 w-full rounded border px-2 py-2 text-sm" placeholder="Choose or enter a type" required></label><label class="text-xs font-semibold">Dosage (mL)<input data-service="dosage_ml" type="number" min="0" step="0.01" class="mt-1 w-full rounded border px-2 py-2 text-sm" placeholder="Not applicable"></label><input type="hidden" data-service="remarks"><button type="button" class="self-end rounded border border-red-200 px-3 py-2 text-sm text-red-700 hover:bg-red-50" aria-label="Remove service" onclick="this.closest('.health-service-row').remove(); updateHealthTreatmentCount()">Remove</button>`;row.querySelector('[data-service="type"]').value=service.type==='Other Services'?(service.other_service_name||'Other Services'):(service.type||'');row.querySelector('[data-service="dosage_ml"]').value=service.dosage_ml??'';row.querySelector('[data-service="remarks"]').value=service.remarks??'';updateHealthServiceDosage(row);document.getElementById('health-service-rows').appendChild(row);}
function filterHealthAnimals() {
    const query = document.getElementById('health-animal-search').value.trim().toLowerCase();
    let hasMatches = false;
    for (const option of document.querySelectorAll('#health-animal-options button')) {
        const matches = option.dataset.registryId.toLowerCase().includes(query);
        option.hidden = !matches;
        hasMatches ||= matches;
    }
    document.getElementById('health-animal-no-results').hidden = hasMatches;
}

function closeHealthAnimalPicker() {
    document.getElementById('health-animal-picker').open = false;
    document.getElementById('health-animal-selection').focus();
}

function selectHealthAnimal(id, focus = true) {
    const value = String(id ?? '');
    const option = [...document.querySelectorAll('#health-animal-options button')].find(button => button.dataset.animalId === value);
    document.getElementById('health-carabao-id').value = option ? value : '';
    document.getElementById('health-animal-selection').textContent = option?.textContent.trim() || 'Select animal';
    document.getElementById('health-animal-picker').open = false;
    document.getElementById('health-animal-search').value = '';
    filterHealthAnimals();
    if (focus) closeHealthAnimalPicker();
}
async function openHealthEditor(id = null, readOnly = false, monitoringContext = null) {
    healthMonitoringContext = monitoringContext;
    const form = document.getElementById('health-editor-form');
    form.reset();
    selectHealthAnimal('', false);
    form.querySelectorAll('input,textarea').forEach(control => control.disabled = false);
    document.getElementById('health-record-id').value = '';
    document.getElementById('health-service-rows').replaceChildren();
    document.getElementById('health-editor-title').textContent = !id ? 'Add Health and Nutrition Activity' : readOnly ? 'View Health and Nutrition Activity' : 'Edit Health and Nutrition Activity';
    document.getElementById('health-animal-picker').inert = readOnly || Boolean(monitoringContext);
    document.getElementById('health-save-button').hidden = readOnly;
    document.getElementById('health-add-service-button').hidden = readOnly;
    document.getElementById('health-remove-service').hidden = !monitoringContext?.hasService;
    const editor = document.getElementById('health-editor');
    editor.classList.remove('hidden');
    editor.classList.add('flex');
    document.getElementById('monitoring-health-dialog')?.showModal();

    try {
        let data = monitoringContext?.data ?? {};
        if (id) {
            const response = await fetch(`${healthRecordsUrl}/${id}`, {headers: {Accept: 'application/json'}});
            data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Could not load this activity.');
            document.getElementById('health-record-id').value = data.id;
            selectHealthAnimal(data.carabao_id, false);
        } else if (monitoringContext) {
            document.getElementById('health-carabao-id').value = monitoringContext.carabaoId;
            document.getElementById('health-animal-selection').textContent = monitoringContext.registryId;
        }
        document.getElementById('health-monitoring-date').value = String(data.monitoring_date || monitoringContext?.reportDate || new Date().toISOString()).slice(0, 10);
        document.getElementById('health-activity-remarks').value = data.remarks || '';
        const services = (data.services || []).filter(service => service.selected ?? true);
        services.forEach(service => addHealthServiceRow(service));
        if (!services.length) addHealthServiceRow();
        updateHealthTreatmentCount();
        if (readOnly) form.querySelectorAll('input,textarea,#health-service-rows button').forEach(control => control.disabled = true);
    } catch (error) {
        alert(error.message);
        closeHealthEditor();
    }
}

async function saveHealthActivity(event) {
    event.preventDefault();
    const carabaoId = document.getElementById('health-carabao-id').value;
    if (!carabaoId) {
        document.getElementById('health-animal-picker').open = true;
        document.getElementById('health-animal-search').focus();
        return;
    }
    const services = [...document.querySelectorAll('#health-service-rows .health-service-row')]
        .map(row => Object.fromEntries([...row.querySelectorAll('[data-service]:not(:disabled)')]
            .map(input => [input.dataset.service, input.value.trim()])))
        .filter(service => service.type);
    if (!services.length) {
        alert('Add at least one service.');
        return;
    }
    const payload = {
        carabao_id: Number(carabaoId),
        monitoring_date: document.getElementById('health-monitoring-date').value,
        remarks: document.getElementById('health-activity-remarks').value,
        services,
    };
    if (healthMonitoringContext) {
        finishMonitoringHealth({
            ...healthMonitoringContext.data,
            activity_form: true,
            monitoring_date: payload.monitoring_date,
            remarks: payload.remarks,
            services: services.map(service => ({...service, selected: true, dosage_ml: service.dosage_ml || null})),
        });
        return;
    }
    const id = document.getElementById('health-record-id').value;
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const response = await fetch(id ? `${healthRecordsUrl}/${id}` : healthRecordsUrl, {
        method: id ? 'PUT' : 'POST',
        headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token},
        body: JSON.stringify(payload),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) {
        alert(result.message || 'Could not save the activity.');
        return;
    }
    location.reload();
}
async function deleteHealthActivity(id){if(!confirm('Delete this Health and Nutrition activity and its service entries?'))return;const token=document.querySelector('meta[name="csrf-token"]')?.content;const response=await fetch(`${healthRecordsUrl}/${id}`,{method:'DELETE',headers:{Accept:'application/json','X-CSRF-TOKEN':token}});const result=await response.json().catch(()=>({}));if(!response.ok){alert(result.message||'Could not delete the activity.');return;}location.reload();}
</script>
@endpush
