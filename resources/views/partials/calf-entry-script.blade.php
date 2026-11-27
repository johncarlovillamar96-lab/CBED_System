@push('scripts')
<script>
function latestCalfAi(records) {
    return records.filter(record => ['ai', 'artificialinsemination'].includes(
        String(record.mating_type || '').toLowerCase().replace(/[^a-z]/g, '')
    )).sort((a, b) => String(b.mating_date || '').localeCompare(String(a.mating_date || '')) || Number(b.id) - Number(a.id))[0];
}

function monitoringDueCalfMating(records, today = new Date().toLocaleDateString('sv-SE', {timeZone: 'Asia/Manila'})) {
    const latest = latestCalfAi((records ?? []).filter(record => !record.pd_only));
    if (!latest?.calf_drop_awaiting || !latest.mating_date) return null;
    const days = Math.floor((Date.parse(today) - Date.parse(latest.mating_date)) / 86400000);
    return days >= 280 ? {...latest, pregnancy_days: days, calf_drop_overdue: true} : null;
}

function calfDropPage(monitoringMode = false) {
    return {
        storageKey: 'cbed_calfdrop_records', records: [], inventoryCalves: [], inventoryLoading: false, search: '', filterCooperative: '', filterProvince: '', filterBreed: '', filterSex: '',
        currentYear: String(new Date().getFullYear()),
        filterReportedYear: '',
        selectedBirthYear: null,
        birthYearSex: '',
        birthYearCooperative: '',
        birthYearStatus: '',
        calfHistory: null,
        calfHistoryLoading: false,
        calfHistoryError: '',
        calfPrintFromYear: '',
        calfPrintToYear: '',
        showAdd: false, savingCalf: false, deletingCalf: false, addForm: {}, showView: false, showEdit: false, savingEdit: false, editForm: {}, selected: null, notice: '', rowKey: 0, noticeTimer: null, page: 1, perPage: 15,
        monitoringCalf: null,
        matingRecords: [],
        matingLookup: 0,
        showMatingPicker: false, dueMatings: [], loadingDueMatings: false, dueMatingsError: '',
        calfCooperatives: [], calfFarmers: [], calfInventory: [],
        transferredCalfIds: new Set(),
        workbookHerds: @js(\App\Support\CooperativeReportingCounts::workbookHerds()),
        calfFarmerHerds: @js(\App\Support\CooperativeReportingCounts::CALF_FARMER_HERDS),
        calfFarmerHerdDefaults: @js(\App\Support\CooperativeReportingCounts::CALF_FARMER_HERD_DEFAULTS),
        showSeriesOptions: false, seriesOptionsQuery: '', checkingSeries: false, verifiedSeries: null, seriesSelection: null,
        sirePickerOpen: false, sireQuery: '', sireTyped: false,
        cooperativePickerOpen: false, cooperativeQuery: '',
        farmerPickerOpen: false, farmerQuery: '',
        motherPickerOpen: false, motherQuery: '', motherInput: '',
        deleteTarget: null, deleteCalfError: '', finalDeleteConfirmation: false,
        provinceOptions: ['Aurora','Bataan','Bulacan','Nueva Ecija','Pampanga','Tarlac','Zambales'],
        breedSeriesByCode: @js(\App\Support\CalfBreedSeries::BY_BREED),
        cb50DamCodes: @js(\App\Support\CalfBreedSeries::CB50_DAM_CODES),
        seriesTwoDamCodes: @js(\App\Support\CalfBreedSeries::SERIES_TWO_DAM_CODES),
        calfBreedBySireSeriesForSeriesTwoDam: @js(\App\Support\CalfBreedSeries::CALF_BREED_BY_SIRE_SERIES_FOR_SERIES_TWO_DAM),
        semenBulls: @js(\App\Support\CalfBreedSeries::semenBulls()),
        get unknownSire() { return String(this.addForm.sire_id ?? '').trim().toUpperCase() === 'UNKNOWN'; },
        get formattedBirthDate() {
            const date = this.addForm.date_of_birth;
            if (!date) return '';

            const [year, month, day] = date.split('-');
            const months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
            return `${day}-${months[Number(month) - 1]}-${year.slice(-2)}`;
        },
        updateManualSire(input) {
            this.addForm.sire_id = this.uppercaseSireInput(input);
            this.sireTyped = true;
        },
        uppercaseSireInput(input) {
            const uppercase = input.value.toUpperCase();
            if (input.value !== uppercase) {
                const {selectionStart, selectionEnd} = input;
                input.value = uppercase;
                input.setSelectionRange(selectionStart, selectionEnd);
            }
            return uppercase.trim();
        },
        get invalidNumericSirePrefix() {
            return /^[01349]/.test(String(this.addForm.sire_id ?? '').trim());
        },
        get canResolveCalfFromSire() {
            return String(this.addForm.sire_id ?? '').trim() !== '' && !this.invalidNumericSirePrefix;
        },
        get invalidSirePrefix() {
            const enteredId = String(this.addForm.sire_id ?? '').trim();
            return this.invalidNumericSirePrefix
                || (this.sireTyped && !this.unknownSire && enteredId !== '' && !/^[25678]/.test(enteredId));
        },
        get enteredSireBreed() {
            const digit = String(this.addForm.sire_id ?? '').trim().charAt(0);
            return Object.keys(this.breedSeriesByCode)
                .find(breed => breed.startsWith('CB') && this.breedSeriesByCode[breed] === digit) ?? '';
        },
        get matchingSemenBulls() {
            const query = this.sireQuery.trim().toUpperCase();
            return this.semenBulls.filter(bull => String(bull.id).toUpperCase().includes(query));
        },
        toggleSirePicker() {
            this.sirePickerOpen = !this.sirePickerOpen;
            if (!this.sirePickerOpen) return;
            this.sireQuery = '';
            this.$nextTick(() => this.$refs.sireSearch.focus());
        },
        selectSemenBull(bull) {
            this.addForm.sire_id = String(bull.id);
            this.sireTyped = false;
            this.sirePickerOpen = false;
            this.$refs.sireTrigger.focus();
        },
        breedSeries(breed) {
            const code = String(breed ?? '').trim().toUpperCase();
            if (this.seriesTwoDamCodes.includes(code)) return '2';
            if (this.cb50DamCodes.includes(code)) return '5';
            return this.breedSeriesByCode[code] ?? '';
        },
        damFor(row) {
            if (this.monitoringCalf) return this.monitoringCalf.dam;
            if (!row.mother_id) return null;
            return this.calfInventory.find(animal => String(animal.animal_id) === String(row.mother_id) || String(animal.id) === String(row.mother_id));
        },
        get motherIdDisplay() {
            return String(this.damFor(this.addForm)?.registry_number ?? '').trim() || this.addForm.mother_id;
        },
        damBreed(row) { return String(this.damFor(row)?.breed ?? '').trim(); },
        latestAi(records) {
            return latestCalfAi(records);
        },
        isPregnantMating(record) {
            return ['+', 'p', 'positive', 'pregnant', 'yes'].includes(String(record?.pregnancy_result || '').trim().toLowerCase());
        },
        get manualDamHasMating() {
            return !this.showMatingPicker && this.selectedDamMatings.length > 0;
        },
        get selectedDamMatings() {
            if (this.monitoringCalf) {
                const latest = this.latestAi(this.matingRecords.filter(record => !record.pd_only));
                return this.isPregnantMating(latest) ? [latest] : [];
            }
            if (!this.addForm.mother_id) return [];
            const dam = this.damFor(this.addForm);
            const ids = [this.addForm.mother_id, dam?.registry_number].filter(Boolean).map(id => String(id).trim().toUpperCase());
            const latest = this.latestAi(this.matingRecords.filter(record => record.carabao_id
                ? String(record.carabao_id) === String(dam?.id)
                : String(record.cooperative_id) === String(this.addForm.cooperative_id)
                    && ids.includes(String(record.registry_number || '').trim().toUpperCase())));
            return this.isPregnantMating(latest) ? [latest] : [];
        },
        get visibleDueMatings() {
            if (!this.monitoringCalf) return this.dueMatings;
            const mating = monitoringDueCalfMating(this.matingRecords);
            return mating ? [mating] : [];
        },
        get monitoringPregnancyDetails() {
            if (!this.monitoringCalf) return null;
            const dam = this.monitoringCalf.dam;
            const status = value => {
                const result = String(value ?? '').trim().toLowerCase();
                if (this.isPregnantMating({pregnancy_result: result})) return 'Pregnant';
                if (['-', 'n', 'negative', 'not pregnant', 'no'].includes(result)) return 'Not pregnant';
                return result === 'inconclusive' ? 'Inconclusive' : '';
            };
            const recordedOn = record => record.pregnancy_check_date || record.mating_date || '';
            const latest = this.matingRecords
                .filter(record => status(record.pregnancy_result)
                    && (!dam.last_calving_date || recordedOn(record) > dam.last_calving_date))
                .sort((a, b) => recordedOn(b).localeCompare(recordedOn(a)))[0];
            if (latest) return { ...latest, status: status(latest.pregnancy_result) };
            const rosterStatus = status(dam.pregnancy_status);
            return rosterStatus ? { status: rosterStatus } : null;
        },
        useDamMating(mating) {
            this.sireTyped = false;
            this.addForm.sire_id = mating.bull_id || this.addForm.sire_id;
            if (this.monitoringCalf) this.addForm.mating_method = 'AI';
            if (mating.actual_calving_date) this.addForm.date_of_birth = mating.actual_calving_date;
        },
        cb50Parentage(row) {
            const damBreed = this.damBreed(row).toUpperCase();
            const cb50Dam = this.cb50DamCodes.includes(damBreed);
            const seriesTwoDam = this.breedSeries(damBreed) === '2';
            const sireCode = String(row.sire_id ?? '').trim().toUpperCase();
            const sireSeries = /^[25678]/.test(sireCode) ? sireCode[0] : null;
            if (sireCode === 'UNKNOWN') {
                const breed = row.mating_method === 'AI' || seriesTwoDam ? 'CB75' : (cb50Dam ? 'CB50' : this.damBreed(row));
                return {breed, sireId: 'UNKNOWN'};
            }
            if (!cb50Dam && !seriesTwoDam) return null;

            const cb50SireCode = this.cb50DamCodes.includes(sireCode);
            const bull = cb50SireCode || ['', 'UNKNOWN'].includes(sireCode)
                ? null
                : this.semenBulls.find(item => String(item.id).trim().toUpperCase() === sireCode);
            if (seriesTwoDam) {
                const resolvedSireSeries = sireSeries ?? (cb50SireCode ? '5' : bull?.breed_series);
                const breed = this.calfBreedBySireSeriesForSeriesTwoDam[resolvedSireSeries];
                return breed ? {breed, sireId: bull?.id ?? (cb50SireCode ? 'CB50' : row.sire_id.trim())} : null;
            }
            const seriesTwo = sireSeries ? sireSeries === '2' : sireCode === 'RV' || bull?.series_two;
            const sireId = bull?.id ?? (seriesTwo || (sireSeries && !cb50SireCode) ? row.sire_id.trim() : 'CB50');
            return {breed: seriesTwo ? 'CB75' : 'CB50', sireId};
        },
        inferredCalfBreed(row) { return this.cb50Parentage(row)?.breed ?? this.damBreed(row); },
        get selectedCooperativeCode() {
            const dam = this.damFor(this.addForm);
            const cooperativeId = this.addForm.cooperative_id || dam?.cooperative_id;
            return this.monitoringCalf?.cooperativeCode
                || this.calfCooperatives.find(item => String(item.id) === String(cooperativeId))?.cooperative_code;
        },
        get calfCooperativeCode() {
            const code = this.selectedCooperativeCode;
            const dam = this.damFor(this.addForm);
            return code === 'ADL' && dam && this.isMappedAnimalIndustryDam(dam) ? 'AST' : code;
        },
        get calfHerdOptions() {
            return Object.keys(this.workbookHerds[this.calfCooperativeCode] ?? {});
        },
        get assignedCalfHerdCode() {
            const cooperative = this.calfCooperativeCode;
            const nameKey = name => (String(name ?? '').toLowerCase().match(/[a-z0-9]+/g) ?? []).sort().join(' ');
            const farmer = nameKey(this.addForm.farmer);
            if (!farmer) return '';
            const rule = Object.entries(this.calfFarmerHerds[cooperative] ?? {}).find(([name]) => nameKey(name) === farmer);
            return rule?.[1] ?? this.calfFarmerHerdDefaults[cooperative] ?? '';
        },
        get selectedCalfHerdCode() {
            return this.assignedCalfHerdCode || this.addForm.herd_code || '';
        },
        isMappedAnimalIndustryDam(animal) {
            const registries = Object.values(this.workbookHerds.AST).flat();
            return [animal.registry_number, animal.animal_id].some(id => registries.includes(id));
        },
        get seriesQuery() {
            const row = this.addForm;
            const dam = this.damFor(row);
            const breed = this.inferredCalfBreed(row);
            if (row.birth_outcome !== 'Live Birth' || this.monitoringCalf?.data?.calf_id || !this.canResolveCalfFromSire || !dam?.id || !this.breedSeries(breed) || !row.date_of_birth) return '';
            if (this.calfHerdOptions.length && !this.selectedCalfHerdCode) return '';

            const params = new URLSearchParams({dam_id: dam.id, breed, date_of_birth: row.date_of_birth});
            if (row.cooperative_id) params.set('cooperative_id', row.cooperative_id);
            if (this.selectedCalfHerdCode) params.set('herd_code', this.selectedCalfHerdCode);
            return params.toString();
        },
        get currentSeries() {
            return this.verifiedSeries?.query === this.seriesQuery ? this.verifiedSeries : null;
        },
        get selectedSeries() {
            return this.seriesSelection?.query === this.seriesQuery ? this.seriesSelection.number : '';
        },
        selectCalfSeries(number) {
            this.seriesSelection = {query: this.seriesQuery, number};
        },
        toggleSeriesOptions(checked) {
            this.showSeriesOptions = checked;
            this.seriesOptionsQuery = checked ? this.seriesQuery : '';
            if (!checked) {
                this.seriesSelection = null;
            }
        },
        get previewCalfId() {
            if (this.selectedSeries && this.currentSeries?.id_prefix) return this.currentSeries.id_prefix + this.selectedSeries;
            return this.monitoringCalf?.data?.calf_id || this.currentSeries?.next_id || this.generatedId(this.addForm);
        },
        async verifyCalfSeries() {
            const query = this.seriesQuery;
            if (!query || this.checkingSeries) return;
            this.checkingSeries = true;
            this.verifiedSeries = null;
            try {
                const response = await fetch(`/admin/calf-drop-api/series?${query}`, {headers: {Accept: 'application/json'}, cache: 'no-store'});
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || 'Unable to verify the calf series.');
                const year = new URLSearchParams(query).get('date_of_birth').slice(0, 4);
                const heldNumbers = new Set((this.monitoringCalf?.otherCalfDrafts ?? [])
                    .filter(draft => draft.year === year)
                    .map(draft => draft.number));
                data.available_series = data.available_series.filter(number => !heldNumbers.has(number));
                data.next_id = data.available_series.length ? data.id_prefix + data.available_series[0] : null;
                this.verifiedSeries = {...data, query};
            } catch (error) {
                this.verifiedSeries = {query, message: error.message};
            } finally {
                this.checkingSeries = false;
            }
        },
        calfSeries(row) { return this.breedSeries(String(row.breed ?? '').trim() || this.inferredCalfBreed(row)); },
        fields: ['calf_id','mother_id','farmer','province','municipality','barangay','cooperative','date_of_birth','sex','breed','sire_id','monitored_date','reported_year','birth_outcome','animal_status','breed_series','remarks'],
        init() {
            if (monitoringMode) return;
            try {
                const saved=localStorage.getItem(this.storageKey);
                const parsed=saved===null?[]:JSON.parse(saved);
                if(!Array.isArray(parsed))throw new Error('Saved calf records are not a list.');
                this.records=parsed.filter(record=>!this.isSampleRecord(record));
                const removed=parsed.length-this.records.length;
                if(removed>0&&this.persist())this.msg(`Removed ${removed} saved sample calf record(s).`);
            } catch(e) {
                this.records=[];
                console.error('Could not load or clean saved calf records:',e);
                this.msg('Unable to read saved calf records. Check browser storage.');
            }
            this.loadInventoryCalves(true);
            this.loadCalfHistory();
        },
        isSampleRecord(record) {
            const calfId=String(record?.calf_id??'').trim();
            const motherId=String(record?.mother_id??'').trim();
            const cooperative=String(record?.cooperative??'').trim();
            const remarks=String(record?.remarks??'').trim();
            return /^CALF-2026-00[1-5]$/i.test(calfId)
                || /^CALF-2025-005$/i.test(calfId)
                || /^6-(?:SB|AB)-26-CB-2024-00[34]$/i.test(calfId)
                || /^CB-2024-00[1-5]$/i.test(motherId)
                || /^Sample Cooperative(?:\s|$)/i.test(cooperative)
                || /Sample record/i.test(remarks);
        },
        reportedYearFromCalfId(calfId, fallback='') { const id=this.calfIdSeries(calfId); return id ? String(id.year) : String(fallback ?? ''); },
        calfIdSeries(calfId) { const match=String(calfId??'').trim().match(/^\d[A-Za-z0-9]+(\d{2})(\d{3})$/); return match?{year:2000+Number(match[1]),series:Number(match[2])}:null; },
        get allRecords() {
            const calfId = record => String(record.calf_id || '').trim().toLowerCase();
            const shared = new Set(this.inventoryCalves.map(calfId).filter(Boolean));
            return [...this.records.filter(record => !shared.has(calfId(record))), ...this.inventoryCalves]
                .map(record => ({...record, reported_year: this.reportedYearFromCalfId(record.calf_id, record.reported_year)}));
        },
        transferCalfIds(exits, animals) {
            const normalize = value => String(value ?? '').trim().toLowerCase();
            const identifiers = new Set(exits.map(exit => normalize(exit.animal_identifier)).filter(Boolean));
            const inventoryIds = new Set(exits.map(exit => normalize(exit.carabao_id)).filter(Boolean));
            for (const animal of animals) {
                if (!inventoryIds.has(normalize(animal.id))) continue;
                for (const alias of [animal.registry_number, animal.animal_id, animal.bolus_id, animal.tag_number, animal.tag_no]) {
                    const id = normalize(alias);
                    if (id) identifiers.add(id);
                }
            }
            return identifiers;
        },
        // An inventory animal is a calf when it has a Dam ID (mother). Placeholder values do not count.
        hasDam(a) { const v = String(a?.dam_id ?? '').trim().toLowerCase(); return v !== '' && !['-','--','n/a','na','none','unknown','0'].includes(v); },
        normalizeProvince(value) { const text = String(value ?? '').replace(/\s+/g,' ').trim(); if (!text) return ''; const aliases = {nuevaecija:'Nueva Ecija', nuevaecjia:'Nueva Ecija', nuevaecijaprovince:'Nueva Ecija'}; return aliases[text.toLowerCase().replace(/[^a-z]/g,'')] || text; },
        formatBirthDate(value) {
            if(!value)return '—';
            const date=String(value).slice(0,10);
            const parsed=new Date(date+'T00:00:00Z');
            if(Number.isNaN(parsed.getTime()) || parsed.toISOString().slice(0,10)!==date)return value;
            const month=parsed.toLocaleDateString('en-US',{month:'short',timeZone:'UTC'});
            return [date.slice(8,10),month,date.slice(2,4)].join('-');
        },
        farmerLabel(f) { if (typeof f === 'string') return f.trim(); const direct = String(f?.full_name ?? f?.farmer_name ?? f?.name ?? f?.recipient_name ?? '').trim(); if (direct) return direct; return [f?.last_name ?? f?.lastname, f?.first_name ?? f?.firstname, f?.middle_name ?? f?.middlename].map(v => String(v ?? '').trim()).filter(Boolean).join(', '); },
        inventoryToCalf(a, coops, farmers) {
            const norm = v => String(v ?? '').trim().toLowerCase().replace(/\s+/g,' ');
            const fid = String(a.farmer_id ?? '').trim();
            let farmer = '';
            const f = fid ? farmers.find(x => String(x?.id ?? x?.farmer_id ?? '').trim() === fid) : null;
            if (f) farmer = this.farmerLabel(f);
            else { const raw = String(a.farmer_recipient ?? '').trim(); if (raw) { const m = farmers.find(x => [x?.id, x?.farmer_id, x?.farmer_code, x?.code].filter(v => v !== null && v !== undefined && String(v).trim() !== '').map(v => String(v).trim()).includes(raw) || norm(this.farmerLabel(x)) === norm(raw)); farmer = m ? this.farmerLabel(m) : raw; } }
            const cid = String(a.cooperative_id ?? '').trim();
            const c = cid ? coops.find(x => String(x?.id ?? x?.db_id ?? x?.cooperative_id ?? '').trim() === cid) : null;
            const cooperative = c ? String(c?.cooperative_name ?? c?.name ?? c?.coop_name ?? c?.association_name ?? '').trim() : String(a.cooperative_name ?? a.association_coop_institution ?? '').trim();
            const sexRaw = String(a.sex ?? '').trim().toLowerCase();
            const sex = ['f','female'].includes(sexRaw) ? 'Female' : (['m','male'].includes(sexRaw) ? 'Male' : '');
            const dob = String(a.birth_date || a.birthday || '').slice(0,10);
            const exit = String(a.nature_of_exit ?? '').trim();
            const st = String(a.status ?? '').trim().toLowerCase();
            const animal_status = exit ? (/dead|death|died/i.test(exit) ? 'Dead' : 'Out') : (st && st !== 'live' ? (/dead/.test(st) ? 'Dead' : 'Out') : 'Live');
            return {
                id: 'inv-' + a.id, inventory_id: a.id, source: 'Inventory',
                calf_id: String(a.registry_number || a.bolus_id || a.tag_no || a.animal_id || a.id).trim(),
                mother_id: String(a.dam_id).trim(), farmer, province: this.normalizeProvince(a.province),
                municipality: a.municipality || '', barangay: a.barangay || '', cooperative,
                date_of_birth: dob, sex, breed: String(a.breed ?? '').trim(), sire_id: String(a.sire_id ?? '').trim(),
                classification: a.classification, parity: a.parity,
                monitored_date: '', reported_year: /^\d{4}$/.test(dob.slice(0,4)) ? dob.slice(0,4) : '',
                birth_outcome: 'Live Birth', animal_status, breed_series: '',
                remarks: [a.dam_name ? 'Dam: ' + a.dam_name : '', a.sire_name ? 'Sire: ' + a.sire_name : '', a.remarks || ''].filter(Boolean).join(' · ')
            };
        },
        async loadInventoryCalves(announce = false) {
            this.inventoryLoading = true;
            const readList = async url => {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error(`${url} returned HTTP ${response.status}`);
                const payload = await response.json();
                const list = Array.isArray(payload) ? payload : (payload?.data ?? payload?.records ?? payload?.farmers ?? payload?.cooperatives);
                if (!Array.isArray(list)) throw new Error(`${url} did not return a list.`);
                return list;
            };
            try {
                const animals = await readList('/admin/carabao-inventory-api');
                const databaseCalves = await readList('/admin/calf-drop-api').catch(error => {
                    console.warn('Calf Drop database history unavailable:', error);
                    return null;
                });
                let coops = [], farmers = [];
                try { [coops, farmers] = await Promise.all([readList('/admin/cooperatives/data'), readList('/admin/farmers/data')]); } catch (e) { console.warn('Farmer/cooperative names unavailable:', e); }
                this.calfCooperatives=coops;
                this.calfFarmers=farmers;
                this.calfInventory=animals;
                try {
                    this.transferredCalfIds = this.transferCalfIds(await readList('/admin/calf-transfer-api'), animals);
                } catch (error) {
                    console.warn('Calf transfer history unavailable:', error);
                    this.$dispatch('cbed-data-status', {
                        title: 'Calf transfer counts may be incomplete',
                        description: 'Reload the page to retry loading Nature of Exit history.',
                        type: 'error'
                    });
                }
                const seenCalfIds=new Set();
                const normalizeId = value => String(value ?? '').trim().toLowerCase();
                const inventoryById = new Map();
                for (const animal of animals) {
                    for (const identifier of [animal.animal_id, animal.registry_number, animal.bolus_id, animal.tag_number]) {
                        const key = normalizeId(identifier);
                        if (key && !inventoryById.has(key)) inventoryById.set(key, animal);
                    }
                }
                const calfRows = (databaseCalves ?? []).map(calf => {
                    const animal = inventoryById.get(normalizeId(calf.calf_id));
                    const calfSex = normalizeId(calf.sex).charAt(0);
                    const inventorySex = normalizeId(animal?.sex).charAt(0);
                    const matchingAnimal = calfSex && inventorySex && calfSex !== inventorySex ? null : animal;
                    return {
                        ...calf, id: 'db-calf-' + calf.id, database_id: calf.id, source: 'Calf Drop',
                        classification: matchingAnimal?.classification, parity: matchingAnimal?.parity,
                    };
                }).concat(animals.filter(a => this.hasDam(a)).map(a => this.inventoryToCalf(a, coops, farmers)));
                this.inventoryCalves=calfRows
                    .filter(calf=>{
                        const id=String(calf.calf_id||'').trim().toLowerCase();
                        if(!id)return Boolean(calf.database_id);
                        if(seenCalfIds.has(id))return false;
                        seenCalfIds.add(id);
                        return true;
                    })
                    .sort((a,b)=>String(b.date_of_birth||'').localeCompare(String(a.date_of_birth||'')));
                if (this.showAdd && !this.showMatingPicker && this.motherInput && !this.addForm.mother_id) {
                    this.updateManualMother(this.motherInput);
                }
                this.page = 1;
                if (databaseCalves === null) {
                    this.$dispatch('cbed-data-status', {
                        title: 'Calf drop history unavailable',
                        description: 'Inventory calves are shown. Reload the page to retry loading Calf Drop history.',
                        type: 'error'
                    });
                } else if (announce) {
                    this.$dispatch('cbed-data-status', {
                        description: 'Calf records are retrieved from Calf Drop and Carabao Inventory. Older entries may also be saved in this browser.'
                    });
                }
            } catch (e) {
                this.inventoryCalves = [];
                console.error('Calf Drop inventory load failed:', e);
                this.$dispatch('cbed-data-status', {
                    title: 'Calf records unavailable',
                    description: 'Reload the page to retry loading records from the database.',
                    type: 'error'
                });
            } finally { this.inventoryLoading = false; }
        },
        get matchingCalfRecords() {
            const normalize=value=>String(value ?? '').normalize('NFD').replace(/\p{M}/gu,'').toLowerCase();
            const terms=normalize(this.search).trim().split(/\s+/).filter(Boolean);
            return this.allRecords.filter(record=>{
                if((this.filterCooperative && String(record.cooperative ?? '').trim()!==this.filterCooperative)
                    || (this.filterProvince && record.province!==this.filterProvince)
                    || (this.filterBreed && record.breed!==this.filterBreed)
                    || (this.filterSex && record.sex!==this.filterSex))return false;
                if(!terms.length)return true;
                const values=this.fields.map(field=>normalize(record[field]));
                return terms.every(term=>values.some(value=>value.includes(term)));
            });
        },
        recordReportedYear(record) {
            return String(record.reported_year ?? '').trim() || String(record.date_of_birth ?? '').slice(0, 4);
        },
        get reportedYearOptions() {
            return [...new Set(this.allRecords.map(record => this.recordReportedYear(record)))]
                .filter(year => /^\d{4}$/.test(year))
                .sort((a, b) => Number(b) - Number(a));
        },
        get cooperativeFilterOptions() {
            const records = this.allRecords.filter(record =>
                !this.filterReportedYear || this.recordReportedYear(record) === this.filterReportedYear
            );
            return this.options('cooperative', records);
        },
        changeReportedYear(year) {
            this.filterReportedYear = year;
            if (!this.cooperativeFilterOptions.includes(this.filterCooperative)) this.filterCooperative = '';
            this.page = 1;
        },
        get filteredRecords() {
            return this.matchingCalfRecords
                .filter(record => !this.filterReportedYear || this.recordReportedYear(record) === this.filterReportedYear)
                .sort((a, b) => {
                    const first = this.calfIdSeries(a.calf_id);
                    const second = this.calfIdSeries(b.calf_id);
                    if (first && second) return second.year - first.year || second.series - first.series;
                    if (first) return -1;
                    if (second) return 1;
                    return String(a.calf_id ?? '').localeCompare(String(b.calf_id ?? ''), undefined, {numeric: true, sensitivity: 'base'});
                });
        },
        get summaryCards() {
            const records=this.filteredRecords;
            const status=record=>String(record.animal_status ?? '').trim().toLowerCase();
            return [
                {title:'Total Calves',value:records.filter(r=>status(r)==='live').length,description:'Live calves matching filters',icon:'fa-solid fa-list-check'},
                {title:'Dead / Out',value:records.filter(r=>['dead','out'].includes(status(r))).length,description:'Calves recorded as Dead or Out',icon:'fa-solid fa-circle-minus'},
                {title:'Female Calves',value:records.filter(r=>r.sex==='Female').length,description:'Female calves across all statuses',icon:'fa-solid fa-venus'},
                {title:'Male Calves',value:records.filter(r=>r.sex==='Male').length,description:'Male calves across all statuses',icon:'fa-solid fa-mars'}
            ];
        },
        get birthYearSummary() {
            return this.summarizeBirthYears(this.matchingCalfRecords);
        },
        get birthYearReportRows() {
            const hasCalfId = record => String(record.calf_id ?? '').trim() !== '';
            const withCalfId = new Map(this.summarizeBirthYears(this.matchingCalfRecords.filter(hasCalfId))
                .map(item => [item.year, item]));
            const historyTotals = this.calfHistory?.yearlyTotals ?? {};
            const registeredTotals = new Map(this.summarizeBirthYears(this.allRecords.filter(hasCalfId))
                .map(item => [item.year, item.count]));
            const years = new Set([...withCalfId.keys(), ...Object.keys(historyTotals)]);
            const rows = [...years].sort((a, b) => Number(b) - Number(a)).map(year => {
                const historyTotal = historyTotals[year] ?? null;
                const registeredTotal = registeredTotals.get(year) ?? 0;
                return {
                    year,
                    withCalfId: withCalfId.get(year),
                    historyTotal,
                    registeredTotal,
                    total: historyTotal ?? registeredTotal,
                    notReported: historyTotal === null ? null : Math.max(0, historyTotal - registeredTotal),
                };
            });
            const maximumTotal = Math.max(1, ...rows.map(row => row.total));
            return rows.map(row => ({ ...row, totalPercent: row.total / maximumTotal * 100 }));
        },
        openBirthYear(item, unknownSexOnly = false) {
            this.selectedBirthYear = item;
            this.birthYearSex = unknownSexOnly ? 'unknown' : '';
            this.birthYearCooperative = '';
            this.birthYearStatus = '';
            this.$nextTick(() => this.$refs.birthYearDialog.showModal());
        },
        get birthYearDialogRecords() {
            if (!this.selectedBirthYear) return [];
            const records = this.birthYearSex === 'unknown' ? this.selectedBirthYear.unknownSexRecords : this.selectedBirthYear.records;
            return records.filter(record => {
                const sex = String(record.sex ?? '').trim().toLowerCase();
                const matchesSex = !this.birthYearSex || this.birthYearSex === 'unknown'
                    || sex === this.birthYearSex || sex === this.birthYearSex[0];
                return matchesSex
                    && (!this.birthYearCooperative || String(record.cooperative ?? '').trim() === this.birthYearCooperative)
                    && (!this.birthYearStatus || this.birthYearStatusLabel(record) === this.birthYearStatus);
            });
        },
        get birthYearCooperativeOptions() {
            return this.options('cooperative', this.selectedBirthYear?.records ?? []);
        },
        get birthYearStatusOptions() {
            return [...new Set((this.selectedBirthYear?.records ?? []).map(record => this.birthYearStatusLabel(record)))]
                .sort((a, b) => a.localeCompare(b));
        },
        get cooperativeDirectoryNames() {
            return new Map(this.calfCooperatives
                .map(cooperative => String(cooperative.cooperative_name ?? '').trim())
                .filter(Boolean)
                .map(name => [name.toLowerCase(), name]));
        },
        get birthYearPrintSummary() {
            const cooperatives = new Map();
            const directoryNames = this.cooperativeDirectoryNames;
            for (const record of this.birthYearDialogRecords) {
                if (String(record.animal_status ?? '').trim().toLowerCase() === 'dead') continue;
                const name = directoryNames.get(String(record.cooperative ?? '').trim().toLowerCase());
                if (!name) continue;
                if (!cooperatives.has(name)) cooperatives.set(name, { female: 0, male: 0, total: 0 });
                const counts = cooperatives.get(name);
                const sex = String(record.sex ?? '').trim().toLowerCase();
                if (sex === 'female' || sex === 'f') counts.female++;
                if (sex === 'male' || sex === 'm') counts.male++;
                counts.total++;
            }
            return [...cooperatives].sort(([a], [b]) => a.localeCompare(b));
        },
        printBirthYear() {
            const summary = this.birthYearPrintSummary;
            const count = summary.reduce((total, [, row]) => total + row.total, 0);
            if (!count) return;

            const sex = { female: 'Female', male: 'Male', unknown: 'Unknown sex' };
            const details = [
                count + (count === 1 ? ' record' : ' records') + ' (dead excluded)',
                this.birthYearCooperative || 'All cooperatives',
                sex[this.birthYearSex] || 'All sexes',
                this.birthYearStatus || 'All statuses',
            ].join(' · ');
            this.printCalfTable(
                'Calf Records for ' + this.selectedBirthYear.year,
                details,
                [{
                    headers: [['Cooperative', 'F', 'M', 'Total'].map(label => ({ label }))],
                    rows: summary.map(([name, counts]) => [name, counts.female, counts.male, counts.total]),
                }]
            );
        },
        get calfPrintYears() {
            const years = this.summarizeBirthYears(this.allRecords).map(item => item.year);
            return [...new Set([this.currentYear, ...years])].sort((a, b) => Number(b) - Number(a));
        },
        openCooperativeCalfPrint() {
            this.calfPrintFromYear = '';
            this.calfPrintToYear = this.currentYear;
            this.$refs.calfPrintRangeDialog.showModal();
        },
        cooperativeCalfHistoryRows(reportYears) {
            const recordsByCooperative = new Map([...this.cooperativeDirectoryNames.keys()].map(name => [name, []]));
            for (const record of this.allRecords) {
                if (String(record.animal_status ?? '').trim().toLowerCase() === 'dead') continue;
                const name = String(record.cooperative ?? '').trim().toLowerCase();
                recordsByCooperative.get(name)?.push(record);
            }

            return this.calfCooperatives.filter(cooperative => String(cooperative.cooperative_name ?? '').trim())
                .map((cooperative, index) => {
                    const name = cooperative.cooperative_name.trim();
                    const years = new Map(this.summarizeBirthYears(recordsByCooperative.get(name.toLowerCase()))
                        .map(year => [year.year, year.records]));
                    const countSex = (year, sex) => (years.get(year) ?? []).filter(record => {
                        const value = String(record.sex ?? '').trim().toLowerCase();
                        return value === sex || value === sex[0];
                    }).length || '';
                    return [
                        index + 1, name, cooperative.barangay, cooperative.municipality, cooperative.province,
                        ...reportYears.flatMap(year => [countSex(year, 'female'), countSex(year, 'male')]),
                    ];
                });
        },
        printCooperativeCalfHistory() {
            const availableYears = this.calfPrintYears;
            if (!availableYears.includes(this.calfPrintFromYear) || !availableYears.includes(this.calfPrintToYear)
                || this.calfPrintFromYear > this.calfPrintToYear) return;
            const firstYear = Number(this.calfPrintFromYear);
            const lastYear = Number(this.calfPrintToYear);
            const years = Array.from({ length: lastYear - firstYear + 1 }, (_, index) => String(firstYear + index));
            const locationHeaders = ['No.', 'Cooperative / Association', 'Barangay', 'Municipality', 'Province'];
            const sections = [];
            const yearsPerTable = 4;
            for (let start = 0; start < years.length; start += yearsPerTable) {
                const sectionYears = years.slice(start, start + yearsPerTable);
                const rows = this.cooperativeCalfHistoryRows(sectionYears);
                const sexHeaders = sectionYears.flatMap(() => [{ label: 'F' }, { label: 'M' }]);
                const totals = sexHeaders.map((_, index) =>
                    rows.reduce((sum, row) => sum + Number(row[locationHeaders.length + index]), 0)
                );
                sections.push({
                    headers: [
                        [
                            ...locationHeaders.map(label => ({ label, rowSpan: 2 })),
                            ...sectionYears.map(year => ({ label: 'Calf Drop ' + year, colSpan: 2 })),
                        ],
                        sexHeaders,
                    ],
                    rows,
                    footer: { label: 'Total', locationColumns: locationHeaders.length - 1, totals },
                });
            }
            const period = firstYear === lastYear ? String(firstYear) : firstYear + '–' + lastYear;
            this.printCalfTable('Calf Drop History by Cooperative', period + ' · Dead excluded', sections, true);
        },
        async printCalfTable(title, details, sections, landscape = false) {
            const printWindow = window.open('', '_blank');
            if (!printWindow) {
                window.alert('Allow pop-ups for this site to print calf records.');
                return;
            }
            const doc = printWindow.document;
            doc.title = title;
            const typography = doc.createElement('link');
            typography.rel = 'stylesheet';
            typography.href = new URL('/css/cbed-typography.css?v=1', location.origin);
            const typographyReady = new Promise(resolve => {
                typography.onload = resolve;
                typography.onerror = resolve;
            });
            doc.head.append(typography);

            const style = doc.createElement('style');
            style.textContent = `
                @page { ${landscape ? 'size: 13in 8.5in; margin: 7mm;' : 'margin: 12mm;'} }
                body { ${landscape ? 'margin: 0; font-size: 9.5pt; line-height: 1.15;' : 'font-size: 11pt;'} color: #0f172a; }
                h1 { margin: 0 0 ${landscape ? '2mm' : '4mm'}; font-size: ${landscape ? '15pt' : '18pt'}; }
                p { margin: 0 0 ${landscape ? '2mm' : '4mm'}; }
                table { width: 100%; border-collapse: collapse; }
                table + table { break-before: page; }
                table.cooperative-print { table-layout: fixed; }
                .cooperative-print th, .cooperative-print td { overflow-wrap: anywhere; }
                th, td { border: 1px solid #64748b; padding: ${landscape ? '0.65mm 1mm' : '2mm'}; }
                .cooperative-print td.numeric, .cooperative-print thead tr:nth-child(2) th { padding-inline: 0.5mm; }
                th { background: #e2e8f0; text-align: center; }
                thead tr:nth-child(2) th { background: #ffedd5; }
                td.numeric { text-align: center; }
                thead { display: table-header-group; break-inside: avoid; page-break-inside: avoid; }
                tfoot { display: table-row-group; font-weight: bold; }
                tfoot th, tfoot td { background: #e2e8f0; }
                tr { break-inside: avoid; }
            `;
            doc.head.append(style);

            const heading = doc.createElement('h1');
            heading.textContent = title;
            const filters = doc.createElement('p');
            filters.textContent = details;
            doc.body.append(heading, filters);
            for (const { headers, rows, footer } of sections) {
                doc.body.append(this.buildCalfPrintTable(doc, headers, rows, footer, landscape));
            }
            await typographyReady;
            await doc.fonts?.ready;
            if (printWindow.closed) return;
            printWindow.addEventListener('afterprint', () => printWindow.close(), { once: true });
            printWindow.focus();
            printWindow.print();
        },
        buildCalfPrintTable(doc, headers, rows, footer, landscape) {
            const table = doc.createElement('table');
            if (landscape) {
                table.className = 'cooperative-print';
                const columns = doc.createElement('colgroup');
                const countWidths = headers[1].map(() => 3);
                const cooperativeWidth = 59 - countWidths.length * 3;
                for (const width of [4, cooperativeWidth, 13, 14, 10, ...countWidths]) {
                    const column = doc.createElement('col');
                    column.style.width = width + '%';
                    columns.append(column);
                }
                table.append(columns);
            }
            const head = table.createTHead();
            for (const labels of headers) {
                const header = head.insertRow();
                for (const { label, colSpan = 1, rowSpan = 1 } of labels) {
                    const cell = doc.createElement('th');
                    cell.scope = colSpan > 1 ? 'colgroup' : 'col';
                    cell.colSpan = colSpan;
                    cell.rowSpan = rowSpan;
                    cell.textContent = label;
                    header.append(cell);
                }
            }
            const body = table.createTBody();
            for (const values of rows) {
                const row = body.insertRow();
                for (const value of values) {
                    const cell = row.insertCell();
                    cell.textContent = value ?? '';
                    if (typeof value === 'number') cell.className = 'numeric';
                }
            }
            if (footer) {
                const row = table.createTFoot().insertRow();
                const label = doc.createElement('th');
                label.scope = 'row';
                label.textContent = footer.label;
                row.append(label);
                row.insertCell().colSpan = footer.locationColumns;
                for (const total of footer.totals) {
                    const cell = row.insertCell();
                    cell.className = 'numeric';
                    cell.textContent = total;
                }
            }
            return table;
        },
        get birthYearDialogStats() {
            const records = this.birthYearDialogRecords;
            const statuses = new Map();
            const directoryNames = this.cooperativeDirectoryNames;
            const representedCooperatives = new Set();
            let female = 0;
            let male = 0;
            let heifers = 0;
            let cows = 0;
            let parityTotal = 0;
            for (const record of records) {
                const cooperative = String(record.cooperative ?? '').trim().toLowerCase();
                if (directoryNames.has(cooperative)) representedCooperatives.add(cooperative);
                const sex = String(record.sex ?? '').trim().toLowerCase();
                const parity = Number(String(record.parity ?? '').trim() || NaN);
                if (sex === 'female' || sex === 'f') {
                    female++;
                    if (parity === 0) heifers++;
                    if (Number.isFinite(parity) && parity >= 1) cows++;
                }
                if (sex === 'male' || sex === 'm') male++;

                if (Number.isFinite(parity)) parityTotal += parity;

                const label = this.birthYearStatusLabel(record);
                statuses.set(label, (statuses.get(label) ?? 0) + 1);
            }
            return {
                total: records.length, female, male, heifers, cows, parityTotal,
                cooperativeTotal: representedCooperatives.size,
                statuses: [...statuses].sort(([a], [b]) => a.localeCompare(b)),
            };
        },
        birthYearCalfStatus(record) {
            const calfId = String(record.calf_id ?? '').trim().toLowerCase();
            return this.transferredCalfIds.has(calfId)
                ? 'Transferred'
                : String(record.animal_status ?? '').trim();
        },
        birthYearStatusLabel(record) {
            const status = this.birthYearCalfStatus(record).toLowerCase();
            return status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Unrecorded';
        },
        birthYearCalfClassification(record) {
            const classification = String(record.classification ?? '').trim().replace(/^[-–—]$/, '');

            const birthday = String(record.date_of_birth ?? '').slice(0, 10);
            if (!/^\d{4}-\d{2}-\d{2}$/.test(birthday)) return classification || 'Unclassified';
            const today = new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Manila' });
            if (today < birthday) return classification || 'Unclassified';
            const age = Number(today.slice(0, 4)) - Number(birthday.slice(0, 4))
                - Number(today.slice(5) < birthday.slice(5));
            if (age >= 1 && classification) return classification;

            const sex = String(record.sex ?? '').trim().toLowerCase();
            if (sex === 'female' || sex === 'f') {
                if (age < 1) return 'Female Calf';
                if (age < 2) return 'Yearling Heifer';
                return 'Heifer';
            }
            if (sex === 'male' || sex === 'm') {
                if (age < 1) return 'Male Calf';
                if (age < 2) return 'Yearling Bull';
                if (age < 4) return 'Junior Bull';
                return 'Senior Bull';
            }
            return classification || 'Unknown sex';
        },
        birthYearCalfParityDisplay(record) {
            const classification = this.birthYearCalfClassification(record).toLowerCase();
            return ['yearling heifer', 'female calf'].includes(classification) ? '—' : (record.parity ?? '—');
        },
        get cooperativeBirthYearSummaries() {
            const directoryNames = this.cooperativeDirectoryNames;
            const groups=new Map();
            for(const record of this.matchingCalfRecords){
                const name = directoryNames.get(String(record.cooperative ?? '').trim().toLowerCase());
                if (!name) continue;
                if(!groups.has(name))groups.set(name,[]);
                groups.get(name).push(record);
            }
            return [...groups.entries()]
                .sort(([a],[b])=>a.localeCompare(b))
                .map(([name,records])=>({name,years:this.summarizeBirthYears(records)}))
                .filter(group=>group.years.length)
                .map(({name,years})=>({name,years:Object.fromEntries(years.map(item=>[item.year,item]))}));
        },
        summarizeBirthYears(records) {
            const counts=new Map();
            records.forEach(record=>{
                const date=String(record.date_of_birth??'').slice(0,10);
                if(!/^\d{4}-\d{2}-\d{2}$/.test(date))return;
                const parsed=new Date(`${date}T00:00:00`);
                if(Number.isNaN(parsed.getTime())||parsed.getFullYear()!==Number(date.slice(0,4))
                    ||parsed.getMonth()+1!==Number(date.slice(5,7))||parsed.getDate()!==Number(date.slice(8,10)))return;
                const year=date.slice(0,4);
                if(!counts.has(year))counts.set(year,new Map());
                const yearRecords=counts.get(year);
                const id=String(record.calf_id||record.id);
                if(!yearRecords.has(id))yearRecords.set(id,record);
            });
            const years=[...counts.entries()].map(([year,records])=>{
                const breakdown={male:{live:0,dead:0,transferred:0},female:{live:0,dead:0,transferred:0}};
                const unknownSexRecords = [];
                for(const record of records.values()){
                    const sex=String(record.sex??'').trim().toLowerCase();
                    if (!['female', 'f', 'male', 'm'].includes(sex)) unknownSexRecords.push(record);
                    const status=this.birthYearCalfStatus(record).toLowerCase();
                    if((sex==='male'||sex==='female')&&['live','dead','transferred'].includes(status)){
                        breakdown[sex][status]++;
                    }
                }
                return {year,count:records.size,unknownSexCount:unknownSexRecords.length,unknownSexRecords,records:[...records.values()],...breakdown};
            });
            const max=Math.max(1,...years.map(item=>item.count));
            return years.map(item=>({...item,percent:item.count/max*100})).sort((a,b)=>Number(b.year)-Number(a.year));
        },
        totalPages() { return Math.max(1, Math.ceil(this.filteredRecords.length / this.perPage)); },
        paginatedRecords() { const list=this.filteredRecords; if(this.page>this.totalPages()) this.page=this.totalPages(); return list.slice((this.page-1)*this.perPage,this.page*this.perPage); },
        options(field, records = this.allRecords) { return [...new Set(records.map(r=>String(r[field] ?? '').trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b,undefined,{numeric:true})); },
        distribution(field) { const counts={}; this.filteredRecords.forEach(r=>{const name=String(r[field]||'Not specified');counts[name]=(counts[name]||0)+1;}); const max=Math.max(1,...Object.values(counts)); return Object.entries(counts).map(([name,count])=>({name,count,percent:count/max*100})).sort((a,b)=>b.count-a.count||a.name.localeCompare(b.name)); },
        label(field) { const labels={calf_id:'Calf ID',mother_id:'Mother ID',farmer:'Farmer Name',province:'Province',cooperative:'Cooperative',date_of_birth:'Date of Birth / Event Date',sex:'Sex',breed:'Breed',sire_id:'Sire / Bull ID',monitored_date:'Monitored Date',reported_year:'Reported Year',birth_outcome:'Birth Outcome',animal_status:'Animal Status',breed_series:'Breed Series',remarks:'Remarks'}; return labels[field]||field; },
        generatedId(r) { const mother=String(r.mother_id||'').trim().replace(/\s+/g,'-'); const year=String(r.reported_year|| (r.date_of_birth ? r.date_of_birth.slice(0,4) : new Date().getFullYear())).slice(-2); const series=this.calfSeries(r) || String(r.breed_series||'6').trim() || '6'; if (!mother || r.birth_outcome === 'Live Birth') return ''; const code=r.birth_outcome==='Stillbirth'?'SB':'AB'; return `${series}-${code}-${year}-${mother}`; },
        emptyRow() { return {_key:++this.rowKey,calf_id:'',mother_id:'',farmer:'',province:'',municipality:'',barangay:'',cooperative:'',date_of_birth:'',sex:'',breed:'',sire_id:'',monitored_date:new Date().toISOString().slice(0,10),reported_year:String(new Date().getFullYear()),birth_outcome:'Live Birth',animal_status:'Live',breed_series:'6',remarks:''}; },
        openAdd() {
            this.showSeriesOptions = false;
            this.verifiedSeries = null;
            this.seriesSelection = null;
            this.sirePickerOpen = false;
            this.sireTyped = false;
            this.addForm={...this.emptyRow(),cooperative_id:'',farmer_id:''};
            this.showMatingPicker=true;
            this.cooperativePickerOpen=false;
            this.farmerPickerOpen=false;
            this.motherPickerOpen=false;
            this.motherInput='';
            this.showAdd=true;
            this.loadDueMatings();
        },
        openMonitoringCalf(context) {
            this.matingLookup++;
            this.monitoringCalf = context;
            this.showSeriesOptions = false;
            this.verifiedSeries = null;
            this.seriesSelection = null;
            this.notice = '';
            this.sirePickerOpen = false;
            this.sireTyped = false;
            this.showMatingPicker = Boolean(context.dam.has_mating_record);
            this.matingRecords = [];
            this.loadingDueMatings = false;
            this.dueMatingsError = '';
            const data = context.data;
            const matingMethod = String(data.mating_method ?? data.nm_ai ?? data.breeding_method ?? '').trim().toUpperCase();
            this.addForm = {
                ...this.emptyRow(),
                mother_id: String(context.dam.animal_id || context.dam.registry_number || context.dam.id),
                cooperative_id: context.cooperativeId,
                herd_code: data.herd_code ?? '',
                cooperative: context.cooperative,
                farmer: context.farmer,
                province: data.province ?? context.dam.province ?? '',
                municipality: data.municipality ?? context.dam.municipality ?? '',
                barangay: data.barangay ?? context.dam.barangay ?? '',
                date_of_birth: data.date_of_birth ?? '',
                birth_outcome: data.birth_outcome ?? 'Live Birth',
                sex: data.sex ?? '',
                sire_id: data.sire_id ?? '',
                mating_method: ['AI', 'ARTIFICIAL INSEMINATION'].includes(matingMethod) ? 'AI'
                    : ['N', 'NM', 'NATURAL', 'NATURAL MATING'].includes(matingMethod) ? 'NM' : '',
                animal_status: data.calf_status ?? 'Live',
                monitored_date: data.monitored_date ?? context.reportDate,
                reported_year: data.reported_year ?? (String(context.reportDate).slice(0, 4) || this.currentYear),
                remarks: data.remarks ?? data.findings ?? '',
            };
            this.showAdd = true;
            this.$root.showModal();
            if (data.registry_series && this.seriesQuery) {
                this.selectCalfSeries(String(data.registry_series).padStart(3, '0'));
                this.toggleSeriesOptions(true);
                this.verifyCalfSeries();
            }
            this.loadMonitoringMatings(context);
        },
        async loadMonitoringMatings(context = this.monitoringCalf) {
            const lookup = ++this.matingLookup;
            this.loadingDueMatings = true;
            this.dueMatingsError = '';
            try {
                if (!navigator.onLine && context.cachedMatings) {
                    this.matingRecords = context.cachedMatings;
                    return;
                }
                const response = await fetch(context.matingRecordsUrl, {headers: {Accept: 'application/json'}, cache: 'no-store'});
                if (!response.ok || response.redirected) {
                    const error = new Error();
                    error.status = response.redirected ? 401 : response.status;
                    throw error;
                }
                const records = await response.json();
                if (this.matingLookup === lookup) this.matingRecords = records;
            } catch (error) {
                if (this.matingLookup === lookup) {
                    if (!error.status && context.cachedMatings) this.matingRecords = context.cachedMatings;
                    else this.dueMatingsError = !error.status && !context.cachedMatings
                        ? 'Pregnancy and mating details for this animal aren’t available offline yet. Open Monitoring while connected to update them.'
                        : 'Could not load pregnancy and mating details.';
                }
            } finally {
                if (this.matingLookup === lookup) this.loadingDueMatings = false;
            }
        },
        closeCalfForm() {
            this.showAdd = false;
            if (monitoringMode) this.$root.close();
        },
        finishMonitoringCalf(data) {
            this.$dispatch('monitoring-calf-draft', {index: this.monitoringCalf.index, data});
            this.closeCalfForm();
        },
        get manualFarmers() {
            if(!this.addForm.cooperative_id)return [];
            const mappedFarmers = this.selectedCooperativeCode === 'AST'
                ? new Set(this.calfInventory.filter(animal => this.isMappedAnimalIndustryDam(animal)).map(animal => String(animal.farmer_id)))
                : new Set();
            return this.calfFarmers.filter(farmer=>String(farmer.cooperative_id)===this.addForm.cooperative_id || mappedFarmers.has(String(farmer.id)));
        },
        get matchingCooperatives() {
            const query=this.cooperativeQuery.trim().toLowerCase();
            return this.calfCooperatives.filter(cooperative=>String(cooperative.cooperative_name || '').toLowerCase().includes(query));
        },
        get matchingManualFarmers() {
            const terms=this.farmerQuery.toLowerCase().trim().split(/\s+/).filter(Boolean);
            return this.manualFarmers.filter(farmer=>{
                const name=this.farmerLabel(farmer).toLowerCase();
                return terms.every(term=>name.includes(term));
            });
        },
        toggleFarmerPicker() {
            this.farmerPickerOpen=!this.farmerPickerOpen;
            if(this.farmerPickerOpen){
                this.farmerQuery='';
                this.$nextTick(()=>this.$refs.farmerSearch.focus());
            }
        },
        selectManualFarmer(farmer) {
            const id=String(farmer.id);
            if(id!==this.addForm.farmer_id)this.changeManualFarmer(id);
            this.farmerPickerOpen=false;
            this.$refs.farmerTrigger.focus();
        },
        toggleCooperativePicker() {
            this.cooperativePickerOpen=!this.cooperativePickerOpen;
            if(this.cooperativePickerOpen){
                this.cooperativeQuery='';
                this.$nextTick(()=>this.$refs.cooperativeSearch.focus());
            }
        },
        selectManualCooperative(cooperative) {
            const id=String(cooperative.id);
            if(id!==this.addForm.cooperative_id)this.changeManualCooperative(id);
            this.cooperativePickerOpen=false;
            this.$refs.cooperativeTrigger.focus();
        },
        get manualMothers() {
            const normalize = value => String(value ?? '').trim().toUpperCase();
            const now = new Date();
            const today = new Date(now.getTime() - now.getTimezoneOffset() * 60_000).toISOString().slice(0, 10);
            const date = this.addForm.date_of_birth || today;
            const cooperativeFilter = this.addForm.cooperative_id;
            const farmerFilter = this.addForm.farmer_id;
            const cooperativeIds = new Set(this.calfCooperatives.map(cooperative => String(cooperative.id)));
            const farmers = cooperativeFilter ? this.manualFarmers : this.calfFarmers;
            const farmerIds = new Set(farmers.filter(farmer => {
                const cooperativeId = String(farmer.cooperative_id);
                return cooperativeIds.has(cooperativeId)
                    && (!farmerFilter || String(farmer.id) === farmerFilter);
            }).map(farmer => String(farmer.id)));
            const recordedDams = new Set(this.allRecords
                .filter(record => this.isWithinCalfEventInterval(record, date))
                .map(record => normalize(record.mother_id)).filter(Boolean));
            return this.calfInventory.filter(animal=>farmerIds.has(String(animal.farmer_id))
                && (this.selectedCooperativeCode !== 'AST' || String(animal.cooperative_id) === cooperativeFilter || this.isMappedAnimalIndustryDam(animal))
                && animal.animal_id && animal.live_breedable_female === true
                && ![animal.id, animal.animal_id, animal.registry_number, animal.bolus_id, animal.tag_number]
                    .some(id => recordedDams.has(normalize(id))));
        },
        get manualMotherIsEligible() {
            return this.manualMothers.some(mother => mother.animal_id === this.addForm.mother_id);
        },
        get manualMotherIneligibility() {
            if (this.showMatingPicker || !this.addForm.mother_id || this.manualMotherIsEligible) return '';
            const dam = this.damFor(this.addForm);
            if (!dam) return 'Enter a Mother ID that exists in Carabao Inventory.';

            const status = String(dam.status ?? '').trim();
            const exitStatus = String(dam.nature_of_exit ?? '').trim();
            const currentStatus = status.toLowerCase() === 'dead' ? status : (exitStatus || status);
            if (currentStatus && currentStatus.toLowerCase() !== 'live') {
                return `Manual Entry requires a live dam. Inventory shows this animal as ${currentStatus}.`;
            }
            if (!['f', 'female'].includes(String(dam.sex ?? '').trim().toLowerCase())) {
                return 'Manual Entry requires a female animal. Check the sex in Carabao Inventory.';
            }
            if (!dam.live_breedable_female) {
                const classification = String(dam.classification ?? '').trim();
                const code = classification.toLowerCase().replace(/[\s_-]+/g, '');
                if (['heiferpregnant', 'pregnantheifer'].includes(code)) {
                    return 'Manual Entry is unavailable because inventory lists this dam as a pregnant heifer.';
                }
                if (code === 'cowlactating') {
                    return 'This dam is recorded as lactating. Manual Entry requires 25 to 45 recorded days in milk for a lactating cow.';
                }
                return classification
                    ? `Manual Entry unavailable for “${classification}”.`
                    : 'Manual Entry requires a breedable classification in Carabao Inventory.';
            }
            return 'A calf drop is already recorded for this dam within the previous 12 months. Select a Birth or event date at least 12 months after that record.';
        },
        get matchingManualMothers() {
            if (!this.motherPickerOpen) return [];
            const query = this.motherQuery.trim().toUpperCase();
            return this.manualMothers.filter(mother => [mother.animal_id, mother.registry_number, mother.animal_name]
                .some(value => String(value ?? '').toUpperCase().includes(query)));
        },
        toggleMotherPicker() {
            this.motherPickerOpen = !this.motherPickerOpen;
            if (!this.motherPickerOpen) return;
            this.motherQuery = this.addForm.mother_id ? '' : this.motherInput;
            this.$nextTick(() => this.$refs.motherSearch.focus());
        },
        chooseMotherFromSearch() {
            const matches = this.matchingManualMothers;
            const query = this.motherQuery.trim().toUpperCase();
            const exact = matches.find(mother => String(mother.animal_id).toUpperCase() === query);
            if (exact || matches.length === 1) this.selectManualMother(exact || matches[0]);
        },
        updateManualMother(value) {
            if (this.addForm.mother_id && value !== this.addForm.mother_id) {
                this.changeManualCooperative('');
            }
            this.motherInput = value;
            const id = value.trim().toUpperCase();
            if (!id) return;

            const mother = this.calfInventory.find(animal => [animal.animal_id, animal.registry_number]
                .some(candidate => String(candidate ?? '').trim().toUpperCase() === id));
            if (mother) this.selectManualMother(mother, false);
        },
        selectManualMother(mother, focusPicker = true) {
            const farmer = this.calfFarmers.find(candidate => String(candidate.id) === String(mother.farmer_id));
            if (!farmer) return;
            const cooperativeId = this.selectedCooperativeCode === 'AST' && this.isMappedAnimalIndustryDam(mother)
                ? this.addForm.cooperative_id : String(farmer.cooperative_id);
            if (cooperativeId !== this.addForm.cooperative_id) this.changeManualCooperative(cooperativeId);
            this.changeManualFarmer(String(farmer.id));
            this.addForm.mother_id = mother.animal_id;
            this.motherInput = String(mother.animal_id);
            if (focusPicker) this.$refs.motherTrigger.focus();
        },
        isWithinCalfEventInterval(record, selectedDate) {
            const eventDate = String(record.date_of_birth || record.monitored_date || '').slice(0, 10);
            if (!/^\d{4}-\d{2}-\d{2}$/.test(eventDate) || eventDate > selectedDate) return false;

            const anniversary = new Date(eventDate + 'T00:00:00Z');
            if (Number.isNaN(anniversary.getTime()) || anniversary.toISOString().slice(0, 10) !== eventDate) return false;
            const month = anniversary.getUTCMonth();
            anniversary.setUTCFullYear(anniversary.getUTCFullYear() + 1);
            if (anniversary.getUTCMonth() !== month) anniversary.setUTCDate(0);

            return selectedDate < anniversary.toISOString().slice(0, 10);
        },
        checkManualDamDate() {
            if (this.showMatingPicker || !this.addForm.mother_id) return;
            if (!this.damFor(this.addForm)?.live_breedable_female || this.manualMotherIsEligible) return;

            const message = this.manualMotherIneligibility;
            this.addForm.mother_id = '';
            this.motherInput = '';
            this.msg(message);
        },
        setEntryMode(useMating) {
            if (this.monitoringCalf) {
                this.showMatingPicker = useMating;
                if (useMating) this.loadMonitoringMatings();
                return;
            }
            if(this.showMatingPicker!==useMating){
                this.showMatingPicker=useMating;
                this.cooperativePickerOpen=false;
                this.changeManualCooperative('');
                this.addForm.sire_id='';
            }
            if(useMating)this.loadDueMatings();
        },
        changeManualCooperative(id) {
            const cooperative=this.calfCooperatives.find(item=>String(item.id)===id);
            this.addForm.cooperative_id=id;
            this.addForm.herd_code='';
            this.addForm.cooperative=cooperative?.cooperative_name || '';
            this.farmerPickerOpen=false;
            this.changeManualFarmer('');
        },
        changeManualFarmer(id) {
            const farmer=this.manualFarmers.find(item=>String(item.id)===id);
            this.addForm.farmer_id=farmer ? id : '';
            this.addForm.farmer=farmer ? this.farmerLabel(farmer) : '';
            this.addForm.mother_id='';
            this.motherInput='';
            this.motherPickerOpen=false;
            this.addForm.province=this.normalizeProvince(farmer?.province || farmer?.cooperative_record?.province);
            this.addForm.municipality=farmer?.municipality || farmer?.cooperative_record?.municipality || '';
            this.addForm.barangay=farmer?.barangay || farmer?.cooperative_record?.barangay || '';
        },
        async loadDueMatings() {
            if(this.loadingDueMatings)return;
            this.loadingDueMatings=true;
            this.dueMatingsError='';
            this.dueMatings=[];
            try {
                const response=await fetch('/admin/breeding-api/records',{headers:{Accept:'application/json'}});
                if(!response.ok)throw new Error();
                const records=await response.json();
                this.matingRecords = records;
                const byDam = new Map();
                for (const record of records) {
                    if (!record.carabao_id) continue;
                    if (!byDam.has(record.carabao_id)) byDam.set(record.carabao_id, []);
                    byDam.get(record.carabao_id).push(record);
                }
                this.dueMatings=[...byDam.values()].map(group => this.latestAi(group))
                    .filter(record => record?.calf_drop_due && this.isPregnantMating(record))
                    .sort((a,b)=>b.pregnancy_days-a.pregnancy_days);
            } catch {
                this.dueMatingsError='Could not load mating records.';
            } finally {
                this.loadingDueMatings=false;
            }
        },
        addFromMating(mating) {
            this.sireTyped = false;
            Object.assign(this.addForm,{
                mother_id:String(mating.animal_id || mating.carabao_id),
                herd_code:'',
                farmer:[mating.farmer_first_name,mating.farmer_middle_name,mating.farmer_last_name].filter(Boolean).join(' '),
                cooperative:mating.cooperative_name || '',
                province:this.normalizeProvince(mating.farmer_province),
                municipality:mating.farmer_municipality || '',
                barangay:mating.farmer_barangay || '',
                sire_id:mating.bull_id || ''
            });
            this.useDamMating(mating);
        },
        persist() { try { localStorage.setItem(this.storageKey,JSON.stringify(this.records)); return true; } catch(e) { this.msg('Unable to save browser data. Check browser storage settings.'); return false; } },
        async saveCalf() {
            if(this.savingCalf || !this.addForm.date_of_birth)return;
            if (this.invalidNumericSirePrefix) return;
            if (!this.addForm.sire_id?.trim()) {
                this.msg('Enter a Sire / Bull ID or UNKNOWN.');
                return;
            }
            if (this.monitoringCalf) {
                const row = this.addForm;
                if (this.checkingSeries) return;
                const availableSeries = this.currentSeries?.available_series;
                if (this.selectedSeries && availableSeries && !availableSeries.includes(this.selectedSeries)) {
                    this.msg('Choose an available calf series number.');
                    return;
                }
                const draftSeries = this.selectedSeries || availableSeries?.[0];
                this.finishMonitoringCalf({
                    ...this.monitoringCalf.data,
                    automatic_calf: true,
                    herd_code: row.birth_outcome === 'Live Birth' ? this.selectedCalfHerdCode || null : null,
                    registry_series: draftSeries ? Number(draftSeries) : null,
                    date_of_birth: row.date_of_birth,
                    birth_outcome: row.birth_outcome,
                    sex: row.sex || null,
                    sire_id: row.sire_id,
                    mating_method: row.mating_method || null,
                    calf_status: row.birth_outcome === 'Live Birth' ? row.animal_status : null,
                    monitored_date: row.monitored_date || null,
                    reported_year: row.reported_year || null,
                    province: row.province || null,
                    municipality: row.municipality || null,
                    barangay: row.barangay || null,
                    remarks: row.remarks || null,
                });
                return;
            }
            if(!this.showMatingPicker && !this.manualMotherIsEligible){
                this.msg(this.manualMotherIneligibility || 'Enter or select an eligible Mother ID from inventory.');
                return;
            }
            if(!this.showMatingPicker && !this.manualFarmers.some(farmer=>String(farmer.id)===this.addForm.farmer_id)){
                this.msg('Select a farmer from the cooperative.');
                return;
            }
            this.savingCalf=true;
            try {
                const automatic = { ...this.addForm, calf_id: '', breed: '', herd_code: this.selectedCalfHerdCode, registry_series: this.selectedSeries ? Number(this.selectedSeries) : null };
                if (automatic.birth_outcome !== 'Live Birth') automatic.breed = this.inferredCalfBreed(automatic);
                if(await this.saveRecords([automatic]))this.showAdd=false;
            } finally {
                this.savingCalf=false;
            }
        },
        async saveRecords(rows) {
            const valid=rows.filter(r=>
                String(r.mother_id||'').trim()
                &&String(r.date_of_birth||r.monitored_date||'').trim()
                &&(r.birth_outcome!=='Live Birth'||String(r.sex||'').trim())
            );
            valid.forEach(r=>{
                r.breed_series=this.calfSeries(r) || r.breed_series;
                const generated=this.generatedId(r);
                if(generated && !String(r.calf_id ?? '').trim())r.calf_id=generated;
                r.reported_year=this.reportedYearFromCalfId(r.calf_id,r.reported_year);
                if(r.birth_outcome==='Stillbirth')r.animal_status='Dead';
                else if(r.birth_outcome==='Abortion')r.animal_status='Not Applicable';
                else if(!r.animal_status)r.animal_status='Live';
            });
            if(!valid.length){
                this.msg('Mother ID and event date are required. Live births also require Sex.');
                return;
            }

            const existingIds=new Set(this.allRecords.map(r=>String(r.calf_id||'').trim().toLowerCase()).filter(Boolean));
            const batchIds=new Set();
            const duplicates=valid.filter(r=>{
                const id=String(r.calf_id||'').trim().toLowerCase();
                if(!id)return false;
                if(existingIds.has(id)||batchIds.has(id))return true;
                batchIds.add(id);
                return false;
            });
            if(duplicates.length&&!confirm(`${duplicates.length} duplicate Calf ID(s) found. Save these records anyway?`))return;

            const localRecords=[];
            const savedRows=new Set();
            const savedLiveRows=new Set();
            const failures=[];
            for(const row of valid){
                try{
                    const csrf=document.querySelector('meta[name="csrf-token"]')?.content;
                    const response=await fetch('/admin/calf-drop-api',{
                        method:'POST',
                        headers:{Accept:'application/json','Content-Type':'application/json',...(csrf?{'X-CSRF-TOKEN':csrf}:{})},
                        body:JSON.stringify({
                            calf_id:row.calf_id||null,
                            registry_series:row.registry_series??null,
                            herd_code:row.birth_outcome === 'Live Birth' ? row.herd_code || null : null,
                            mother_id:row.mother_id,
                            cooperative_id:row.cooperative_id||null,
                            farmer_id:row.farmer_id||null,
                            date_of_birth:row.date_of_birth||row.monitored_date,
                            sex:row.sex||null,
                            birth_outcome:row.birth_outcome,
                            monitored_date:row.monitored_date||null,
                            animal_status:row.animal_status||'Live',
                            breed:row.breed||null,
                            sire_id:row.sire_id||null,
                            province:row.province||null,
                            municipality:row.municipality||null,
                            barangay:row.barangay||null,
                            remarks:row.remarks||null
                        })
                    });
                    if(!response.ok){
                        const payload=await response.json().catch(()=>({}));
                        throw new Error(payload.message||`Request failed (${response.status}).`);
                    }
                    savedRows.add(row._key);
                    savedLiveRows.add(row._key);
                }catch(error){
                    failures.push(`${row.calf_id||row.mother_id}: ${error.message}`);
                    console.error('Could not save calf drop:',error);
                }
            }

            if(savedRows.size){
                await this.loadInventoryCalves();
                this.page=1;
            }
            if(failures.length){
                this.msg(savedRows.size ? `Saved ${savedRows.size} record(s). ${failures.join(' ')}` : failures.join(' '));
                return;
            }
            this.msg(savedRows.size === 1 ? 'Calf record saved.' : `${savedRows.size} calf records saved.`);
            return true;
        },
        view(record) { this.selected={...record}; this.showView=true; },
        startEdit(record) { const sex=String(record.sex??'').trim().toLowerCase(); this.editForm={...record,date_of_birth:String(record.date_of_birth??'').slice(0,10),monitored_date:String(record.monitored_date??'').slice(0,10),sex:['f','female'].includes(sex)?'Female':(['m','male'].includes(sex)?'Male':''),animal_status:['Live','Out','Dead','Not Applicable'].includes(record.animal_status)?record.animal_status:'Live',birth_outcome:['Live Birth','Stillbirth','Abortion'].includes(record.birth_outcome)?record.birth_outcome:'Live Birth'};this.showEdit=true; },
        closeEdit() { if(this.savingEdit)return;this.showEdit=false;this.editForm={}; },
        async saveEdit() {
            if(!this.editForm?.id||this.savingEdit)return;
            this.savingEdit=true;
            const form={...this.editForm},payload={date_of_birth:form.date_of_birth,sex:form.sex,breed:form.breed||null,sire_id:form.sire_id||null,animal_status:form.animal_status||'Live',monitored_date:form.monitored_date||null,birth_outcome:form.birth_outcome||'Live Birth',remarks:form.remarks||null};
            try {
                if(form.source==='Inventory'||form.inventory_id){
                    const response=await fetch(`/admin/calf-drop-api/inventory/${form.inventory_id}`,{method:'PATCH',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''},body:JSON.stringify(payload)});
                    if(!response.ok){const result=await response.json().catch(()=>({}));throw new Error(result.message||`Save failed (${response.status}).`)}
                    await this.loadInventoryCalves();
                }else if(form.source==='Calf Drop'||form.database_id){
                    if (form.dam_unlinked && form.resolve_dam?.trim()) payload.resolve_dam = form.resolve_dam.trim();
                    const response=await fetch(`/admin/calf-drop-api/${form.database_id}`,{method:'PUT',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''},body:JSON.stringify(payload)});
                    if(!response.ok){const result=await response.json().catch(()=>({}));throw new Error(result.message||`Save failed (${response.status}).`)}
                    await this.loadInventoryCalves();
                }else{
                    const previous=this.records;
                    this.records=this.records.map(record=>record.id===form.id?{...record,...payload,calf_id:record.calf_id}:record);
                    if(!this.persist()){this.records=previous;throw new Error('Unable to save this record in browser storage.')}
                }
                this.showEdit=false;this.editForm={};this.msg('Calf record updated. Calf ID was preserved.');
            }catch(error){this.msg(error.message||'Could not update calf record.');}
            finally{this.savingEdit=false;}
        },
        remove(record) {
            if(this.deletingCalf)return;
            this.deleteTarget=record;
            this.deleteCalfError='';
            this.finalDeleteConfirmation=false;
            this.$refs.deleteCalfDialog.showModal();
        },

        reviewCalfDelete() {
            this.finalDeleteConfirmation=true;
            this.$nextTick(()=>this.$refs.cancelCalfDelete.focus());
        },

        async confirmCalfDelete() {
            if(this.deletingCalf || !this.deleteTarget || !this.finalDeleteConfirmation)return;
            const record=this.deleteTarget;
            this.deletingCalf=true;
            this.deleteCalfError='';
            try {
                const id=record.inventory_id || record.database_id;
                if(id){
                    const path=record.inventory_id ? 'inventory/' : '';
                    const response=await fetch(`/admin/calf-drop-api/${path}${id}`,{
                        method:'DELETE',
                        headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''}
                    });
                    if(!response.ok){
                        const payload=await response.json().catch(()=>({}));
                        throw new Error(payload.message||`Delete failed (${response.status}).`);
                    }
                    this.inventoryCalves=this.inventoryCalves.filter(row=>row.id!==record.id);
                }else{
                    const previous=this.records;
                    this.records=this.records.filter(row=>row.id!==record.id);
                    if(!this.persist()){
                        this.records=previous;
                        throw new Error('Unable to delete the record from browser storage.');
                    }
                }
                this.page=Math.min(this.page,this.totalPages());
                this.$refs.deleteCalfDialog.close();
                this.deleteTarget=null;
                this.msg('Calf record deleted.');
            }catch(error){
                this.deleteCalfError=error.message;
            }finally{
                this.deletingCalf=false;
            }
        },
        resetFilters() { this.search='';this.filterCooperative='';this.filterProvince='';this.filterBreed='';this.filterSex='';this.filterReportedYear='';this.page=1; },
        msg(message) { this.notice=message;clearTimeout(this.noticeTimer);this.noticeTimer=setTimeout(()=>{this.notice='';},3500); },
        openCalfHistory() {
            this.$refs.calfHistoryDialog.showModal();
            this.loadCalfHistory();
        },
        async loadCalfHistory() {
            if (this.calfHistory || this.calfHistoryLoading) return;
            this.calfHistoryLoading = true;
            this.calfHistoryError = '';
            try {
                const response = await fetch(@js(route('ui.calves.history', [], false)), { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('History request failed');
                this.calfHistory = await response.json();
            } catch {
                this.calfHistoryError = 'Calf Drop History could not be loaded.';
            } finally {
                this.calfHistoryLoading = false;
            }
        },
        exportCSV() {
            const escapeCSV = value => '"' + String(value ?? '').replace(/"/g, '""') + '"';
            const lines = [
                this.fields.map(escapeCSV).join(','),
                ...this.filteredRecords.map(record => this.fields.map(field => escapeCSV(record[field])).join(',')),
            ];
            const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'calfdrop_records.csv';
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
        },
        importCSV(event) {
            const file=event.target.files&&event.target.files[0];
            if(!file)return;
            const reader=new FileReader();
            reader.onload=async()=>{
                try{
                    const lines=String(reader.result||'').replace(/^\uFEFF/,'').split(/\r?\n/).filter(line=>line.trim());
                    if(lines.length<2){this.msg('CSV has no data rows.');return;}
                    const parseLine=line=>{
                        const values=[];let value='',quoted=false;
                        for(let i=0;i<line.length;i++){
                            const ch=line[i];
                            if(ch==='"'&&quoted&&line[i+1]==='"'){value+='"';i++;}
                            else if(ch==='"')quoted=!quoted;
                            else if(ch===','&&!quoted){values.push(value);value='';}
                            else value+=ch;
                        }
                        values.push(value);
                        return values;
                    };
                    const normalizeHeader=header=>{
                        const key=header.trim().toLowerCase().replace(/[\s/-]+/g,'_');
                        const aliases={calf:'calf_id',calfid:'calf_id',mother:'mother_id',motherid:'mother_id',farmer_name:'farmer',birth_date:'date_of_birth',sex_of_calf:'sex',bull_id:'sire_id',sire_bull_id:'sire_id',year:'reported_year',outcome:'birth_outcome',status:'animal_status',series:'breed_series',event_date:'date_of_birth'};
                        return aliases[key]||key;
                    };
                    const headers=parseLine(lines.shift()).map(normalizeHeader);
                    const imported=[];
                    lines.forEach(line=>{
                        const values=parseLine(line);
                        const row=this.emptyRow();
                        headers.forEach((header,index)=>{if(this.fields.includes(header))row[header]=values[index]??'';});
                        const generated=this.generatedId(row);
                        if(generated && !String(row.calf_id ?? '').trim())row.calf_id=generated;
                        if(String(row.mother_id||'').trim()&&String(row.date_of_birth||row.monitored_date||'').trim()&&(row.birth_outcome!=='Live Birth'||String(row.sex||'').trim())){
                            if(row.birth_outcome==='Stillbirth')row.animal_status='Dead';
                            else if(row.birth_outcome==='Abortion')row.animal_status='Not Applicable';
                            imported.push(row);
                        }
                    });
                    if(!imported.length){this.msg('No valid rows found. Check Calf ID, Mother ID, and Sex columns.');return;}
                    await this.saveRecords(imported);
                }catch(e){
                    console.error('Could not import calf records:',e);
                    this.msg('Could not read the CSV. Check its headers and format.');
                }finally{
                    event.target.value='';
                }
            };
            reader.onerror=()=>{this.msg('Could not read this CSV file.');event.target.value='';};
            reader.readAsText(file);
        }
    };
}
</script>
@endpush
