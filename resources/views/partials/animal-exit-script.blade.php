@push('scripts')
<script>
function animalExitDialog(cooperatives, farmers, animals, labels, natureOptions, baseUrl, today, monitoringMode = false) {
    const directory=monitoringMode ? {} : milkProductionEntry(cooperatives,farmers,animals,'','','',0);
    const fields=Object.keys(labels);
    return {...directory, labels, fields, mode:'add', record:{}, draft:{}, natureOptions, error:'', saving:false, monitoringContext:null,
        titles:{add:'Add Exit',view:'Exit Record Details',edit:'Edit Exit Record',delete:'Delete exit record?',monitoring:'Add Exit'},
        get entryMode() { return ['add', 'edit', 'monitoring'].includes(this.mode); },
        options(picker){
            const choices=directory.options.call(this,picker);
            if(picker!=='animal')return choices;
            const allowed=new Set(animals.filter(item=>String(item.cooperative_id)===this.cooperative).map(item=>item.id));
            return choices.filter(item=>allowed.has(item.id));
        },
        choose(picker,item,wrapper){
            const changed=this[picker]!==String(item.id);
            directory.choose.call(this,picker,item,wrapper);
            if(changed && picker!=='animal')this.selectAnimal('');
        },
        selectAnimal(id){
            const selected=animals.find(item=>String(item.id)===id);
            this.animal=id;
            this.draft.entrustment=selected?.entrustment || '';
            this.draft.ownership_status=selected?.ownership_status || '';
        },
        showExit({mode,record={}}){
            if(this.saving)return;
            this.mode=mode;
            this.record=record;
            this.error='';
            this.cooperative=this.farmer=this.animal=this.openPicker='';
            this.draft=Object.fromEntries(fields.map(field=>[field,record[field] ?? (mode==='add' && field==='exit_date' ? today : '')]));
            this.natureOptions=[...new Set([...natureOptions,...(record.nature_of_exit ? [record.nature_of_exit] : [])])];
            this.$root.showModal();
            this.$nextTick(()=>this.$refs.cancel.focus());
        },
        showMonitoringExit(context) {
            if (!monitoringMode) return;
            this.monitoringContext = context;
            this.showExit({mode: 'monitoring', record: {
                exit_date: context.reportDate || today,
                entrustment: context.dam.entrustment,
                ownership_status: context.dam.ownership_status,
                ...context.data,
                cooperative_name: context.cooperative,
                farmer_name: context.farmer,
                animal_identifier: context.registryId,
            }});
        },
        finishMonitoringExit(data) {
            const index = this.monitoringContext.index;
            this.$root.close();
            this.$dispatch('monitoring-exit-draft', {index, data});
        },
        async save(){
            if (this.mode === 'monitoring') {
                this.finishMonitoringExit({...this.draft});
                return;
            }
            if(this.saving || this.mode==='view' || (this.mode==='add' ? !this.animal : !this.record.record_id))return;
            const adding=this.mode==='add';
            const deleting=this.mode==='delete';
            const payload=adding ? {...this.draft,cooperative_id:this.cooperative,farmer_id:this.farmer,carabao_id:this.animal} : this.draft;
            this.saving=true;
            this.error='';
            try{
                const response=await fetch(adding ? baseUrl : baseUrl+'/'+this.record.record_id,{
                    method:adding ? 'POST' : (deleting ? 'DELETE' : 'PUT'),
                    headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || ''},
                    body:deleting ? undefined : JSON.stringify(payload)
                });
                if(!response.ok){
                    const result=await response.json().catch(()=>({}));
                    throw Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Could not save the exit record.');
                }
                this.$root.close();
                if(adding)window.location.assign(baseUrl);
                else window.location.reload();
            }catch(error){
                this.error=error.message;
            }finally{
                this.saving=false;
            }
        }
    };
}
</script>
@endpush
