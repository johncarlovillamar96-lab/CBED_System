@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<script>
function matingSearchPicker() {
    return {
        open: false,
        query: '',
        matches(items) {
            const normalize = value => String(value ?? '').normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();
            const query = normalize(this.query).trim();
            return items.filter(item => normalize(item.name).includes(query));
        },
        togglePicker() {
            this.open = !this.open;
            if (this.open) {
                this.query = '';
                this.$nextTick(() => this.$refs.search.focus());
            }
        },
        closePicker() {
            this.open = false;
            this.$refs.trigger.focus();
        },
        choose(id, selected, onSelect) {
            if (String(id) !== String(selected)) onSelect(String(id));
            this.closePicker();
        },
    };
}

function breedingPage(monitoringMode = false){return {
    api:{
        csrfToken:@js(csrf_token()),
        records:@js(route('admin.breeding-api.records')),
        cooperatives:@js(route('admin.breeding-api.cooperatives')),
        farmers:@js(route('admin.breeding-api.farmers')),
        carabaos:@js(route('admin.breeding-api.carabaos')),
        bulls:@js(route('admin.breeding-api.semen-bulls')),
        store:@js(route('admin.breeding-api.store')),
        bulk:@js(route('admin.breeding-api.bulk')),
        update:@js(url('/admin/breeding-api/records')),
        delete:@js(url('/admin/breeding-api/records'))
    },

    records:[],cooperatives:[],farmers:[],animals:[],semenBulls:[],
    totalActiveBulls:0,lookupReady:false,loading:false,saving:false,
    search:'',filterMatingType:'',filterCooperative:'',filterPD:'',filterDate:'',filterYear:'',unreportedCalvesOnly:false,overdueCalvesOnly:false,includeReportedPregnancies:false,
    selectedCooperative:'',showBulk:false,showView:false,
    bulkRows:[],selected:null,editingId:null,notice:'',noticeType:'success',
    pdDraft:null,pdSaving:false,pdError:'',
    deleteRecord:null,deleting:false,deleteError:'',
    noticeTimer:null,rowKey:0,page:1,perPage: 15,
    monitoringMatingContext: null,

    fields:[
        'cooperative','farmer','animal_id','breeding_date','mating_type',
        'bull_id','bull_name','straws','heat_status','mating_technician',
        'pd_date','pd_result','pregnancy_months','pd_technician',
        'expected_calving','actual_calving','remarks'
    ],

    async init(){
        if (monitoringMode) return;
        try{
            await Promise.all([this.loadCooperatives(),this.loadSemenBulls()]);
            await this.loadRecords();
            const notificationParams=new URLSearchParams(window.location.search);
            if(notificationParams.get('overdue_calves')==='1'){
                this.showOverdueCalves(notificationParams.get('cooperative_id')||'');
                this.search=notificationParams.get('animal_id')||'';
            }
            this.$dispatch('cbed-data-status');
        }catch(e){
            this.$dispatch('cbed-data-status', {
                title: 'Mating records unavailable',
                description: e.message || 'Reload the page to try again.',
                type: 'error'
            });
        }
    },

    async request(url,options={}){
        const token=document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            ||this.api.csrfToken;

        const response=await fetch(url,{
            ...options,
            headers:{
                Accept:'application/json',
                'X-CSRF-TOKEN':token,
                'X-Requested-With':'XMLHttpRequest',
                ...(options.body?{'Content-Type':'application/json'}:{}),
                ...(options.headers||{})
            },
            credentials:'same-origin'
        });

        let result;

        try{
            result=await response.json();
        }catch(e){
            throw new Error('Unexpected server response. Check Laravel routes and logs.');
        }

        if(!response.ok){
            if(response.status===419){
                throw new Error('Your Laravel session or CSRF token has expired. Refresh the page, log in again if needed, and retry.');
            }

            let message=result.message||'The request failed.';

            if(result.errors){
                message=Object.values(result.errors).flat().join(' ');
            }

            throw new Error(message);
        }

        return result;
    },

    async loadCooperatives(){
        const result=await this.request(this.api.cooperatives);
        const data=Array.isArray(result)?result:(Array.isArray(result.data)?result.data:(result.data?.data||[]));

        this.cooperatives=data.map(c=>({
            id:String(c.id),
            name:c.cooperative_name||c.name||'',
            code:c.cooperative_code||''
        }));
    },

    async loadSemenBulls(){
        const result=await this.request(this.api.bulls);
        const data=Array.isArray(result)?result:Array.isArray(result.data)?result.data:
            (Array.isArray(result.data?.data)?result.data.data:[]);

        this.semenBulls=data.map(b=>({
            id:String(b.id),
            bull_id:b.bull_id||'',
            bull_name:b.bull_name||'',
            breed:b.breed||'',
            status:b.status||'Active'
        }));

        this.totalActiveBulls=Number(result.total_count??result.data?.total??this.semenBulls.length);
    },

    async loadRecords(){
        this.loading=true;
        try{
            const result=await this.request(this.api.records);
            const payload=Array.isArray(result)?result:result?.data;
            const data=Array.isArray(payload)?payload:payload?.data;
            if (!Array.isArray(data)) throw new Error('The server did not return a list of mating records.');

            this.records=data.map(r=>this.normalizeRecord(r));
            const localCalves=this.localCalfReports();
            this.records.forEach(r=>{
                const start=r.breeding_date||r.pd_date;
                const next=this.records.filter(other=>other.pd_result==='Positive'&&other.carabao_id===r.carabao_id)
                    .map(other=>other.breeding_date||other.pd_date).filter(date=>date>start).sort()[0];
                if(r.pd_result==='Positive'&&this.pregnancyDays(r)>300&&localCalves.some(calf=>
                    String(calf.mother_id).trim().toLowerCase()===String(r.animal_id??'').trim().toLowerCase()
                    &&calf.date_of_birth>start&&(!next||calf.date_of_birth<next))){
                    r.calf_drop_reported=true;
                    r.pregnancy_followup_complete=true;
                }
            });
            this.page=Math.min(this.page,this.totalPages());
        }finally{
            this.loading=false;
        }
    },

    normalizeRecord(r){
        const f=r.farmer||{},a=r.carabao||{},c=r.cooperative||{},b=r.semen_bull||r.semenBull||{};
        const fullName=[f.first_name||r.farmer_first_name,f.middle_name||r.farmer_middle_name,f.last_name||r.farmer_last_name].filter(Boolean).join(' ');
        let source={};
        try{source=typeof r.remarks==='string'?JSON.parse(r.remarks):{};}catch(e){}

        return {
            id:r.id,
            cooperative_id:String(r.cooperative_id??''),
            farmer_id:String(r.farmer_id??''),
            carabao_id:String(r.carabao_id??''),
            cooperative:c.cooperative_name||c.name||r.cooperative_name||'',
            farmer:fullName||r.farmer_name||f.farmer_name||f.name||'',
            animal_id:a.animal_id||a.bolus||r.animal_id||r.registry_number||'',
            registry_number:r.registry_number||a.registry_number||'',
            breeding_date:this.dateOnly(r.breeding_date||r.mating_date),
            mating_type:this.normalizeMatingType(r.mating_type),
            semen_bull_id:r.semen_bull_id?String(r.semen_bull_id):'',
            bull_id:r.bull_id||b.bull_id||'',
            bull_name:r.bull_name||r.matched_bull_name||b.bull_name||'',
            straws:r.straws_used??'',
            heat_status:this.normalizeMatingType(r.mating_type)==='AI'?(r.estrus_type||source['type of estrus_7']||''):'',
            mating_technician:r.mating_technician||r.technician||source['technician_8']||'',
            pd_date:this.dateOnly(r.pd_date||r.pregnancy_check_date),
            pd_result:this.normalizePDResult(r.pd_result??r.pregnancy_result),
            pregnancy_months:r.pregnancy_months??'',
            pd_technician:r.pd_technician||source['confirmed by_18']||'',
            expected_calving:this.dateOnly(r.expected_calving_date),
            actual_calving:this.dateOnly(r.actual_calving_date),
            calf_drop_reported:r.calf_drop_reported===true||r.calf_drop_reported===1,
            pregnancy_followup_complete:r.pregnancy_followup_complete===true||r.pregnancy_followup_complete===1,
            calf_drop_overdue:r.calf_drop_overdue===true||r.calf_drop_overdue===1,
            remarks:source['remarks_19']||r.remarks||''
        };
    },

    dateOnly(v){return v?String(v).slice(0,10):'';},

    displayDate(v){
        if(!v||v==='—')return '—';
        const parts=String(v).slice(0,10).split('-').map(Number);
        if(parts.length!==3||parts.some(Number.isNaN))return String(v);
        const date=new Date(Date.UTC(parts[0],parts[1]-1,parts[2]));
        return new Intl.DateTimeFormat('en-US',{month:'long',day:'numeric',year:'numeric',timeZone:'UTC'}).format(date);
    },

    normalizeMatingType(v){
        const value=String(v||'').trim().toUpperCase();
        return ['N','NM','NATURAL','NATURAL MATING','NATURAL SERVICE','NS'].includes(value)?'NM':'AI';
    },

    normalizePDResult(v){
        const value=String(v??'').trim().toLowerCase();
        if(['+','p','positive','pregnant','yes'].includes(value))return 'Positive';
        if(['-','n','negative','not pregnant','non-pregnant','no'].includes(value))return 'Negative';
        return value===''||['pending','null','n/a','na','?'].includes(value)?'Pending':String(v).trim();
    },

    async cooperativeChanged(){
        const cooperativeId = this.selectedCooperative;
        this.farmers=[];
        this.animals=[];
        this.lookupReady=false;

        for(const r of this.bulkRows){
            r.farmer_id='';
            r.farmer='';
            r.animal_id='';
            r.carabao_id='';
            r._animals=[];
        }

        if(!cooperativeId)return;

        try{
            const u=new URL(this.api.farmers,window.location.origin);
            u.searchParams.set('cooperative_id',cooperativeId);

            const res=await this.request(u.toString());
            if (this.selectedCooperative !== cooperativeId) return;
            const data=Array.isArray(res)?res:(Array.isArray(res.data)?res.data:(res.data?.data||[]));

            this.farmers=data.filter(f=>String(f.cooperative_id??'')===this.selectedCooperative).map(f=>({
                id:String(f.id),
                name:[f.first_name,f.middle_name,f.last_name].filter(Boolean).join(' ')
                    ||f.farmer_name||f.name||'',
                cooperative_id:String(f.cooperative_id??'')
            }));

            this.lookupReady=true;
        }catch(e){
            if (this.selectedCooperative === cooperativeId) this.msg(e.message,'error');
        }
    },

    async farmerChanged(row){
        const cooperativeId = this.selectedCooperative;
        const farmerId = String(row.farmer_id);
        const f=this.farmers.find(x=>x.id===String(row.farmer_id));
        row.farmer=f?f.name:'';
        row.carabao_id='';
        row.animal_id='';
        row._animals=[];

        if(!farmerId||!cooperativeId)return;

        try{
            const u=new URL(this.api.carabaos,window.location.origin);
            u.searchParams.set('cooperative_id',cooperativeId);
            u.searchParams.set('farmer_id',farmerId);

            const res=await this.request(u.toString());
            if (this.selectedCooperative !== cooperativeId || String(row.farmer_id) !== farmerId) return;
            const data=Array.isArray(res)?res:(Array.isArray(res.data)?res.data:(res.data?.data||[]));

            row._animals=data.filter(a=>{
                const sex=String(a.sex||a.gender||'').toLowerCase();
                return String(a.farmer_id??'')===String(row.farmer_id)
                    &&String(a.cooperative_id??'')===this.selectedCooperative
                    &&(!sex||sex==='f'||sex==='female');
            }).map(a=>({
                id:String(a.id),
                animal_id:a.animal_id||a.bolus||'',
                registry_number:a.registry_number||'',
                label:a.animal_id||a.bolus||('Animal '+a.id)
            }));
        }catch(e){
            if (this.selectedCooperative === cooperativeId && String(row.farmer_id) === farmerId) this.msg(e.message,'error');
        }
    },

    animalsForFarmer(r){return r._animals||[];},

    animalChanged(r){
        const a=this.animalsForFarmer(r).find(x=>String(x.id)===String(r.carabao_id));
        r.animal_id=a?a.animal_id:'';
    },

    pdRecordsFor(row) {
        if (!row.carabao_id) return [];
        const selected = this.animalsForFarmer(row).find(animal => animal.id === String(row.carabao_id));
        const key = value => String(value ?? '').trim().toLowerCase();
        const identifiers = [row.animal_id, selected?.registry_number].map(key).filter(Boolean);
        return this.records.filter(record =>
            record.carabao_id === String(row.carabao_id) ||
            (!record.carabao_id && record.cooperative_id === this.selectedCooperative
                && identifiers.includes(key(record.registry_number || record.animal_id)))
        ).sort((a, b) => (b.breeding_date || b.pd_date).localeCompare(a.breeding_date || a.pd_date) || Number(b.id) - Number(a.id));
    },

    pendingPdRecord(row) {
        return this.pdRecordsFor(row).find(record => record.pd_result === 'Pending');
    },

    activePositivePdRecord(row) {
        const latest = this.pdRecordsFor(row)[0];
        return latest?.pd_result === 'Positive' && !latest.actual_calving && !latest.calf_drop_reported
            && !latest.pregnancy_followup_complete ? latest : null;
    },

    openPendingPd(row) {
        const record = this.pendingPdRecord(row);
        if (!record) return;
        this.pdDraft = {...record, animal_id: row.animal_id || record.animal_id, pd_result: ''};
        this.pdError = '';
        this.$refs.pendingPdDialog.showModal();
    },

    async savePendingPd() {
        if (this.pdSaving || !this.pdDraft) return;
        const draft = this.pdDraft;
        this.pdSaving = true;
        this.pdError = '';
        try {
            await this.request(`${this.api.update}/${draft.id}/pd`, {
                method: 'PATCH',
                body: JSON.stringify({
                    pd_result: draft.pd_result,
                    pd_date: draft.pd_date,
                    pd_technician: draft.pd_technician,
                    pregnancy_months: draft.pd_result === 'Positive' ? Number(draft.pregnancy_months) : null,
                    expected_calving_date: draft.pd_result === 'Positive' ? (draft.expected_calving || null) : null,
                }),
            });
            const updated = this.records.find(record => record.id === draft.id);
            if (updated) {
                for (const field of ['pd_result', 'pd_date', 'pd_technician', 'pregnancy_months', 'expected_calving']) updated[field] = draft[field];
            }
            this.$refs.pendingPdDialog.close();
            this.msg(draft.pd_result === 'Negative' ? 'PD updated to Negative. Continue with the new mating record.' : 'PD updated to Positive.');
            await this.loadRecords().catch(() => {});
        } catch (error) {
            this.pdError = error.message;
        } finally {
            this.pdSaving = false;
        }
    },

    emptyRow(){
        return {
            _key:++this.rowKey,
            farmer_id:'',farmer:'',carabao_id:'',animal_id:'',
            breeding_date:new Date().toISOString().slice(0,10),
            mating_type:'AI',semen_bull_id:'',bull_search:'',
            bull_id:'',bull_name:'',straws:'1',heat_status:'',
            mating_technician:'',pd_date:'',pd_result:'Pending',
            pregnancy_months:'',pd_technician:'',expected_calving:'',
            actual_calving:'',remarks:'',_animals:[]
        };
    },

    async openMonitoringMating(context) {
        this.monitoringMatingContext = context;
        this.notice = '';
        this.editingId = 'monitoring';
        this.selectedCooperative = String(context.cooperativeId);
        const data = context.data;
        this.bulkRows = [{
            ...this.emptyRow(),
            farmer_id: String(context.farmerId ?? ''),
            farmer: context.farmer,
            carabao_id: String(context.carabaoId),
            animal_id: context.animalId,
            breeding_date: data.breeding_date || context.reportDate,
            mating_type: this.normalizeMatingType(data.method),
            semen_bull_id: String(data.semen_bull_id ?? ''),
            bull_id: data.bull_id ?? '',
            bull_name: data.bull_name ?? '',
            straws: data.semen_used ?? (data.method === 'NM' ? '' : '1'),
            heat_status: data.estrus_type ?? '',
            mating_technician: data.technician ?? '',
            pd_date: data.pd_date ?? '',
            pd_result: this.normalizePDResult(data.pregnancy_status),
            pregnancy_months: data.pregnancy_months ?? '',
            pd_technician: data.pd_technician ?? '',
            expected_calving: data.expected_calving ?? '',
            actual_calving: data.actual_calving ?? '',
            record_date: data.record_date ?? '',
            remarks: data.remarks ?? '',
        }];
        const rowKey = this.bulkRows[0]._key;
        this.$root.showModal();
        if (!this.semenBulls.length) {
            try {
                await this.loadSemenBulls();
                if (!this.monitoringMatingContext || this.bulkRows[0]?._key !== rowKey) return;
                const row = this.bulkRows[0];
                const bull = this.semenBulls.find(item => item.id === row.semen_bull_id);
                if (bull) {
                    row.bull_id ||= bull.bull_id;
                    row.bull_name ||= bull.bull_name;
                }
            } catch {
                this.msg('Semen Bulls unavailable. You can enter a Bull ID manually.', 'error');
            }
        }
    },

    saveMonitoringMating() {
        if (!this.validateRows()) return;
        const row = this.bulkRows[0];
        const data = this.toPayload(row);
        this.finishMonitoringMating({
            ...this.monitoringMatingContext.data,
            mating_form: true,
            method: data.mating_type,
            breeding_date: data.breeding_date,
            semen_bull_id: data.semen_bull_id,
            bull_id: data.bull_id,
            bull_name: data.bull_name,
            semen_used: data.straws_used,
            estrus_type: data.estrus_type,
            technician: data.mating_technician,
            pd_date: data.pd_date,
            pregnancy_status: ({Positive: 'Pregnant', Negative: 'Not Pregnant', Inconclusive: 'Inconclusive'})[data.pd_result] ?? null,
            pregnancy_months: data.pregnancy_months,
            pd_technician: data.pd_technician,
            expected_calving: data.expected_calving_date,
            actual_calving: data.actual_calving_date,
            record_date: row.record_date || null,
            remarks: data.remarks,
        });
    },

    finishMonitoringMating(data) {
        const index = this.monitoringMatingContext.index;
        this.$root.close();
        this.$dispatch('monitoring-breeding-draft', {index, data});
    },

    openBulk(){
        this.editingId=null;
        this.selectedCooperative='';
        this.farmers=[];
        this.bulkRows=[this.emptyRow()];
        this.showBulk=true;
        this.lookupReady=false;
        this.loadRecords().catch(error => this.msg(error.message, 'error'));
    },

    addRow(){this.bulkRows.push(this.emptyRow());},
    removeRow(i){this.bulkRows.splice(i,1);},

    matchBull(r){
        const q=String(r.bull_search||'').trim().toLowerCase();
        const b=this.semenBulls.find(x=>
            x.bull_id.toLowerCase()===q||x.bull_name.toLowerCase()===q
        );

        if(b){
            r.semen_bull_id=b.id;
            r.bull_id=b.bull_id;
            r.bull_name=b.bull_name;
        }else{
            r.semen_bull_id='';
            r.bull_id='';
            r.bull_name='';
        }
    },

    matchBullField(r,f){
        const q=String(r[f]||'').trim().toLowerCase();
        const b=this.semenBulls.find(x=>
            f==='bull_id'?x.bull_id.toLowerCase()===q:x.bull_name.toLowerCase()===q
        );

        if(b){
            r.semen_bull_id=b.id;
            r.bull_id=b.bull_id;
            r.bull_name=b.bull_name;
            r.bull_search=b.bull_id;
        }else{
            r.semen_bull_id='';
        }
    },

    typeChanged(r){
        if(r.mating_type!=='AI'){r.straws='';r.heat_status='';}
        else if(!r.straws)r.straws='1';
    },

    dateChanged(r){
        if(r.pd_result==='Positive'){
            r.expected_calving=this.calculateExpectedCalving(r.breeding_date);
        }
    },

    pdDateChanged(r){
        if(r.pd_result==='Positive'&&!r.expected_calving){
            r.expected_calving=this.calculateExpectedCalving(r.breeding_date);
        }
    },

    pdChanged(r) {
        if (r.pd_result === 'Positive') {
            if (!r.expected_calving) {
                r.expected_calving =
                    this.calculateExpectedCalving(r.breeding_date);
            }
        } else {
            r.pregnancy_months = '';
            r.expected_calving = '';

            // Preserve PD date and technician during correction.
            // Clear them manually only if they are incorrect.
        }
    },

    addDaysToDate(v,days){
        if(!v)return '';
        const d=new Date(v+'T12:00:00');
        if(isNaN(d.getTime()))return '';
        d.setDate(d.getDate()+days);
        return this.formatDate(d);
    },

    expectedPDDate(v,days){
        return this.displayDate(this.addDaysToDate(v,days));
    },

    secondCycleRange(v){
        if(!v)return '—';
        const start=this.addDaysToDate(v,18);
        const end=this.addDaysToDate(v,21);
        return start&&end?this.displayDate(start)+' – '+this.displayDate(end):'—';
    },

    calculateExpectedCalving(v){
        if(!v||Number(String(v).slice(0,4))<1900)return '';
        const d=new Date(v+'T12:00:00');
        if(isNaN(d.getTime()))return '';
        d.setDate(d.getDate()+310);
        return this.formatDate(d);
    },

    formatDate(d){
        return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
    },

    pregnancyDays(r){
        if(!r||r.pd_result!=='Positive'||!r.breeding_date)return 0;
        const d=new Date(r.breeding_date+'T00:00:00');
        const n=new Date();
        return Math.max(0,Math.floor((
            new Date(n.getFullYear(),n.getMonth(),n.getDate())-d
        )/86400000));
    },

    toPayload(r){
        return {
            cooperative_id:Number(this.selectedCooperative),
            farmer_id:Number(r.farmer_id),
            carabao_id:Number(r.carabao_id),
            breeding_date:r.breeding_date,
            mating_type:r.mating_type,
            semen_bull_id:r.semen_bull_id?Number(r.semen_bull_id):null,
            bull_id:r.bull_id||null,
            bull_name:r.bull_name||null,
            straws_used:r.mating_type==='AI'&&r.straws!==''?Number(r.straws):null,
            estrus_type:r.mating_type==='AI'?(r.heat_status||null):null,
            mating_technician:r.mating_technician||null,
            pd_date:r.pd_date||null,
            pd_result:r.pd_result||'Pending',
            pregnancy_months:r.pd_result==='Positive'&&r.pregnancy_months!==''&&r.pregnancy_months!=null
                ?Number(r.pregnancy_months):null,
            pd_technician:r.pd_result!=='Pending'?(r.pd_technician||null):null,
            expected_calving_date:r.pd_result==='Positive'?(r.expected_calving||null):null,
            actual_calving_date:r.actual_calving||null,
            remarks:r.remarks||null
        };
    },

    validateRows() {
        if (!this.selectedCooperative) {
            this.msg('Select a cooperative first.', 'error');
            return false;
        }

        if (!this.bulkRows.length) {
            this.msg('Add at least one mating record.', 'error');
            return false;
        }

        for (let i = 0; i < this.bulkRows.length; i++) {
            const r = this.bulkRows[i];
            const row = i + 1;

            if (
                !r.farmer_id ||
                !r.carabao_id ||
                !r.breeding_date ||
                !r.mating_type ||
                (r.mating_type==='AI'&&!r.heat_status)
            ) {
                this.msg(
                    'Row ' + row +
                    ': Farmer, female animal, mating date, mating type, and estrus/heat for AI are required.',
                    'error'
                );
                return false;
            }

            if (!['Pending', 'Positive', 'Negative'].includes(r.pd_result) && !(monitoringMode && r.pd_result === 'Inconclusive')) {
                this.msg('Row ' + row + ': Select a valid PD result.', 'error');
                return false;
            }

            // Require PD details for new records.
            // Existing records can be corrected even if old PD details are incomplete.
            if ((!this.editingId || monitoringMode) &&
                ['Positive', 'Negative'].includes(r.pd_result)) {

                if (!r.pd_date || !String(r.pd_technician || '').trim()) {
                    this.msg(
                        'Row ' + row +
                        ': Enter the PD date and confirming technician.',
                        'error'
                    );
                    return false;
                }
            }

            // Positive diagnosis always requires pregnancy months.
            if (r.pd_result === 'Positive') {
                const months = Number(r.pregnancy_months);

                if (
                    r.pregnancy_months === '' ||
                    r.pregnancy_months === null ||
                    r.pregnancy_months === undefined ||
                    !Number.isInteger(months) ||
                    months < 1 ||
                    months > 11
                ) {
                    this.msg(
                        'Row ' + row +
                        ': Enter pregnancy months from 1 to 11 for a positive diagnosis.',
                        'error'
                    );
                    return false;
                }
            }
        }

        return true;
    },

    async updateRecord(){
        if(this.saving||!this.validateRows())return;
        this.saving=true;

        try{
            await this.request(this.api.update+'/'+this.editingId,{
                method:'PUT',
                body:JSON.stringify(this.toPayload(this.bulkRows[0]))
            });
            this.msg('Mating record updated.');
            this.closeModal();
            await this.loadRecords();
        }catch(e){
            this.msg(e.message,'error');
        }finally{
            this.saving=false;
        }
    },

    async saveBulk(){
        if(this.saving||!this.validateRows())return;
        for (const [index, row] of this.bulkRows.entries()) {
            if (this.pendingPdRecord(row)) {
                this.msg(`Row ${index + 1}: Update the Pending PD beside Remarks before saving.`, 'error');
                return;
            }
            if (this.activePositivePdRecord(row)) {
                this.msg(`Row ${index + 1}: This animal has an active Positive PD result.`, 'error');
                return;
            }
        }
        this.saving=true;

        try{
            const records=this.bulkRows.map(r=>{
                const x=this.toPayload(r);
                delete x.cooperative_id;
                return x;
            });
            await this.request(this.api.bulk,{
                method:'POST',
                body:JSON.stringify({cooperative_id:Number(this.selectedCooperative),records,check_pending_pd:true})
            });
            this.msg(records.length===1?'Mating record saved.':records.length+' mating records saved.');
            this.closeModal();
            await this.loadRecords();
        }catch(e){
            this.msg(e.message,'error');
        }finally{
            this.saving=false;
        }
    },

    async edit(r){
        this.editingId=r.id;
        this.selectedCooperative=String(r.cooperative_id||'');
        this.farmers=[];
        this.bulkRows=[];
        this.showBulk=true;

        try{
            await this.cooperativeChanged();

            const row={
                ...this.emptyRow(),
                ...r,
                _key:++this.rowKey,
                farmer_id:String(r.farmer_id??''),
                carabao_id:String(r.carabao_id??''),
                semen_bull_id:r.semen_bull_id?String(r.semen_bull_id):'',
                pregnancy_months:r.pregnancy_months??'',
                bull_search:r.bull_id||''
            };

            const farmerName=row.farmer;
            await this.farmerChanged(row);
            row.farmer=farmerName||row.farmer;
            row.cooperative=r.cooperative||this.cooperatives.find(c=>String(c.id)===this.selectedCooperative)?.name||'';
            row.carabao_id=String(r.carabao_id??'');
            row.animal_id=r.animal_id||'';
            if(row.pd_result==='Positive'&&row.breeding_date&&row.expected_calving&&row.expected_calving<row.breeding_date){
                row.expected_calving=this.calculateExpectedCalving(row.breeding_date);
            }
            this.bulkRows=[row];
        }catch(e){
            this.msg(e.message,'error');
        }
    },

    view(r){
        this.selected={...r};
        this.showView=true;
    },

    closeModal(){
        this.showBulk=false;
        this.editingId=null;
        this.bulkRows=[];
    },

    confirmDelete(record){
        this.deleteRecord=record;
        this.deleteError='';
        this.$refs.deleteDialog.showModal();
    },

    async remove(){
        if(this.deleting||!this.deleteRecord)return;
        this.deleting=true;
        this.deleteError='';
        try{
            await this.request(this.api.delete+'/'+this.deleteRecord.id,{method:'DELETE'});
        }catch(e){
            this.deleteError=e.message;
            return;
        }finally{
            this.deleting=false;
        }

        this.$refs.deleteDialog.close();
        this.deleteRecord=null;
        try{
            await this.loadRecords();
            this.msg('Mating record deleted.');
        }catch(e){
            this.msg('Mating record deleted. Refresh the page to update the list.','error');
        }
    },

    localCalfReports(){
        try{
            const saved=JSON.parse(localStorage.getItem('cbed_calfdrop_records')||'[]');
            if(!Array.isArray(saved))return [];
            return saved.filter(r=>{
                const calf=String(r?.calf_id??'').trim(),mother=String(r?.mother_id??'').trim();
                return mother&&!['-','--','n/a','na','none','unknown','0'].includes(mother.toLowerCase())
                    &&!/^CALF-2026-00[1-5]$|^CALF-2025-005$|^6-(?:SB|AB)-26-CB-2024-00[34]$/i.test(calf)
                    &&!/^CB-2024-00[1-5]$/i.test(mother)
                    &&!/^Sample Cooperative(?:\s|$)/i.test(String(r?.cooperative??'').trim())
                    &&!/Sample record/i.test(String(r?.remarks??''));
            });
        }catch(e){return [];}
    },

    isUnreportedCalf(r){
        return r.pd_result==='Positive'&&!r.calf_drop_reported;
    },

    showUnreportedCalves(){
        this.resetFilters();
        this.filterPD='Positive';
        this.unreportedCalvesOnly=true;
    },

    showOverdueCalves(cooperativeId=''){
        this.resetFilters();
        this.filterCooperative=String(cooperativeId);
        this.filterPD='Positive';
        this.unreportedCalvesOnly=true;
        this.overdueCalvesOnly=true;
    },

    showReportedPregnancies(){
        this.includeReportedPregnancies=true;
        this.unreportedCalvesOnly=false;
        this.overdueCalvesOnly=false;
        this.page=1;
    },

    hiddenReportedCount(){
        return this.includeReportedPregnancies?0:this.matchingRecords().filter(r=>r.pregnancy_followup_complete).length;
    },

    matchingRecords(){
        const q=this.search.toLowerCase().trim();

        return this.records.filter(r=>
            (!q||this.fields.some(f=>String(r[f]??'').toLowerCase().includes(q)))&&
            (!this.filterMatingType||r.mating_type===this.filterMatingType)&&
            (!this.filterCooperative||String(r.cooperative_id)===this.filterCooperative)&&
            (!this.filterYear||String(r.breeding_date||r.pd_date||'').slice(0,4)===this.filterYear)&&
            (!this.filterPD||(r.pd_result||'Pending')===this.filterPD)&&
            (!this.filterDate||r.breeding_date===this.filterDate)
        );
    },

    filteredRecords(){
        return this.matchingRecords().filter(r=>
            (this.includeReportedPregnancies||!r.pregnancy_followup_complete)&&
            (!this.overdueCalvesOnly||r.calf_drop_overdue)&&
            (!this.unreportedCalvesOnly||this.isUnreportedCalf(r))
        );
    },

    summaryCards(){
        const a=this.filteredRecords();

        return [
            {title:'Total Mating Records',value:a.length,description:'Records matching filters',icon:'fa-solid fa-list-check',bg:'bg-slate-100',color:'text-slate-700'},
            {title:'Artificial Insemination',value:a.filter(r=>r.mating_type==='AI').length,description:'AI records in selected year',icon:'fa-solid fa-syringe',bg:'bg-orange-50',color:'text-orange-700'},
            {title:'Natural Mating (NM)',value:a.filter(r=>r.mating_type==='NM').length,description:'Natural service records in selected year',icon:'fa-solid fa-horse',bg:'bg-emerald-50',color:'text-emerald-700'},
            {title:'PD Positive',value:a.filter(r=>r.pd_result==='Positive').length,description:'Confirmed positive diagnoses',icon:'fa-solid fa-heart',bg:'bg-emerald-50',color:'text-emerald-700'},
            {title:'Calf Drop Not Reported',value:this.records.filter(r=>this.isUnreportedCalf(r)).length,description:'Positive PD records across all years',followup:true,icon:'fa-solid fa-clipboard-question',bg:'bg-amber-50',color:'text-amber-700'}
        ];
    },

    availableYears(){
        const years=this.records.map(r=>Number(String(r.breeding_date||r.pd_date||'').slice(0,4))).filter(year=>Number.isInteger(year)&&year>=1900);
        const first=years.length?Math.min(...years):new Date().getFullYear();
        const last=Math.max(new Date().getFullYear()+1,...years);
        return Array.from({length:last-first+1},(_,index)=>String(last-index));
    },

    totalPages(){
        return Math.max(1,Math.ceil(this.filteredRecords().length/this.perPage));
    },

    paginatedRecords(){
        if(this.page>this.totalPages())this.page=this.totalPages();
        return this.filteredRecords().slice((this.page-1)*this.perPage,this.page*this.perPage);
    },

    resetFilters(){
        this.search='';
        this.filterMatingType='';
        this.filterCooperative='';
        this.filterPD='';
        this.filterDate='';
        this.filterYear='';
        this.unreportedCalvesOnly=false;
        this.overdueCalvesOnly=false;
        this.includeReportedPregnancies=false;
        this.page=1;
    },

    pdClass(v){
        return v==='Positive'?'bg-emerald-50 text-emerald-700':
            v==='Negative'?'bg-rose-50 text-rose-700':
            'bg-amber-50 text-amber-700';
    },

    label(f){
        return ({
            cooperative:'Cooperative',
            farmer:'Farmer',
            animal_id:'Animal ID',
            breeding_date:'Mating Date',
            mating_type:'Mating Type',
            bull_id:'Bull ID',
            bull_name:'Bull Name',
            straws:'Straws Used',
            heat_status:'Estrus / Heat Type',
            mating_technician:'Mating Technician',
            pd_date:'PD Date',
            pd_result:'PD Result',
            pregnancy_months:'Pregnancy Months',
            pd_technician:'PD Confirming Technician',
            expected_calving:'Expected Calving',
            actual_calving:'Actual Calving',
            remarks:'Remarks'
        })[f]||f;
    },

    msg(s,t='success'){
        this.notice=s;
        this.noticeType=t;
        if(this.noticeTimer)clearTimeout(this.noticeTimer);
        this.noticeTimer=setTimeout(()=>this.notice='',4500);
    },

    esc(v){
        return '"'+String(v??'').replace(/"/g,'""')+'"';
    },

    exportCSV(){
        const h=[
            'id','cooperative_id','farmer_id','carabao_id','breeding_date',
            'mating_type','semen_bull_id','bull_id','bull_name','straws_used',
            'estrus_type','mating_technician','pd_date','pd_result',
            'pregnancy_months','pd_technician','expected_calving_date',
            'actual_calving_date','remarks'
        ];

        const lines=[
            h.map(x=>this.esc(x)).join(','),
            ...this.filteredRecords().map(r=>[
                r.id,r.cooperative_id,r.farmer_id,r.carabao_id,r.breeding_date,
                r.mating_type,r.semen_bull_id,r.bull_id,r.bull_name,r.straws,
                r.heat_status,r.mating_technician,r.pd_date,r.pd_result,
                r.pregnancy_months,r.pd_technician,r.expected_calving,
                r.actual_calving,r.remarks
            ].map(x=>this.esc(x)).join(','))
        ];

        const blob=new Blob(['\uFEFF'+lines.join('\r\n')],{type:'text/csv;charset=utf-8;'});
        const url=URL.createObjectURL(blob);
        const a=document.createElement('a');
        a.href=url;
        a.download='cbed_mating_records.csv';
        a.click();
        URL.revokeObjectURL(url);
        this.msg('Filtered database records exported.');
    },

    downloadTemplate(){
        if(typeof XLSX==='undefined'){
            this.msg('Excel library unavailable. Check your internet connection.','error');
            return;
        }

        const headers=[
            'cooperative_id','farmer_id','carabao_id','breeding_date',
            'mating_type','semen_bull_id','bull_id','bull_name','straws_used',
            'estrus_type','mating_technician','pd_date','pd_result',
            'pregnancy_months','pd_technician','expected_calving_date',
            'actual_calving_date','remarks'
        ];

        const example=[
            '', '', '', '2026-10-28',
            'AI', '', '', '', 1, 'ES',
            'Technician Name', '', 'Pending', '', '', '', '', ''
        ];

        const workbook=XLSX.utils.book_new();
        const worksheet=XLSX.utils.aoa_to_sheet([headers,example]);

        worksheet['!cols']=headers.map(h=>({wch:Math.max(18,h.length+3)}));

        XLSX.utils.book_append_sheet(workbook,worksheet,'Mating Records');

        const instructions=[
            ['CBED MATING & PREGNANCY MONITORING'],
            ['Instructions'],
            ['1. Enter database IDs for cooperative_id, farmer_id, and carabao_id.'],
            ['2. Import one cooperative per workbook.'],
            ['3. Use YYYY-MM-DD for dates, for example 2026-10-28.'],
            ['4. mating_type must be AI or Natural Mating.'],
            ['5. estrus_type must be ES, NH, or FTAI.'],
            ['6. pd_result must be Pending, Positive, or Negative.'],
            ['7. pregnancy_months is required for Positive and must be 1–11.'],
            ['8. Positive/Negative diagnosis requires pd_date and pd_technician.'],
            ['9. Leave optional values blank when not applicable.'],
            ['10. Remove the example row before importing actual records.']
        ];

        const guide=XLSX.utils.aoa_to_sheet(instructions);
        guide['!cols']=[{wch:100}];

        XLSX.utils.book_append_sheet(workbook,guide,'Instructions');
        XLSX.writeFile(workbook,'cbed_mating_template.xlsx');
        this.msg('Excel template downloaded.');
    },

    excelDate(value){
        if(value===''||value===null||value===undefined)return null;

        if(value instanceof Date&&!isNaN(value.getTime())){
            return [
                value.getFullYear(),
                String(value.getMonth()+1).padStart(2,'0'),
                String(value.getDate()).padStart(2,'0')
            ].join('-');
        }

        if(typeof value==='number'){
            const p=XLSX.SSF.parse_date_code(value);
            if(!p)throw new Error('Invalid Excel date.');

            return [
                String(p.y).padStart(4,'0'),
                String(p.m).padStart(2,'0'),
                String(p.d).padStart(2,'0')
            ].join('-');
        }

        const s=String(value).trim();

        if(/^\d{4}-\d{2}-\d{2}$/.test(s)){
            const [y,m,d]=s.split('-').map(Number);
            const date=new Date(y,m-1,d);

            if(date.getFullYear()!==y||date.getMonth()!==m-1||date.getDate()!==d){
                throw new Error('Invalid date: '+s+'. Use YYYY-MM-DD.');
            }

            return s;
        }

        const match=s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);

        if(match){
            const month=Number(match[1]);
            const day=Number(match[2]);
            const year=Number(match[3]);
            const date=new Date(year,month-1,day);

            if(date.getFullYear()!==year||date.getMonth()!==month-1||date.getDate()!==day){
                throw new Error('Invalid date: '+s);
            }

            return [
                year,
                String(month).padStart(2,'0'),
                String(day).padStart(2,'0')
            ].join('-');
        }

        throw new Error('Unrecognized date: '+s+'. Use YYYY-MM-DD.');
    },

    async importXLSX(e){
        const file=e.target.files?.[0];
        if(!file)return;

        try{
            if(typeof XLSX==='undefined'){
                throw new Error('Excel library did not load. Check your internet connection.');
            }

            if(!file.name.toLowerCase().endsWith('.xlsx')){
                throw new Error('Please select an .xlsx Excel file.');
            }

            const workbook=XLSX.read(await file.arrayBuffer(),{
                type:'array',
                cellDates:true
            });

            const sheet=workbook.Sheets[workbook.SheetNames[0]];
            if(!sheet)throw new Error('No worksheet found in the Excel file.');

            const data=XLSX.utils.sheet_to_json(sheet,{
                header:1,
                defval:'',
                raw:true,
                blankrows:false
            });

            if(data.length<2){
                throw new Error('The worksheet must contain a header row and at least one data row.');
            }

            const headers=data[0].map(h=>
                String(h||'').trim().toLowerCase().replace(/[\s/-]+/g,'_')
            );

            const required=[
                'cooperative_id','farmer_id','carabao_id','breeding_date'
            ];

            const missing=required.filter(h=>!headers.includes(h));

            if(missing.length){
                throw new Error('Missing required columns: '+missing.join(', '));
            }

            const allowed=[
                'farmer_id','carabao_id','breeding_date','mating_type',
                'semen_bull_id','bull_id','bull_name','straws_used',
                'estrus_type','mating_technician','pd_date','pd_result',
                'pregnancy_months','pd_technician','expected_calving_date',
                'actual_calving_date','remarks'
            ];

            const parsed=data.slice(1).map((values,index)=>{
                const row={};
                headers.forEach((h,i)=>row[h]=values[i]??'');
                return {row,rowNumber:index+2};
            }).filter(x=>
                x.row.cooperative_id!==''&&
                x.row.farmer_id!==''&&
                x.row.carabao_id!==''&&
                x.row.breeding_date!==''
            );

            if(!parsed.length){
                throw new Error('No usable records found. Check the required IDs and mating dates.');
            }

            const coop=String(parsed[0].row.cooperative_id).trim();

            if(parsed.some(x=>String(x.row.cooperative_id).trim()!==coop)){
                throw new Error('Import one cooperative per Excel file.');
            }

            const records=parsed.map(({row,rowNumber})=>{
                const rec={};

                allowed.forEach(k=>{
                    if(Object.prototype.hasOwnProperty.call(row,k)){
                        rec[k]=row[k]===''?null:row[k];
                    }
                });

                rec.breeding_date=this.excelDate(row.breeding_date);

                ['pd_date','expected_calving_date','actual_calving_date'].forEach(k=>{
                    rec[k]=row[k]!==''?this.excelDate(row[k]):null;
                });

                rec.mating_type=rec.mating_type||'AI';
                rec.pd_result=rec.pd_result||'Pending';

                if(!['AI','Natural Mating'].includes(rec.mating_type)){
                    throw new Error('Excel row '+rowNumber+': mating_type must be AI or Natural Mating.');
                }

                if(!['Pending','Positive','Negative'].includes(rec.pd_result)){
                    throw new Error('Excel row '+rowNumber+': invalid pd_result.');
                }

                if(rec.estrus_type&&!['ES','NH','FTAI'].includes(rec.estrus_type)){
                    throw new Error('Excel row '+rowNumber+': estrus_type must be ES, NH, or FTAI.');
                }

                if(rec.pd_result==='Positive'){
                    const months=Number(rec.pregnancy_months);

                    if(rec.pregnancy_months===null||rec.pregnancy_months===undefined||
                        rec.pregnancy_months===''||!Number.isInteger(months)||months<1||months>11){
                        throw new Error('Excel row '+rowNumber+': Positive PD requires pregnancy_months from 1 to 11.');
                    }
                }else{
                    rec.pregnancy_months=null;
                }

                if(rec.pd_result!=='Pending'&&(!rec.pd_date||!String(rec.pd_technician||'').trim())){
                    throw new Error('Excel row '+rowNumber+': Positive/Negative PD requires pd_date and pd_technician.');
                }

                if(rec.straws_used!==null&&rec.straws_used!==undefined&&rec.straws_used!==''){
                    const straws=Number(rec.straws_used);
                    if(!Number.isInteger(straws)||straws<0){
                        throw new Error('Excel row '+rowNumber+': straws_used must be a non-negative whole number.');
                    }
                    rec.straws_used=straws;
                }

                if(rec.pregnancy_months!==null&&rec.pregnancy_months!==undefined&&rec.pregnancy_months!==''){
                    rec.pregnancy_months=Number(rec.pregnancy_months);
                }

                return rec;
            });

            await this.request(this.api.bulk,{
                method:'POST',
                body:JSON.stringify({
                    cooperative_id:Number(coop),
                    records
                })
            });

            await this.loadRecords();
            this.msg(records.length+' Excel record(s) imported into MySQL.');
        }catch(err){
            this.msg(err.message||'Excel import failed.','error');
        }finally{
            e.target.value='';
        }
    }
};}
</script>
@endpush
