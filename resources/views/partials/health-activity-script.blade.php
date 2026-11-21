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
function finishMonitoringHealth(data, bodyConditionScore) {
    const index = healthMonitoringContext.index;
    closeHealthEditor();
    window.dispatchEvent(new CustomEvent('monitoring-health-draft', {detail: {index, data, bodyConditionScore}}));
}
function updateHealthTreatmentCount() {
    const rows = [...document.querySelectorAll('#health-service-rows .health-service-row')];
    document.getElementById('health-treatments').value = rows.filter(healthServiceType).length;
}

function healthServiceType(row) {
    const choice = row.querySelector('[data-service-choice]').value;
    return choice === '__other__' ? row.querySelector('[data-custom-service]').value.trim() : choice;
}

function updateHealthServiceFields(row) {
    const choice = row.querySelector('[data-service-choice]');
    const custom = row.querySelector('[data-custom-service]');
    const isOther = choice.value === '__other__';
    custom.closest('label').hidden = !isOther;
    custom.disabled = !isOther;
    custom.required = isOther;

    const type = healthServiceType(row).toLowerCase().replace(/[^a-z0-9]/g, '');
    const allowsDosage = /^(deworm|vaccin|vitamin)/.test(type);
    const dosage = row.querySelector('[data-service="dosage_ml"]');
    dosage.closest('label').hidden = !allowsDosage;
    dosage.disabled = !allowsDosage;
    const requiresBolus = ['bolus', 'bolusapplication'].includes(type);
    const bolus = row.querySelector('[data-service="bolus_number"]');
    bolus.closest('[data-bolus-field]').hidden = !requiresBolus;
    bolus.disabled = !requiresBolus;
    bolus.required = requiresBolus;
    updateHealthBolusWarning(row);
}

function updateHealthBolusWarning(row) {
    const bolus = row.querySelector('[data-service="bolus_number"]');
    const inventoryId = document.getElementById('health-carabao-id').selectedOptions[0]?.dataset.bolusId?.trim() || '';
    const warning = row.querySelector('[data-bolus-warning]');
    const hasInventoryId = !['', '-', '--', 'n/a', 'na', 'none', 'unknown', '0'].includes(inventoryId.toLowerCase());
    warning.hidden = bolus.disabled || !hasInventoryId;
    const entered = bolus.value.trim();
    warning.textContent = warning.hidden ? '' : entered && entered.toLowerCase() !== inventoryId.toLowerCase()
        ? 'Saving will replace this animal’s Carabao Inventory Bolus ID (' + inventoryId + ') with the number entered.'
        : 'This animal already has Bolus ID ' + inventoryId + ' in Carabao Inventory.';
}

function addHealthServiceRow(service = {}, focus = false) {
    const row = document.getElementById('health-service-row-template').content.firstElementChild.cloneNode(true);
    const choice = row.querySelector('[data-service-choice]');
    const custom = row.querySelector('[data-custom-service]');
    const storedName = service.type === 'Other Services' ? (service.other_service_name || service.type) : (service.type || '');
    const storedType = storedName.toLowerCase() === 'bolus' ? 'Bolus Application' : storedName;
    const existing = [...choice.options].find(option => option.value !== '__other__' && option.value.toLowerCase() === storedType.toLowerCase());
    choice.value = existing?.value ?? '__other__';
    custom.value = existing ? '' : storedType;
    row.querySelector('[data-service="dosage_ml"]').value = service.dosage_ml ?? '';
    row.querySelector('[data-service="bolus_number"]').value = service.bolus_number ?? service.bolus_id ?? '';
    row.querySelector('[data-service="remarks"]').value = service.remarks ?? '';

    const refresh = () => {
        updateHealthServiceFields(row);
        updateHealthTreatmentCount();
    };
    choice.addEventListener('change', () => {
        refresh();
        if (choice.value === '__other__') custom.focus();
    });
    custom.addEventListener('input', refresh);
    row.querySelector('[data-service="bolus_number"]').addEventListener('input', () => updateHealthBolusWarning(row));
    row.querySelector('[data-remove-service]').addEventListener('click', () => {
        row.remove();
        updateHealthTreatmentCount();
        document.getElementById('health-add-service-button').focus();
    });
    document.getElementById('health-service-rows').appendChild(row);
    refresh();
    if (focus) choice.focus();
}

document.getElementById('health-carabao-id').addEventListener('change', () => {
    document.querySelectorAll('#health-service-rows .health-service-row').forEach(updateHealthBolusWarning);
});
async function openHealthEditor(id = null, readOnly = false, monitoringContext = null) {
    healthMonitoringContext = monitoringContext;
    const form = document.getElementById('health-editor-form');
    form.reset();
    const animalSelect = document.getElementById('health-carabao-id');
    animalSelect.querySelector('[data-monitoring-animal]')?.remove();
    form.querySelectorAll('input,select,textarea').forEach(control => control.disabled = false);
    document.getElementById('health-record-id').value = '';
    document.getElementById('health-service-rows').replaceChildren();
    document.getElementById('health-editor-title').textContent = !id ? 'Add Health and Nutrition Activity' : readOnly ? 'View Health and Nutrition Activity' : 'Edit Health and Nutrition Activity';
    animalSelect.disabled = readOnly || Boolean(monitoringContext);
    animalSelect.dispatchEvent(new Event('change', {bubbles: true}));
    const saveButton = document.getElementById('health-save-button');
    saveButton.hidden = readOnly;
    saveButton.textContent = id || monitoringContext?.hasService ? 'Update Record' : 'Save Record';
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
            animalSelect.value = String(data.carabao_id ?? '');
        } else if (monitoringContext) {
            const animalId = String(monitoringContext.carabaoId ?? '');
            animalSelect.value = animalId;
            if (animalId && !animalSelect.value) {
                const option = new Option(monitoringContext.registryId || animalId, animalId);
                option.setAttribute('data-monitoring-animal', '');
                option.dataset.bolusId = monitoringContext.bolusId ?? '';
                animalSelect.add(option);
                animalSelect.value = animalId;
            }
        }
        animalSelect.dispatchEvent(new Event('change', {bubbles: true}));
        document.getElementById('health-monitoring-date').value = String(data.monitoring_date || monitoringContext?.reportDate || new Date().toISOString()).slice(0, 10);
        document.getElementById('health-body-condition-score').value = monitoringContext
            ? (monitoringContext.bodyConditionScore ?? '')
            : (data.body_condition_score ?? '');
        document.getElementById('health-activity-remarks').value = data.remarks || '';
        const services = (data.services || []).filter(service => service.selected ?? true);
        services.forEach(service => addHealthServiceRow(service));
        if (!services.length) addHealthServiceRow();
        updateHealthTreatmentCount();
        if (readOnly) form.querySelectorAll('input,select,textarea,#health-service-rows button').forEach(control => control.disabled = true);
    } catch (error) {
        alert(error.message);
        closeHealthEditor();
    }
}

async function saveHealthActivity(event) {
    event.preventDefault();
    const carabaoId = document.getElementById('health-carabao-id').value;
    if (!carabaoId) {
        const trigger = document.getElementById('health-carabao-id-trigger');
        if (trigger.getAttribute('aria-expanded') === 'true') {
            document.getElementById('health-carabao-id-search').focus();
        } else {
            trigger.click();
        }
        return;
    }
    const services = [...document.querySelectorAll('#health-service-rows .health-service-row')]
        .map(row => ({
            type: healthServiceType(row),
            ...Object.fromEntries([...row.querySelectorAll('[data-service]:not(:disabled)')]
                .map(input => [input.dataset.service, input.value.trim()])),
        }))
        .filter(service => service.type);
    if (!services.length) {
        alert('Add at least one service.');
        return;
    }
    const payload = {
        carabao_id: Number(carabaoId),
        monitoring_date: document.getElementById('health-monitoring-date').value,
        body_condition_score: document.getElementById('health-body-condition-score').value || null,
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
        }, payload.body_condition_score);
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
