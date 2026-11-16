<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
class ModuleController extends Controller {
 private array $map=['farmers'=>'farmers','cooperatives'=>'cooperatives','carabaos'=>'carabaos','mating'=>'mating_records','fhpr'=>'fhpr_records','calves'=>'calves','milk'=>'milk_records','mtd'=>'mtd_records','health'=>'health_records','bcs'=>'bcs_records','nature-of-exit'=>'nature_of_exits','exits'=>'nature_of_exits','semen-bulls'=>'semen_bulls','semen-usage'=>'semen_usage','meat'=>'cooperative_product_records','manure'=>'cooperative_product_records'];
 private function table($m){abort_unless(isset($this->map[$m]),404);return $this->map[$m];}
 public function monitoring(Request $request){
     $cooperatives=DB::table('cooperatives')->orderBy('cooperative_name')->get(['id','cooperative_name']);
     $monthInput=(string)$request->query('month',now()->format('Y-m'));
     try{$monthStart=Carbon::createFromFormat('!Y-m',$monthInput)->startOfMonth();}catch(\Throwable){$monthStart=now()->startOfMonth();}
     $selectedMonth=$monthStart->format('Y-m');
     $selectedCooperativeId=(string)$request->query('cooperative_id','');
     $reportSearch=trim((string)$request->query('report_search',''));
     $reportSpecification=(string)$request->query('report_specification','');
     $reportAvailability=(string)$request->query('report_availability','');
     $reportStatus=(string)$request->query('report_status','');
     $hasReportsTable=Schema::hasTable('monitoring_reports');
     $hasDetails=Schema::hasTable('monitoring_report_details');
     $decorate=function($report)use($hasDetails){
         $details=$hasDetails?DB::table('monitoring_report_details')->where('monitoring_report_id',$report->id)->orderBy('id')->get():collect();
         $legacy=json_decode((string)($report->report_data??''),true)?:[];
         $rows=$details->isNotEmpty()?$details->map(fn($d)=>[
             'detail_id'=>$d->id,'carabao_id'=>$d->carabao_id,'farmer_name'=>$d->farmer_name??'',
             'bolus_id'=>$d->bolus_id,'registry_id'=>$d->registry_number,'tag_number'=>$d->tag_number,
             'animal_name'=>$d->animal_name,'sex'=>$d->sex,'birth_date'=>$d->birth_date,'breed'=>$d->breed,
             'availability_status'=>$d->availability_status,'unavailability_reason'=>$d->unavailability_reason,
             'monitoring_specification'=>$d->monitoring_specification,'destination_module'=>$d->destination_module,
             'monitoring_status'=>$d->monitoring_status,'monitoring_data'=>json_decode((string)$d->monitoring_data,true)?:[],
             'remarks'=>$d->remarks,
         ])->all():($legacy['rows']??[]);
         $available=count(array_filter($rows,fn($r)=>($r['availability_status']??'available')==='available'));
         $unavailable=count($rows)-$available;
         $completed=count(array_filter($rows,fn($r)=>($r['monitoring_status']??'')==='completed'));
         $specs=array_values(array_unique(array_filter(array_column($rows,'monitoring_specification'))));
         $serviceCount=function($key)use($rows){return count(array_filter($rows,function($row)use($key){$v=$row['monitoring_data'][$key]??null;return $v!==null&&$v!==''&&!in_array(mb_strtolower((string)$v),['no','n/a','na','none','-','0'],true);}));};
         $report->report_data=['rows'=>$rows];
         $serviceNames=collect($rows)->flatMap(function($row){
             $data=$row['monitoring_data']??[];
             $services=collect($data['services']??[])->filter(fn($service)=>is_array($service)&&filter_var($service['selected']??false,FILTER_VALIDATE_BOOLEAN))
                 ->map(fn($service)=>(($service['type']??'')==='Other Services'?'Other Services: '.($service['other_service_name']??''):(($service['type']??''))))
                 ->filter()->values();
             if(!empty($data['service_type']))$services->push($data['service_type']);
             return $services;
         });
         $serviceSummary=$serviceNames->countBy()->map(fn($n,$name)=>$name.' ('.$n.')')->values()->all();
         $report->summary=['animals'=>count($rows),'available'=>$available,'unavailable'=>$unavailable,'completed'=>$completed,'pending'=>max(0,$available-$completed),'specifications'=>$specs,'service_summary'=>$serviceSummary,
             'blood_collection'=>$serviceCount('blood_collection'),'fecalysis'=>$serviceCount('fecalysis'),'vaccine'=>$serviceCount('vaccine'),'deworming'=>$serviceCount('deworming'),'vi_supp'=>$serviceCount('vi_supp'),'ear_tagged'=>$serviceCount('ear_tagged'),'bolus'=>$serviceCount('bolus'),'flashing'=>$serviceCount('flashing')];
         return $report;
     };
     $historyQuery=$hasReportsTable?DB::table('monitoring_reports')->leftJoin('users','users.id','=','monitoring_reports.created_by')->select('monitoring_reports.*')->selectRaw("TRIM(CONCAT(COALESCE(users.name,''))) as created_by_name"):null;
     if($historyQuery&&$selectedCooperativeId!==''&&ctype_digit($selectedCooperativeId))$historyQuery->where('cooperative_id',(int)$selectedCooperativeId);
     if($historyQuery&&$reportSearch!=='')$historyQuery->where('cooperative_name','like','%'.$reportSearch.'%');
     if($historyQuery&&$hasDetails&&$reportSpecification!=='')$historyQuery->whereExists(fn($q)=>$q->selectRaw('1')->from('monitoring_report_details')->whereColumn('monitoring_report_details.monitoring_report_id','monitoring_reports.id')->where('monitoring_specification',$reportSpecification));
     if($historyQuery&&$hasDetails&&$reportAvailability!=='')$historyQuery->whereExists(fn($q)=>$q->selectRaw('1')->from('monitoring_report_details')->whereColumn('monitoring_report_details.monitoring_report_id','monitoring_reports.id')->where('availability_status',$reportAvailability));
     if($historyQuery&&$hasDetails&&$reportStatus!=='')$historyQuery->whereExists(fn($q)=>$q->selectRaw('1')->from('monitoring_report_details')->whereColumn('monitoring_report_details.monitoring_report_id','monitoring_reports.id')->where('monitoring_status',$reportStatus));
     $reports=$historyQuery?$historyQuery->orderByDesc('monitoring_reports.report_date')->orderByDesc('monitoring_reports.id')->limit(100)->get()->map($decorate):collect();
     $calendarQuery=$hasReportsTable?DB::table('monitoring_reports')->whereBetween('report_date',[$monthStart->toDateString(),$monthStart->copy()->endOfMonth()->toDateString()]):null;
     if($calendarQuery&&$selectedCooperativeId!==''&&ctype_digit($selectedCooperativeId))$calendarQuery->where('cooperative_id',(int)$selectedCooperativeId);
     if($calendarQuery&&$reportSearch!=='')$calendarQuery->where('cooperative_name','like','%'.$reportSearch.'%');
     if($calendarQuery&&$hasDetails&&$reportSpecification!=='')$calendarQuery->whereExists(fn($q)=>$q->selectRaw('1')->from('monitoring_report_details')->whereColumn('monitoring_report_details.monitoring_report_id','monitoring_reports.id')->where('monitoring_specification',$reportSpecification));
     if($calendarQuery&&$hasDetails&&$reportAvailability!=='')$calendarQuery->whereExists(fn($q)=>$q->selectRaw('1')->from('monitoring_report_details')->whereColumn('monitoring_report_details.monitoring_report_id','monitoring_reports.id')->where('availability_status',$reportAvailability));
     if($calendarQuery&&$hasDetails&&$reportStatus!=='')$calendarQuery->whereExists(fn($q)=>$q->selectRaw('1')->from('monitoring_report_details')->whereColumn('monitoring_report_details.monitoring_report_id','monitoring_reports.id')->where('monitoring_status',$reportStatus));
     $calendarReports=$calendarQuery?$calendarQuery->orderBy('report_date')->orderBy('cooperative_name')->orderBy('id')->get()->map($decorate):collect();
     $calendarReportsByDay=$calendarReports->groupBy(fn($r)=>(int)Carbon::parse($r->report_date)->day);
     $selectedReport=$hasReportsTable&&$request->filled('report_id')?DB::table('monitoring_reports')->where('id',(int)$request->query('report_id'))->first():null;
     $activeReport=$selectedReport?$decorate($selectedReport):$reports->first();
     $activeSummary=$activeReport->summary??['animals'=>0,'available'=>0,'unavailable'=>0,'completed'=>0,'pending'=>0];
     $counts=['total_animals'=>$activeSummary['animals'],'available'=>$activeSummary['available'],'unavailable'=>$activeSummary['unavailable'],'monitored'=>$activeSummary['completed'],'pending'=>$activeSummary['pending']];
     $calendarDaysInMonth=(int)$monthStart->daysInMonth;$calendarStartOffset=$monthStart->dayOfWeekIso-1;
     $previousMonth=$monthStart->copy()->subMonth()->format('Y-m');$nextMonth=$monthStart->copy()->addMonth()->format('Y-m');
     // Read-only 2026 integration across the existing monitoring destination tables.
     $reportYear=(int)$request->query('year',2026);if($reportYear<2000||$reportYear>2100)$reportYear=2026;$yearStart=sprintf('%04d-01-01',$reportYear);$yearEnd=sprintf('%04d-12-31',$reportYear);$calendarStart='2026-01-01';$calendarEnd='2026-12-31';$fetchStart=min($yearStart,$calendarStart);$fetchEnd=max($yearEnd,$calendarEnd);$monitoringModules=['calf_monitoring'=>collect(),'breeding'=>collect(),'health_nutrition'=>collect(),'other_services'=>collect()];$calendarEvents=collect();
     if($hasReportsTable){
         $yearReports=DB::table('monitoring_reports')->whereBetween('report_date',[$fetchStart,$fetchEnd])->orderBy('report_date')->get();
         foreach($yearReports as $report){
             $details=$hasDetails?DB::table('monitoring_report_details')->where('monitoring_report_id',$report->id)->orderBy('id')->get():collect();
             foreach($details as $detail){$data=json_decode((string)$detail->monitoring_data,true)?:[];$spec=$detail->monitoring_specification?:'other_services';$category=in_array($spec,['calf_monitoring','breeding','health_nutrition'],true)?$spec:'other_services';if(!empty($detail->destination_record_id)&&in_array($category,['calf_monitoring','breeding','health_nutrition'],true))continue;$eventDate=$data['date_of_birth']??$data['breeding_date']??$data['service_date']??$data['monitoring_date']??$report->report_date;
                 $item=(object)['date'=>$eventDate,'service'=>$spec==='calf_monitoring'?'Calf Drop':($spec==='breeding'?'Mating / Breeding':($spec==='health_nutrition'?'Health and Nutrition':($data['service_type']??'Other Services'))),'animal_id'=>$detail->registry_number?:$detail->animal_database_id??$detail->bolus_id,'bolus_id'=>$detail->bolus_id,'animal_name'=>$detail->animal_name,'farmer_name'=>$detail->farmer_name,'cooperative_name'=>$report->cooperative_name,'details'=>$data,'remarks'=>$detail->remarks,'source_id'=>$detail->id];$monitoringModules[$category]->push($item);$calendarEvents->push($item);
             }
         }
     }
     if(Schema::hasTable('calves')){$calves=DB::table('calves as calf')->leftJoin('carabaos as dam','dam.id','=','calf.dam_id')->leftJoin('carabaos as sire','sire.animal_id','=','calf.sire_id')->leftJoin('semen_bulls as sire_bull','sire_bull.bull_id','=','calf.sire_id')->leftJoin('farmers as dam_farmer','dam_farmer.id','=','dam.farmer_id')->leftJoin('cooperatives as dam_cooperative','dam_cooperative.id','=',DB::raw('COALESCE(dam.cooperative_id, dam_farmer.cooperative_id)'))->whereBetween('calf.birth_date',[$fetchStart,$fetchEnd])->orderBy('calf.birth_date')->get(['calf.*','dam.animal_id as dam_animal_id','dam.animal_name as dam_name','sire.animal_id as matched_sire_id','sire.animal_name as animal_sire_name','sire_bull.bull_name as bull_sire_name','dam_farmer.first_name as farmer_first_name','dam_farmer.middle_name as farmer_middle_name','dam_farmer.last_name as farmer_last_name','dam_cooperative.cooperative_name']);foreach($calves as $c){$item=(object)['date'=>$c->birth_date,'service'=>'Calf Drop','animal_id'=>$c->calf_animal_id,'animal_name'=>null,'dam_id'=>$c->dam_animal_id,'dam_name'=>$c->dam_name,'sire_id'=>$c->sire_id,'sire_name'=>$c->animal_sire_name?:$c->bull_sire_name,'farmer_name'=>trim(implode(' ',array_filter([$c->farmer_first_name,$c->farmer_middle_name,$c->farmer_last_name]))),'cooperative_name'=>$c->cooperative_name,'remarks'=>$c->remarks??$c->calf_findings??null,'birth_outcome'=>$c->birth_outcome??null,'animal_status'=>$c->animal_status??null,'details'=>$c,'source_id'=>$c->id];$monitoringModules['calf_monitoring']->push($item);if($item->date>=$calendarStart&&$item->date<=$calendarEnd)$calendarEvents->push($item);}}
     if(Schema::hasTable('mating_records')){$breedings=DB::table('mating_records as mr')->leftJoin('carabaos as animal','animal.id','=','mr.carabao_id')->leftJoin('farmers as farmer','farmer.id','=','animal.farmer_id')->leftJoin('cooperatives as cooperative','cooperative.id','=',DB::raw('COALESCE(mr.cooperative_id, animal.cooperative_id, farmer.cooperative_id)'))->leftJoin('semen_bulls as bull','bull.bull_id','=','mr.bull_id')->whereBetween('mr.mating_date',[$fetchStart,$fetchEnd])->orderBy('mr.mating_date')->get(['mr.*','animal.animal_id','animal.animal_name','animal.bolus_id','bull.bull_name','farmer.first_name as farmer_first_name','farmer.middle_name as farmer_middle_name','farmer.last_name as farmer_last_name','cooperative.cooperative_name']);foreach($breedings as $b){$item=(object)['date'=>$b->mating_date,'service'=>'Mating / Breeding ('.$b->mating_type.')','animal_id'=>$b->animal_id?:$b->registry_number,'bolus_id'=>$b->bolus_id,'animal_name'=>$b->animal_name,'farmer_name'=>trim(implode(' ',array_filter([$b->farmer_first_name,$b->farmer_middle_name,$b->farmer_last_name]))),'cooperative_name'=>$b->cooperative_name,'sire_id'=>$b->bull_id,'sire_name'=>$b->bull_name,'pregnancy_status'=>$b->pregnancy_result,'details'=>$b,'source_id'=>$b->id];$monitoringModules['breeding']->push($item);if($item->date>=$calendarStart&&$item->date<=$calendarEnd)$calendarEvents->push($item);}}
     if(Schema::hasTable('health_records')){$health=DB::table('health_records as hr')->leftJoin('carabaos as animal','animal.id','=','hr.carabao_id')->leftJoin('farmers as farmer','farmer.id','=','animal.farmer_id')->leftJoin('cooperatives as cooperative','cooperative.id','=',DB::raw('COALESCE(hr.cooperative_id, animal.cooperative_id, farmer.cooperative_id)'))->whereBetween('hr.monitoring_date',[$fetchStart,$fetchEnd])->orderBy('hr.monitoring_date')->get(['hr.*','animal.animal_id','animal.animal_name','animal.bolus_id','farmer.first_name as farmer_first_name','farmer.middle_name as farmer_middle_name','farmer.last_name as farmer_last_name','cooperative.cooperative_name']);foreach($health as $h){$source=json_decode((string)($h->source_data??''),true)?:[];$data=$source['monitoring_fields']??[];$services=$source['services']??$data['services']??[];$serviceNames=collect($services)->filter(fn($s)=>is_array($s)&&($s['selected']??true))->map(fn($s)=>($s['type']??'')==='Other Services'?($s['other_service_name']??'Other Services'):($s['type']??''))->filter()->values();foreach(['blood_collection'=>'BC - Blood Collection','fecalysis'=>'FS - Fecalysis','vaccine'=>'Vac - Vaccination','deworming'=>'Deworming','vi_supp'=>'Vitamin Supplementation','ear_tagged'=>'Ear Tagging','bolus'=>'Bolus Application','flashing'=>'Flushing'] as $field=>$label){$value=$data[$field]??$source[$field]??null;if($value!==null&&$value!==''&&!in_array(mb_strtolower((string)$value),['0','no','false','none','n/a','na'],true))$serviceNames->push($label);}$service=$serviceNames->isNotEmpty()?$serviceNames->implode(', '):($h->treatment?:$h->record_category??'Health and Nutrition');$item=(object)['date'=>$h->monitoring_date,'service'=>$service,'animal_id'=>$h->animal_id?:($h->registry_number??null),'bolus_id'=>$h->bolus_id,'animal_name'=>$h->animal_name,'farmer_name'=>trim(implode(' ',array_filter([$h->farmer_first_name,$h->farmer_middle_name,$h->farmer_last_name]))),'cooperative_name'=>$h->cooperative_name,'details'=>$data,'health_status'=>$h->health_status,'remarks'=>$h->remarks,'source_id'=>$h->id];$category='health_nutrition';$monitoringModules[$category]->push($item);if($item->date>=$calendarStart&&$item->date<=$calendarEnd)$calendarEvents->push($item);}}
     $calendarEvents=$calendarEvents->filter(fn($e)=>!empty($e->date)&&$e->date>=$calendarStart&&$e->date<=$calendarEnd)->sortBy('date')->values();$calendarEventsByDate=$calendarEvents->groupBy('date');
     $selectedDate=(string)$request->query('date','');if(!preg_match('/^2026-\d{2}-\d{2}$/',$selectedDate))$selectedDate='';$calendarView=in_array($request->query('view'),['day','month','year'],true)?$request->query('view'):'month';
     $inventoryAnimals=Schema::hasTable('carabaos')?DB::table('carabaos')->get():collect();$deadTerms=['dead','death'];$exitOutTerms=['transferred','sold','disposed','culled','dead'];$exitAnimalIds=Schema::hasTable('nature_of_exits')?DB::table('nature_of_exits as exit_record')->join('carabaos as animal','animal.id','=','exit_record.carabao_id')->whereIn(DB::raw('LOWER(exit_record.nature_of_exit)'),$exitOutTerms)->pluck('animal.animal_id')->all():[];$outAnimals=$inventoryAnimals->filter(fn($a)=>in_array(mb_strtolower((string)($a->status??'')),$deadTerms,true)||in_array(mb_strtolower((string)($a->nature_of_exit??'')),$exitOutTerms,true)||in_array($a->animal_id,$exitAnimalIds,true));$outIds=$outAnimals->pluck('id')->all();$animalAvailability=['available'=>0,'unavailable'=>0,'out'=>$outAnimals->count()];foreach($inventoryAnimals->reject(fn($a)=>in_array($a->id,$outIds,true)) as $a){$aliases=array_filter([$a->animal_id??null,$a->registry_number??null,$a->bolus_id??null,$a->tag_number??null]);$hasHistory=$calendarEvents->contains(fn($e)=>in_array($e->animal_id??null,$aliases,true)||in_array($e->bolus_id??null,$aliases,true));$animalAvailability[$hasHistory?'available':'unavailable']++;}
     $animalDirectory=Schema::hasTable('carabaos')?DB::table('carabaos as animal')->leftJoin('farmers as farmer','farmer.id','=','animal.farmer_id')->leftJoin('cooperatives as cooperative','cooperative.id','=',DB::raw('COALESCE(animal.cooperative_id, farmer.cooperative_id)'))->get(['animal.*','farmer.id as matched_farmer_id','farmer.first_name','farmer.middle_name','farmer.last_name','cooperative.id as matched_cooperative_id','cooperative.cooperative_name']):collect();
     $categoryLabels=['calf_monitoring'=>'Calf Monitoring','breeding'=>'Breeding','health_nutrition'=>'Health and Nutrition','other_services'=>'Other Services'];
     $makeRowKey=fn($identity,$farmerId,$cooperativeId)=>mb_strtolower(implode('|',[(string)$identity,(string)($farmerId??''),(string)($cooperativeId??'')]));
     $animalLookup=[];$consolidated=[];$animalRowKeys=[];
     foreach($animalDirectory as $animal){
         $farmerName=trim(implode(' ',array_filter([$animal->first_name??null,$animal->middle_name??null,$animal->last_name??null])));
         $cooperativeId=$animal->matched_cooperative_id??$animal->cooperative_id??null;$farmerId=$animal->matched_farmer_id??$animal->farmer_id??null;$identity=(string)($animal->animal_id??$animal->registry_number??$animal->id);
         $rowKey=$makeRowKey($identity,$farmerId,$cooperativeId);
         $isOut=in_array(mb_strtolower((string)($animal->status??'')),['dead','death'],true)||in_array(mb_strtolower((string)($animal->nature_of_exit??'')),$exitOutTerms,true)||in_array($animal->animal_id,$exitAnimalIds,true);
          $consolidated[$rowKey]=['cooperative'=>$animal->cooperative_name??'N/A','farmer'=>$farmerName?:'N/A','animal_id'=>$identity,'birth_date'=>$animal->birth_date??null,'breed'=>$animal->breed??null,'bcs'=>null,'bcs_date'=>null,'bolus_id'=>$animal->bolus_id??null,'calf'=>null,'breeding'=>null,'health'=>[],'other'=>[],'remarks'=>[],'availability'=>$isOut?'Out':'Unavailable','_search'=>[$identity,$farmerName,$animal->cooperative_name??'']];
         $info=['row_key'=>$rowKey,'farmer_id'=>$farmerId,'cooperative_id'=>$cooperativeId,'animal_name'=>$animal->animal_name??null,'sex'=>$animal->sex??null,'breed'=>$animal->breed??null,'farmer_name'=>$farmerName,'cooperative_name'=>$animal->cooperative_name??null,'inventory'=>$animal];
         foreach(['animal_id','registry_number','bolus_id','tag_number','id'] as $key)if(!empty($animal->{$key})){$alias=mb_strtolower((string)$animal->{$key});$animalLookup[$alias]=$info;$animalRowKeys[$alias]=$rowKey;}
     }
      if(Schema::hasTable('bcs_records')){$bcsRecords=DB::table('bcs_records')->whereBetween('record_date',[$yearStart,$yearEnd])->whereNotNull('body_condition_score')->orderByDesc('record_date')->orderByDesc('id')->get(['carabao_id','registry_number','record_date','body_condition_score']);foreach($bcsRecords as $bcs){$lookup=$animalLookup[mb_strtolower((string)($bcs->carabao_id??''))]??$animalLookup[mb_strtolower((string)($bcs->registry_number??''))]??[];$rowKey=$lookup['row_key']??null;if($rowKey!==null&&isset($consolidated[$rowKey])&&$consolidated[$rowKey]['bcs']===null){$consolidated[$rowKey]['bcs']=$bcs->body_condition_score;$consolidated[$rowKey]['bcs_date']=$bcs->record_date;}}}
     foreach($monitoringModules as $category=>$items)foreach($items as $item){
         if(empty($item->date)||$item->date<$yearStart||$item->date>$yearEnd)continue;
         $isCalf=$category==='calf_monitoring';$identity=(string)($isCalf?($item->dam_id??$item->animal_id??''):($item->animal_id??$item->bolus_id??''));
         $aliases=array_filter([$identity,(string)($item->animal_id??''),(string)($item->bolus_id??'')]);$lookup=[];foreach($aliases as $alias){$lookup=$animalLookup[mb_strtolower($alias)]??[];if($lookup)break;}
         $inventory=$lookup['inventory']??null;$farmerName=($item->farmer_name??null)?:($lookup['farmer_name']??'');$cooperativeName=($item->cooperative_name??null)?:($lookup['cooperative_name']??'');
         $rowKey=$lookup['row_key']??$makeRowKey($identity?:($item->source_id??'unknown'),$lookup['farmer_id']??$farmerName,$lookup['cooperative_id']??$cooperativeName);
         $animalOut=$inventory&&(in_array(mb_strtolower((string)($inventory->status??'')),['dead','death'],true)||in_array(mb_strtolower((string)($inventory->nature_of_exit??'')),$exitOutTerms,true)||in_array($inventory->animal_id,$exitAnimalIds,true));
         if(!isset($consolidated[$rowKey]))$consolidated[$rowKey]=['cooperative'=>$cooperativeName?:'N/A','farmer'=>$farmerName?:'N/A','animal_id'=>$identity?:'N/A','birth_date'=>$inventory->birth_date??null,'breed'=>$inventory->breed??null,'bcs'=>null,'bcs_date'=>null,'bolus_id'=>$item->bolus_id??$inventory->bolus_id??null,'calf'=>null,'breeding'=>null,'health'=>[],'other'=>[],'remarks'=>[],'availability'=>$animalOut?'Out':'Unavailable','_search'=>[]];
         $row=&$consolidated[$rowKey];if($animalOut)$row['availability']='Out';elseif($row['availability']!=='Out')$row['availability']='Available';
         $detail=$item->details??[];$detail=is_object($detail)?(array)$detail:(is_array($detail)?$detail:[]);$data=$detail['monitoring_fields']??$detail;
         if($category==='calf_monitoring'){
             $candidate=['date'=>$item->date,'sex'=>$data['sex']??null,'method'=>$item->mating_method??$data['mating_method']??$data['nm_ai']??null,'sire_id'=>$item->sire_id??$data['sire_id']??null,'calf_id'=>$item->calf_id??$data['calf_id']??($item->dam_id?$item->animal_id:null),'dam_id'=>$item->dam_id??$data['dam_id']??null,'dam_name'=>$item->dam_name??$data['dam_name']??null];
             if(!$row['calf']||($candidate['date']??'')>=($row['calf']['date']??''))$row['calf']=$candidate;
         }elseif($category==='breeding'){
             $source=json_decode((string)($detail['source_data']??''),true)?:[];$fields=$source['mating_api_fields']??$source['monitoring_fields']??[];
             $candidate=['date'=>$item->date,'pd'=>$item->pregnancy_status??$detail['pregnancy_result']??null,'estrus'=>$detail['estrus_type']??$fields['estrus_type']??null,'method'=>$detail['mating_type']??null,'sire_id'=>$item->sire_id??$detail['bull_id']??null,'sire_name'=>$item->sire_name??null];
             if(!$row['breeding']||($candidate['date']??'')>=($row['breeding']['date']??''))$row['breeding']=$candidate;
         }else{
               $serviceName=(string)($item->service??'');$serviceLines=[];
               foreach(($data['services']??[]) as $service){if(!is_array($service)||isset($service['selected'])&&!filter_var($service['selected'],FILTER_VALIDATE_BOOLEAN))continue;$name=($service['type']??'')==='Other Services'?($service['other_service_name']??'Other Services'):($service['type']??$service['other_service_name']??'');if($name!=='')$serviceLines[]=['name'=>$name,'dosage'=>$service['dosage_ml']??null];}
               if(!$serviceLines&&$serviceName!=='')foreach(preg_split('/\s*,\s*/',$serviceName) as $name)if($name!=='')$serviceLines[]=['name'=>$name,'dosage'=>null];
               foreach($serviceLines as $serviceLine){$name=$serviceLine['name'];$field=null;$target=null;if(preg_match('/blood\s*collection|\bBC\b/i',$name)){$target='health';$field='blood_collection';}elseif(preg_match('/fecalysis|\bFS\b/i',$name)){$target='health';$field='fecalysis';}elseif(preg_match('/vaccin|\bVac\b/i',$name)){$target='health';$field='vaccine';}elseif(preg_match('/de.?worm/i',$name)){$target='health';$field='deworming';}elseif(preg_match('/vitamin|vi.?supp/i',$name)){$target='health';$field='vi_supp';}elseif(preg_match('/ear.?tag/i',$name)){$target='other';$field='ear_tagging';}elseif(preg_match('/bolus/i',$name)){$target='other';$field='bolus';}elseif(preg_match('/flush/i',$name)){$target='other';$field='flushing';}if(!$field)continue;$record=['date'=>$item->date,'text'=>trim($item->date.' · '.$name.(!empty($serviceLine['dosage'])?' · '.$serviceLine['dosage'].' mL':''))];if(empty($row[$target][$field])||$record['date']>=$row[$target][$field]['date'])$row[$target][$field]=$record;}
         }
         if(!empty($item->remarks))$row['remarks'][]=(string)$item->remarks;
         foreach([$item->animal_id??null,$item->bolus_id??null,$item->service??null,$farmerName,$cooperativeName] as $term)if($term)$row['_search'][]=(string)$term;
         unset($row);
     }
       $consolidatedRows=collect(array_values($consolidated))->map(function($row){$row['remarks']=implode(' · ',array_unique($row['remarks']));$row['_searchText']=mb_strtolower(implode(' ',array_merge([$row['cooperative'],$row['farmer'],$row['animal_id'],$row['bolus_id']??''],$row['_search'])));return $row;});
      $tableSearch=trim((string)$request->query('table_search',''));$tableCooperative=trim((string)$request->query('table_cooperative',''));$tableFarmer=trim((string)$request->query('table_farmer',''));$tableDate=trim((string)$request->query('table_date',''));$tableService=trim((string)$request->query('table_service',''));
     if($tableSearch!=='')$consolidatedRows=$consolidatedRows->filter(fn($r)=>str_contains($r['_searchText'],mb_strtolower($tableSearch)));
     if($tableCooperative!=='')$consolidatedRows=$consolidatedRows->filter(fn($r)=>str_contains(mb_strtolower($r['cooperative']),mb_strtolower($tableCooperative)));
      if($tableFarmer!=='')$consolidatedRows=$consolidatedRows->filter(fn($r)=>str_contains(mb_strtolower($r['farmer']),mb_strtolower($tableFarmer)));
      if($tableDate!=='')$consolidatedRows=$consolidatedRows->filter(function($r)use($tableDate){$values=array_merge(array_filter([$r['bcs_date']??null,$r['health']['blood_collection']['date']??null,$r['health']['fecalysis']['date']??null,$r['health']['vaccine']['date']??null,$r['health']['deworming']['date']??null,$r['health']['vi_supp']['date']??null,$r['other']['ear_tagging']['date']??null,$r['other']['bolus']['date']??null,$r['other']['flushing']['date']??null,$r['calf']['date']??null,$r['breeding']['date']??null]));return collect($values)->contains(fn($v)=>str_contains((string)$v,$tableDate));});
     if($tableService!=='')$consolidatedRows=$consolidatedRows->filter(function($r)use($tableService){return match($tableService){'calf_monitoring'=>!empty($r['calf']),'breeding'=>!empty($r['breeding']),'health_nutrition'=>!empty($r['health']),'other_services'=>!empty($r['other']),default=>true};});
      $farmerOptions=$animalDirectory->map(fn($animal)=>trim(implode(' ',array_filter([$animal->first_name??null,$animal->middle_name??null,$animal->last_name??null]))))->filter()->unique()->sort()->values();$tableTotal=$consolidatedRows->count();$tablePage=max(1,(int)$request->query('table_page',1));$tablePerPage=25;$consolidatedRowsPage=new LengthAwarePaginator($consolidatedRows->forPage($tablePage,$tablePerPage)->values(),$tableTotal,$tablePerPage,$tablePage,['path'=>$request->url(),'query'=>$request->query()]);$technicianOptions=DB::table('mating_records')->whereNotNull('technician')->where('technician','<>','')->distinct()->orderBy('technician')->pluck('technician');
      return view('monitoring',compact('counts','cooperatives','reports','activeReport','calendarReports','calendarReportsByDay','selectedMonth','selectedCooperativeId','reportSearch','reportSpecification','reportAvailability','reportStatus','calendarDaysInMonth','calendarStartOffset','previousMonth','nextMonth','technicianOptions','calendarEvents','calendarEventsByDate','calendarView','selectedDate','monitoringModules','animalAvailability','consolidatedRowsPage','tableSearch','tableCooperative','tableFarmer','tableDate','tableService','categoryLabels','reportYear','farmerOptions'));
 } public function natureOfExit(Request $request){
     $search=trim((string)$request->query('search',''));
     $hasFarmerName=Schema::hasColumn('nature_of_exits','farmer_name');
     $query=DB::table('nature_of_exits as exit_record')
         ->leftJoin('cooperatives as cooperative','cooperative.id','=','exit_record.cooperative_id')
         ->leftJoin('carabaos as animal','animal.id','=','exit_record.carabao_id')
         ->leftJoin('farmers as farmer','farmer.id','=','animal.farmer_id')
         ->select('exit_record.id','exit_record.exit_date','exit_record.nature_of_exit','exit_record.remarks')
         ->selectRaw('cooperative.cooperative_name as cooperative_name')
         ->selectRaw(($hasFarmerName
             ? "COALESCE(NULLIF(exit_record.farmer_name, ''), NULLIF(CONCAT_WS(' ', NULLIF(TRIM(farmer.first_name), ''), NULLIF(TRIM(farmer.middle_name), ''), NULLIF(TRIM(farmer.last_name), '')), ''))"
             : "CONCAT_WS(' ', NULLIF(TRIM(farmer.first_name), ''), NULLIF(TRIM(farmer.middle_name), ''), NULLIF(TRIM(farmer.last_name), ''))"
         ).' as farmer_name')
         ->selectRaw("COALESCE(NULLIF(exit_record.registry_number, ''), NULLIF(animal.registry_number, '')) as registry_number");
     if($search!=='')$query->where(function($builder)use($search,$hasFarmerName){
         $like='%'.$search.'%';
         $builder->where('cooperative.cooperative_name','like',$like)
             ->orWhereRaw("CONCAT_WS(' ', NULLIF(TRIM(farmer.first_name), ''), NULLIF(TRIM(farmer.middle_name), ''), NULLIF(TRIM(farmer.last_name), '')) LIKE ?",[$like])
             ->orWhere('exit_record.registry_number','like',$like)
             ->orWhere('animal.registry_number','like',$like)
             ->orWhere('exit_record.nature_of_exit','like',$like)
             ->orWhere('exit_record.remarks','like',$like);
         if($hasFarmerName)$builder->orWhere('exit_record.farmer_name','like',$like);
     });
     $rows=$query->orderByDesc('exit_record.exit_date')->orderByDesc('exit_record.id')->paginate(25)->withQueryString();
     $rows->getCollection()->transform(function($row){
         if(!empty($row->exit_date))$row->exit_date=Carbon::parse($row->exit_date)->format('F j, Y');
         return $row;
     });
     $summaryCards=DB::table('nature_of_exits')
         ->select('nature_of_exit as nature')
         ->selectRaw('COUNT(*) as total')
         ->groupBy('nature_of_exit')
         ->orderByDesc('total')
         ->get();
     return view('nature-of-exit-records',compact('rows','search','summaryCards'));
 }
 private function milkRecordsQuery(){
     return DB::table('milk_records as record')
         ->leftJoin('carabaos as animal','animal.id','=','record.carabao_id')
         ->leftJoin('farmers as farmer','farmer.id','=',DB::raw('COALESCE(record.farmer_id, animal.farmer_id)'))
         ->leftJoin('cooperatives as cooperative','cooperative.id','=',DB::raw('COALESCE(record.cooperative_id, farmer.cooperative_id, animal.cooperative_id)'))
         ->select(
             'record.id','record.record_date','record.collection_period','record.total_liters',
             'record.number_of_cows','record.price_per_kg','record.milk_traded',
             'record.source_data'
         )
         ->selectRaw("COALESCE(NULLIF(record.farmer_name, ''), NULLIF(TRIM(CONCAT_WS(' ', NULLIF(TRIM(farmer.first_name), ''), NULLIF(TRIM(farmer.middle_name), ''), NULLIF(TRIM(farmer.last_name), ''))), '')) as farmer_name")
         ->addSelect(DB::raw("COALESCE(NULLIF(cooperative.cooperative_name, ''), '') as cooperative_name_display"));
 }

 private function decorateMilkRecord($row){
     if(empty($row->cooperative_name_display)){
         $source=is_string($row->source_data??null)?json_decode($row->source_data,true):[];
         foreach(['_cooperative_source_label','Name of Coop/Association','cooperative_name','coop_name','association_name'] as $key){
             if(is_array($source)&&!empty($source[$key])){
                 $row->cooperative_name_display=trim((string)$source[$key]);
                 break;
             }
         }
     }
     unset($row->source_data);
     return $row;
 }

 public function index(\Illuminate\Http\Request $request, $module){
     $table=$this->table($module);$allColumns=Schema::getColumnListing($table);$columns=$module==='bcs'?['cooperative_name','farmer_name','record_date','body_condition_score']:collect($allColumns)->reject(fn($column)=>in_array($column,['id','created_at','updated_at']))->values()->all();$search=trim((string)$request->query('search',$request->query('q','')));
     if($module==='health'){
         $columns=['monitoring_date','registry_id','tag_number','cooperative_name','breed','services','dosage_ml','no_of_visits_monitored','no_of_treatments','remarks','actions'];
         $query=DB::table('health_records')
             ->leftJoin('carabaos as inventory_animal','inventory_animal.id','=','health_records.carabao_id')
             ->leftJoin('farmers as animal_farmer','animal_farmer.id','=','inventory_animal.farmer_id')
             ->leftJoin('cooperatives as animal_cooperative','animal_cooperative.id','=',DB::raw('COALESCE(inventory_animal.cooperative_id, animal_farmer.cooperative_id)'))
             ->select('health_records.id','health_records.carabao_id','health_records.monitoring_date','health_records.remarks','health_records.source_data','inventory_animal.tag_number','inventory_animal.breed')
             ->addSelect('animal_cooperative.cooperative_name as cooperative_name')
             ->selectRaw("COALESCE(NULLIF(inventory_animal.registry_number, ''), NULLIF(health_records.registry_number, ''), NULLIF(inventory_animal.animal_id, '')) as registry_id");
         if($search!=='')$query->where(function($builder)use($search){$builder->orWhere('health_records.monitoring_date','like','%'.$search.'%')->orWhere('health_records.remarks','like','%'.$search.'%')->orWhere('inventory_animal.tag_number','like','%'.$search.'%')->orWhere('inventory_animal.breed','like','%'.$search.'%')->orWhere('animal_cooperative.cooperative_name','like','%'.$search.'%')->orWhereRaw("COALESCE(NULLIF(inventory_animal.registry_number, ''), NULLIF(health_records.registry_number, ''), NULLIF(inventory_animal.animal_id, '')) LIKE ?",['%'.$search.'%'])->orWhere('health_records.source_data','like','%'.$search.'%');});
         $rows=$query->orderByDesc('health_records.id')->paginate(25)->withQueryString();
         $rows->getCollection()->transform(function($row){$source=$this->decodeHealthSource($row->source_data??null);$row->services=$this->healthServicesArray($source);$row->no_of_visits_monitored=$this->healthActivityCount($source,'no_of_visits_monitored');$row->no_of_treatments=$this->healthActivityCount($source,'no_of_treatments');$row->monitoring_date=$row->monitoring_date?Carbon::parse($row->monitoring_date)->format('F j, Y'):'-';unset($row->source_data);return $row;});
         $healthSummary=$this->healthSummary();
         $healthAnimals=DB::table('carabaos as animal')->leftJoin('farmers as farmer','farmer.id','=','animal.farmer_id')->leftJoin('cooperatives as cooperative','cooperative.id','=',DB::raw('COALESCE(animal.cooperative_id, farmer.cooperative_id)'))->orderBy('animal.registry_number')->orderBy('animal.animal_id')->get(['animal.id','animal.registry_number','animal.animal_id','animal.tag_number','animal.breed','cooperative.cooperative_name'])->map(fn($animal)=>['id'=>$animal->id,'registry_id'=>$animal->registry_number?:$animal->animal_id,'tag_number'=>$animal->tag_number,'breed'=>$animal->breed,'cooperative_name'=>$animal->cooperative_name]);
     }elseif($module==='bcs'){
         $query=DB::table('bcs_records as bcs')
             ->leftJoin('carabaos as animal','animal.id','=','bcs.carabao_id')
             ->leftJoin('farmers as farmer','farmer.id','=','animal.farmer_id')
             ->leftJoin('cooperatives as record_cooperative','record_cooperative.id','=','bcs.cooperative_id')
             ->leftJoin('cooperatives as animal_cooperative','animal_cooperative.id','=','animal.cooperative_id')
             ->leftJoin('cooperatives as farmer_cooperative','farmer_cooperative.id','=','farmer.cooperative_id')
             ->select('bcs.id','bcs.record_date','bcs.body_condition_score')
             ->selectRaw("COALESCE(NULLIF(record_cooperative.cooperative_name, ''), NULLIF(animal_cooperative.cooperative_name, ''), NULLIF(farmer_cooperative.cooperative_name, ''), 'N/A') as cooperative_name")
             ->selectRaw("COALESCE(NULLIF(TRIM(CONCAT_WS(' ', NULLIF(TRIM(farmer.first_name), ''), NULLIF(TRIM(farmer.middle_name), ''), NULLIF(TRIM(farmer.last_name), ''))), ''), 'N/A') as farmer_name");
         if($search!=='')$query->where(function($builder)use($search){$builder->where('record_cooperative.cooperative_name','like','%'.$search.'%')->orWhere('animal_cooperative.cooperative_name','like','%'.$search.'%')->orWhere('farmer_cooperative.cooperative_name','like','%'.$search.'%')->orWhereRaw("CONCAT_WS(' ', farmer.first_name, farmer.middle_name, farmer.last_name) LIKE ?",['%'.$search.'%'])->orWhere('bcs.record_date','like','%'.$search.'%')->orWhere('bcs.body_condition_score','like','%'.$search.'%')->orWhere('bcs.registry_number','like','%'.$search.'%')->orWhere('animal.animal_id','like','%'.$search.'%');});
         $rows=$query->orderByDesc('bcs.record_date')->orderByDesc('bcs.id')->paginate(25)->withQueryString();
         $rows->getCollection()->transform(function($row){$row->record_date=$row->record_date?Carbon::parse($row->record_date)->format('d-M-Y'):'-';$row->body_condition_score=$row->body_condition_score??'-';return $row;});
     }elseif($module==='milk'){
         $filters=$request->validate([
             'search'=>['nullable','string','max:255'],
             'farmer'=>['nullable','string','max:255'],
             'cooperative_id'=>['nullable','integer','exists:cooperatives,id'],
             'year'=>['nullable','digits:4'],
             'quarter'=>['nullable','in:Q1,Q2,Q3,Q4'],
             'date'=>['nullable','date_format:Y-m-d'],
             'period'=>['nullable','in:AM,PM'],
         ]);
         $columns=['cooperative_name_display','farmer_name','record_date','year_collected','collection_period','total_liters','number_of_cows','price_per_kg','milk_traded','value_total'];
         $milkYears=DB::table('milk_records')->whereNotNull('record_date')->selectRaw('YEAR(record_date) as collected_year')->distinct()->orderByDesc('collected_year')->pluck('collected_year');
         $milkFilters=[
             'search'=>$search,
             'farmer'=>(string)($filters['farmer']??''),
             'cooperative_id'=>(string)($filters['cooperative_id']??''),
             'year'=>(string)($filters['year']??''),
             'quarter'=>(string)($filters['quarter']??''),
             'date'=>(string)($filters['date']??''),
             'period'=>(string)($filters['period']??''),
         ];
         $milkCooperatives=DB::table('cooperatives')->orderBy('cooperative_name')->get(['id','cooperative_name']);
         $baseQuery=$this->milkRecordsQuery();
         if($search!=='')$baseQuery->where(function($builder)use($search){
             $like='%'.$search.'%';
             $builder->where('cooperative.cooperative_name','like',$like)
                 ->orWhere('record.farmer_name','like',$like)
                 ->orWhereRaw("CONCAT_WS(' ', farmer.first_name, farmer.middle_name, farmer.last_name) LIKE ?",[$like])
                 ->orWhere('record.source_data','like',$like);
         });
         if($milkFilters['farmer']!=='')$baseQuery->where(function($builder)use($milkFilters){
             $like='%'.$milkFilters['farmer'].'%';
             $builder->where('record.farmer_name','like',$like)
                 ->orWhereRaw("CONCAT_WS(' ', farmer.first_name, farmer.middle_name, farmer.last_name) LIKE ?",[$like]);
         });
         if($milkFilters['cooperative_id']!=='')$baseQuery->where('cooperative.id',(int)$milkFilters['cooperative_id']);
         if($milkFilters['date']!=='')$baseQuery->whereDate('record.record_date',$milkFilters['date']);
         if($milkFilters['period']!=='')$baseQuery->where('record.collection_period',$milkFilters['period']);
         $milkYearlySummaries=(clone $baseQuery)->reorder()->whereNotNull('record.record_date')->select([])->selectRaw(
             'YEAR(record.record_date) as collected_year, COALESCE(SUM(record.total_liters), 0) as total_production, COALESCE(SUM(record.milk_traded), 0) as total_traded, COALESCE(SUM(record.total_liters * record.price_per_kg), 0) as total_value'
         )->groupByRaw('YEAR(record.record_date)')->orderByDesc('collected_year')->get();
         $latestFilteredYear=$milkYearlySummaries->first()?->collected_year??$milkYears->first()??now()->year;
         $selectedYear=(int)($filters['year']??$latestFilteredYear);
         $milkFilters['year']=(string)$selectedYear;
         $quarterDefinitions=[
             'Q1'=>['number'=>1,'months'=>'January - March'],
             'Q2'=>['number'=>2,'months'=>'April - June'],
             'Q3'=>['number'=>3,'months'=>'July - September'],
             'Q4'=>['number'=>4,'months'=>'October - December'],
         ];
         $quarterRows=(clone $baseQuery)->reorder()->whereYear('record.record_date',$selectedYear)->select([])->selectRaw(
             'QUARTER(record.record_date) as quarter_number, COALESCE(SUM(record.total_liters), 0) as total_production, COALESCE(SUM(record.milk_traded), 0) as total_traded, COALESCE(SUM(record.total_liters * record.price_per_kg), 0) as total_value, COUNT(*) as record_count'
         )->groupByRaw('QUARTER(record.record_date)')->get()->keyBy('quarter_number');
         $milkQuarterSummaries=[];
         foreach($quarterDefinitions as $name=>$definition){
             $row=$quarterRows->get($definition['number']);
             $milkQuarterSummaries[]=[
                 'name'=>$name,
                 'months'=>$definition['months'],
                 'total_production'=>(float)($row->total_production??0),
                 'total_traded'=>(float)($row->total_traded??0),
                 'total_value'=>(float)($row->total_value??0),
                 'record_count'=>(int)($row->record_count??0),
                 'selected'=>$milkFilters['quarter']===$name,
             ];
         }
         $maxQuarterProduction=max(array_column($milkQuarterSummaries,'total_production'));
         foreach($milkQuarterSummaries as &$quarterSummary){
             $quarterSummary['percent']=$maxQuarterProduction>0?$quarterSummary['total_production']/$maxQuarterProduction*100:0;
         }
         unset($quarterSummary);
         $query=(clone $baseQuery)
             ->whereYear('record.record_date',$selectedYear)
             ->selectRaw('YEAR(record.record_date) as year_collected')
             ->selectRaw('CASE WHEN record.total_liters IS NULL OR record.price_per_kg IS NULL THEN NULL ELSE record.total_liters * record.price_per_kg END as value_total');
         if($milkFilters['quarter']!=='')$query->whereRaw('QUARTER(record.record_date) = ?',[(int)substr($milkFilters['quarter'],1)]);
         $summary=(clone $query)->reorder()->select([])->selectRaw(
             'COALESCE(SUM(record.total_liters), 0) as total_production, COALESCE(SUM(record.milk_traded), 0) as total_traded, COALESCE(SUM(record.total_liters * record.price_per_kg), 0) as total_value'
         )->first();
         $milkSummary=[
             'total_production'=>(float)$summary->total_production,
             'total_traded'=>(float)$summary->total_traded,
             'total_value'=>(float)$summary->total_value,
         ];
         $rows=$query->orderByDesc('record.record_date')->orderByDesc('record.id')->paginate(25)->withQueryString();
         $rows->getCollection()->transform(fn($row)=>$this->decorateMilkRecord($row));
     }elseif($module==='fhpr'){
         $columns=$this->fhprDisplayColumns();
         $records=$this->fhprRecords();
         if($search!=='')$records=$records->filter(function($row)use($columns,$search){
             foreach($columns as $column){
                 if(str_contains(Str::lower((string)data_get($row,$column,'')),Str::lower($search)))return true;
                 if($column==='date_of_birth'&&str_contains(Str::lower((string)($row->_date_of_birth_search??'')),Str::lower($search)))return true;
             }
             return false;
         })->values();
         $records->each(function($row){unset($row->_date_of_birth_search);});
         $page=LengthAwarePaginator::resolveCurrentPage();$perPage=25;$total=$records->count();
         $rows=new LengthAwarePaginator($records->slice(($page-1)*$perPage,$perPage)->values(),$total,$perPage,$page,['path'=>$request->url(),'query'=>$request->query()]);
     }else{
         $query=DB::table($table);if(in_array($module,['meat','manure'],true))$query->where('product_type',$module);if($search!=='')$query->where(function($builder)use($columns,$search){foreach($columns as $column)$builder->orWhere($column,'like','%'.$search.'%');});$rows=$query->orderByDesc('id')->paginate(25)->withQueryString();
     }
     $moduleTitle=$module==='bcs'?'Body Condition Score Records':($module==='milk'?'Milk Production Records':null);
     $bcsScores=$module==='bcs'?DB::table('bcs_records')->whereNotNull('body_condition_score')->distinct()->orderBy('body_condition_score')->pluck('body_condition_score')->map(fn($score)=>number_format((float)$score,2,'.',''))->values():collect();
     return view('modules.index',compact('module','table','rows','columns','moduleTitle','bcsScores')+($module==='health'?compact('healthSummary','healthAnimals'):[])+($module==='milk'?compact('milkSummary','milkCooperatives','milkYears','milkFilters','selectedYear','milkYearlySummaries','milkQuarterSummaries','maxQuarterProduction'):[]));
 }

 private function decodeHealthSource(?string $sourceData): array
 {
     $source=is_string($sourceData)?json_decode($sourceData,true):[];
     return is_array($source)?$source:[];
 }

 private function healthServicesArray(array $source): array
 {
     $services=$source['services']??($source['monitoring_fields']['services']??[]);
     if(!is_array($services))return [];
     return collect($services)->filter(fn($service)=>is_array($service)&&($service['selected']??true))
         ->map(function($service){$dosage=($service['dosage_ml']??'')!==''?($service['dosage_ml']??null):null;return ['type'=>trim((string)($service['type']??'')),'other_service_name'=>trim((string)($service['other_service_name']??'')),'dosage_ml'=>$dosage,'dosage_display'=>$dosage!==null?rtrim(rtrim(number_format((float)$dosage,2,'.',''),'0'),'.'):null,'remarks'=>trim((string)($service['remarks']??''))];})
         ->filter(fn($service)=>$service['type']!=='')->values()->all();
 }

 private function healthActivityCount(array $source,string $key): ?int
 {
     $fields=$source['health_activity']??$source['monitoring_fields']??$source;
     if(!array_key_exists($key,$fields)||$fields[$key]===null||$fields[$key]==='')return null;
     return max(0,(int)$fields[$key]);
 }

 private function healthSummary(): array
 {
     $records=DB::table('health_records')->get(['carabao_id','source_data']);
     $serviceLabels=['Deworming'=>'Deworming Services','Vitamins'=>'Vitamins Services','Vaccination'=>'Vaccinations','Uterine Flushing'=>'Uterine Flushing Services','Fecalysis'=>'Fecalysis Activities','Ear Tagging'=>'Ear Tagging Activities','Recruitment'=>'Recruitment Activities','Hands-on Milking'=>'Hands-on Milking Activities','Forage Demo'=>'Forage Demo Activities'];$services=array_fill_keys(array_values($serviceLabels),0);
     $visits=0;$treatments=0;
     foreach($records as $record){$source=$this->decodeHealthSource($record->source_data);$visits+=$this->healthActivityCount($source,'no_of_visits_monitored')??0;$treatments+=$this->healthActivityCount($source,'no_of_treatments')??0;foreach($this->healthServicesArray($source) as $service){$name=$service['type']==='Other Services'?$service['other_service_name']:$service['type'];if(isset($serviceLabels[$name]))$services[$serviceLabels[$name]]++;}}
     return ['animals'=>$records->pluck('carabao_id')->filter()->unique()->count(),'visits'=>$visits,'treatments'=>$treatments,'services'=>$services,'activities'=>$records->count()];
 }

 public function healthRecord(int $record)
 {
     $row=DB::table('health_records')->where('id',$record)->first();abort_unless($row,404);
     $source=$this->decodeHealthSource($row->source_data);
     return response()->json(['id'=>$row->id,'carabao_id'=>$row->carabao_id,'monitoring_date'=>$row->monitoring_date,'remarks'=>$row->remarks,'no_of_visits_monitored'=>$this->healthActivityCount($source,'no_of_visits_monitored'),'no_of_treatments'=>$this->healthActivityCount($source,'no_of_treatments'),'services'=>$this->healthServicesArray($source)]);
 }

 public function storeHealthRecord(\Illuminate\Http\Request $request)
 {
     $data=$this->validateHealthRecord($request);
     $animal=DB::table('carabaos as animal')->leftJoin('farmers as farmer','farmer.id','=','animal.farmer_id')->where('animal.id',$data['carabao_id'])->first(['animal.*','farmer.cooperative_id as farmer_cooperative_id']);abort_unless($animal,422,'Choose an animal from Carabao Inventory.');
     $id=DB::transaction(function()use($data,$animal){$fields=$this->healthRecordFields($data,null);$fields+=['carabao_id'=>$animal->id,'cooperative_id'=>$animal->cooperative_id?:$animal->farmer_cooperative_id,'registry_number'=>$animal->registry_number?:$animal->animal_id,'created_at'=>now()];return (int)DB::table('health_records')->insertGetId($fields);});
     return response()->json(['message'=>'Health and Nutrition activity saved.','id'=>$id],201);
 }

 public function updateHealthRecord(\Illuminate\Http\Request $request,int $record)
 {
     $data=$this->validateHealthRecord($request);$existing=DB::table('health_records')->where('id',$record)->first();abort_unless($existing,404);
     $animal=DB::table('carabaos as animal')->leftJoin('farmers as farmer','farmer.id','=','animal.farmer_id')->where('animal.id',$data['carabao_id'])->first(['animal.*','farmer.cooperative_id as farmer_cooperative_id']);abort_unless($animal,422,'Choose an animal from Carabao Inventory.');
     DB::transaction(function()use($data,$existing,$animal,$record){$fields=$this->healthRecordFields($data,$existing);$fields+=['carabao_id'=>$animal->id,'cooperative_id'=>$animal->cooperative_id?:$animal->farmer_cooperative_id,'registry_number'=>$animal->registry_number?:$animal->animal_id];DB::table('health_records')->where('id',$record)->update($fields);$this->syncHealthMonitoringDetail($record,$data);});
     return response()->json(['message'=>'Health and Nutrition activity updated.']);
 }

 public function deleteHealthRecord(int $record)
 {
     abort_unless(DB::table('health_records')->where('id',$record)->exists(),404);
     DB::transaction(function()use($record){DB::table('monitoring_report_details')->where('destination_module','Health & Nutrition')->where('destination_record_id',$record)->update(['destination_record_id'=>null,'monitoring_status'=>'pending','updated_at'=>now()]);DB::table('health_records')->where('id',$record)->delete();});
     return response()->json(['message'=>'Health and Nutrition activity deleted.']);
 }

 private function validateHealthRecord(\Illuminate\Http\Request $request): array
 {
     $data=$request->validate(['carabao_id'=>['required','integer','exists:carabaos,id'],'monitoring_date'=>['required','date'],'no_of_visits_monitored'=>['required','integer','min:0','max:100000'],'no_of_treatments'=>['required','integer','min:0','max:100000'],'remarks'=>['nullable','string','max:2000'],'services'=>['required','array','min:1','max:100'],'services.*.type'=>['required','string','max:100'],'services.*.dosage_ml'=>['nullable','numeric','min:0','max:100000'],'services.*.remarks'=>['nullable','string','max:2000']]);
     $data['services']=collect($data['services'])->map(fn($service)=>['type'=>trim($service['type']),'dosage_ml'=>($service['dosage_ml']??'')!==''? (string)$service['dosage_ml']:null,'remarks'=>trim((string)($service['remarks']??''))])->filter(fn($service)=>$service['type']!=='')->values()->all();
     if(!$data['services'])abort(422,'Add at least one service row or cancel the activity.');
     return $data;
 }

 private function healthRecordFields(array $data,?object $existing): array
 {
     $source=$this->decodeHealthSource($existing->source_data??null);if($existing&&$existing->source_data&&!json_decode($existing->source_data,true))$source=['original_source_data'=>$existing->source_data];$counts=['no_of_visits_monitored'=>(int)$data['no_of_visits_monitored'],'no_of_treatments'=>(int)$data['no_of_treatments']];
     $source['services']=$data['services'];$source['health_activity']=$counts;$source['services_display']=$this->healthServicesSummary(json_encode($source,JSON_UNESCAPED_UNICODE));
     if(isset($source['monitoring_fields'])&&is_array($source['monitoring_fields']))$source['monitoring_fields']=array_merge($source['monitoring_fields'],$counts,['services'=>$data['services']]);
     return ['monitoring_date'=>$data['monitoring_date'],'health_status'=>$existing->health_status??'Services Provided','remarks'=>$data['remarks']??null,'record_category'=>$existing->record_category??'health_nutrition','source_data'=>json_encode($source,JSON_UNESCAPED_UNICODE),'updated_at'=>now()];
 }

 private function syncHealthMonitoringDetail(int $record,array $data): void
 {
     $detail=DB::table('monitoring_report_details')->where('destination_module','Health & Nutrition')->where('destination_record_id',$record)->first();if(!$detail)return;
     $fields=json_decode((string)$detail->monitoring_data,true);$fields=is_array($fields)?$fields:[];$fields['services']=array_map(fn($service)=>$service+['selected'=>true],$data['services']);$fields['no_of_visits_monitored']=$data['no_of_visits_monitored'];$fields['no_of_treatments']=$data['no_of_treatments'];
     DB::table('monitoring_report_details')->where('id',$detail->id)->update(['monitoring_data'=>json_encode($fields,JSON_UNESCAPED_UNICODE),'remarks'=>$data['remarks']??null,'updated_at'=>now()]);
 }

 private function healthServicesSummary(?string $sourceData): string
 {
     $source=is_string($sourceData)?json_decode($sourceData,true):[];
     if(!is_array($source))return '';
     $services=$source['services']??[];
     if(!is_array($services))return '';
     return collect($services)->filter(fn($service)=>is_array($service)&&($service['selected']??true))
         ->map(function($service){
             $name=trim((string)($service['type']??''));
             if($name==='Other Services'&&!empty($service['other_service_name']))$name.=': '.trim((string)$service['other_service_name']);
             $details=[];
             if(isset($service['dosage_ml'])&&$service['dosage_ml']!=='')$details[]=($service['dosage_display']??(string)$service['dosage_ml']).' mL';
             if(!empty($service['remarks']))$details[]=trim((string)$service['remarks']);
             return $name.($details?' ('.implode('; ',$details).')':'');
         })->filter()->implode('; ');
 }

 private function animalAge(?string $birthDate): string
 {
     if(!$birthDate)return '—';try{$age=Carbon::parse($birthDate)->diff(Carbon::now());return $age->y.'yrs '.$age->m.'mo';}catch(\Throwable){return '—';}
 }

 private function fhprDisplayColumns(): array
 {
     $hidden=['id','created_at','updated_at','carabao_id','cooperative_id','registry_number','source_data'];
     $columns=array_values(array_unique(array_merge(
         ['animal_identifier','cooperative_name_display'],
         collect(Schema::getColumnListing('fhpr_records'))->reject(fn($column)=>in_array($column,$hidden,true))->all()
     )));
     $dateOfBirthIndex=array_search('date_of_birth',$columns,true);
     array_splice($columns,$dateOfBirthIndex===false?count($columns):$dateOfBirthIndex,0,['sex','age']);
     $columns[]='record_source';
     return array_values(array_unique($columns));
 }

 private function fhprRecords(): \Illuminate\Support\Collection
 {
     $records=DB::table('fhpr_records')
         ->leftJoin('carabaos as inventory_animal','inventory_animal.id','=','fhpr_records.carabao_id')
         ->leftJoin('farmers as inventory_farmer','inventory_farmer.id','=','inventory_animal.farmer_id')
         ->whereRaw("UPPER(TRIM(inventory_animal.sex)) IN ('F', 'FEMALE')")
         ->leftJoin('cooperatives as cooperative',function($join){
             $join->on('cooperative.id','=',DB::raw('COALESCE(inventory_animal.cooperative_id, inventory_farmer.cooperative_id, fhpr_records.cooperative_id)'));
         })
         ->select('fhpr_records.*','inventory_animal.sex as sex')
         ->selectRaw("COALESCE(NULLIF(inventory_animal.registry_number, ''), NULLIF(inventory_animal.animal_id, ''), NULLIF(fhpr_records.registry_number, ''), 'N/A') as animal_identifier")
         ->selectRaw("COALESCE(NULLIF(TRIM(cooperative.cooperative_name), ''), 'N/A') as cooperative_name_display")
         ->get();
     $records->each(function($row){
         $row->_date_of_birth_search=$row->date_of_birth??'';
         $row->age=$this->animalAge($row->date_of_birth);
         $row->record_source='FHPR Breeding';
     });
     $ordered=$records->sortByDesc('id')->values();
     $ordered->each(function($row){
         $row->tag_number=$this->displayFhprTagNumber($row->tag_number??null);
         $row->date_of_birth=$this->formatFhprDateOfBirth($row->date_of_birth??null);
         unset($row->carabao_id,$row->cooperative_id,$row->registry_number,$row->source_data);
     });
     return $ordered;
 }

 private function formatFhprDateOfBirth($dateOfBirth): string
 {
     if($dateOfBirth===null||trim((string)$dateOfBirth)==='')return '-';
     try{return Carbon::parse($dateOfBirth)->format('F j, Y');}catch(\Throwable){return '-';}
 }

 private function displayFhprTagNumber($tagNumber): string
 {
     $tagNumber=trim((string)($tagNumber??''));
     return $tagNumber===''||(is_numeric($tagNumber)&&(float)$tagNumber===0.0)?'-':$tagNumber;
 }

 public function template($module){abort_if($module==='fhpr',404);if($module==='milk'){$cols=['Cooperative Code','Cooperative Name','Farmer Name','Actual Date of Collection','AM','Total','No. of cows','Price per kg (Raw Milk)','Milk Traded'];return response()->streamDownload(function()use($cols){if(ob_get_level()>0)ob_clean();$f=fopen('php://output','w');fputcsv($f,$cols);fclose($f);},'milk_template.csv');}$cols=collect(Schema::getColumnListing($this->table($module)))->reject(fn($c)=>in_array($c,['id','created_at','updated_at'])||($module==='health'&&$c==='remarks'))->all();return response()->streamDownload(function()use($cols){$f=fopen('php://output','w');fputcsv($f,$cols);fclose($f);},$module.'_template.csv');}
 public function export($module){
     $table=$this->table($module);
     if($module==='fhpr'){$cols=$this->fhprDisplayColumns();$rows=$this->fhprRecords();$labels=['animal_identifier'=>'Registry ID / Animal ID','cooperative_name_display'=>'Cooperative Name','date_of_birth'=>'Date of Birth'];return response()->streamDownload(function()use($cols,$rows,$labels){$file=fopen('php://output','w');fputcsv($file,array_map(fn($column)=>$labels[$column]??ucwords(str_replace('_',' ',$column)),$cols));foreach($rows as $row)fputcsv($file,array_map(fn($column)=>data_get($row,$column,''),$cols));fclose($file);},'fhpr_export.csv');}
     if($module==='health')return response()->streamDownload(function(){$file=fopen('php://output','w');fwrite($file,"\xEF\xBB\xBF");fputcsv($file,['Monitoring Date','Animal Registry ID','Tag Number','Cooperative Name','Breed','Type of Service','Dosage (mL)','No. of Visits / Monitored','No. of Treatments','Service Remarks','Activity Remarks']);DB::table('health_records as record')->leftJoin('carabaos as animal','animal.id','=','record.carabao_id')->leftJoin('farmers as farmer','farmer.id','=','animal.farmer_id')->leftJoin('cooperatives as cooperative','cooperative.id','=',DB::raw('COALESCE(record.cooperative_id, animal.cooperative_id, farmer.cooperative_id)'))->select('record.*','animal.registry_number as animal_registry_number','animal.animal_id','animal.tag_number','animal.breed','cooperative.cooperative_name')->orderBy('record.id')->chunk(500,function($rows)use($file){foreach($rows as $row){$source=$this->decodeHealthSource($row->source_data);$services=$this->healthServicesArray($source);if(!$services)$services=[['type'=>'','other_service_name'=>'','dosage_ml'=>null,'remarks'=>'']];foreach($services as $index=>$service){$type=$service['type']==='Other Services'?($service['other_service_name']?:'Other Services'):$service['type'];fputcsv($file,[$row->monitoring_date?Carbon::parse($row->monitoring_date)->format('F j, Y'):'-',$row->animal_registry_number?:($row->registry_number?:$row->animal_id),$row->tag_number,$row->cooperative_name,$row->breed,$type,$service['dosage_ml']!==null&&$service['dosage_ml']!==''?$service['dosage_ml']:'-',$index===0?$this->healthActivityCount($source,'no_of_visits_monitored'):'',$index===0?$this->healthActivityCount($source,'no_of_treatments'):'',$service['remarks'],$row->remarks]);}}});fclose($file);},'health_export.csv');
     if($module==='milk')return response()->streamDownload(function(){
         if(ob_get_level()>0)ob_clean();
         $file=fopen('php://output','w');
         fwrite($file,"\xEF\xBB\xBF");
         fputcsv($file,['Cooperative Code','Cooperative Name','Farmer Name','Actual Date of Collection','Year Collected','AM / PM','Total Milk Production (kg)','Number of Cows','Price per Kilogram (₱)','Milk Traded (kg)','Value of Total Milk Production (₱)']);
         $this->milkRecordsQuery()->addSelect('cooperative.cooperative_code')->orderBy('record.id')->chunk(500,function($rows)use($file){
             foreach($rows as $row){
                 $row=$this->decorateMilkRecord($row);
                 $date=$row->record_date?Carbon::parse($row->record_date):null;
                 $value=$row->total_liters!==null&&$row->price_per_kg!==null?(float)$row->total_liters*(float)$row->price_per_kg:'';
                 fputcsv($file,[$row->cooperative_code??'',$row->cooperative_name_display??'',$row->farmer_name??'',$date?$date->format('Y-m-d'):'',$date?$date->year:'',$row->collection_period??'',$row->total_liters??'',$row->number_of_cows??'',$row->price_per_kg!==null?number_format((float)$row->price_per_kg,2,'.',''):'',$row->milk_traded??'',$value!==''?number_format((float)$value,2,'.',''):'']);
             }
         });
         fclose($file);
     },'milk_production_export.csv');
     $cols=collect(Schema::getColumnListing($table))->reject(fn($column)=>$column==='id'||($module==='health'&&in_array($column,['source_data','remarks'])))->values()->all();
     if($module==='health')$cols[]='services';
     return response()->streamDownload(function()use($table,$cols,$module){$file=fopen('php://output','w');fputcsv($file,$cols);$select=$module==='health'?array_merge(array_diff($cols,['services']),['source_data']):$cols;DB::table($table)->select($select)->orderBy('id')->chunk(500,function($rows)use($file,$cols,$module){foreach($rows as $row){$values=(array)$row;if($module==='health')$values['services']=$this->healthServicesSummary($values['source_data']??null);fputcsv($file,array_map(fn($column)=>$values[$column]??'',$cols));}});fclose($file);},$module.'_export.csv');
 }
 private function importMilkCsv(Request $request){
     $request->validate(['file'=>'required|file|mimes:csv,txt|max:10240']);
     $file=fopen($request->file('file')->getRealPath(),'r');
     $headers=fgetcsv($file);
     if(!$headers){fclose($file);abort(422,'CSV header is missing.');}
     $headers[0]=preg_replace('/^\xEF\xBB\xBF/','',(string)$headers[0]);
     $normalize=fn($value)=>trim(preg_replace('/[^a-z0-9]+/','_',Str::lower(Str::ascii(trim((string)$value)))),'_');
     $aliases=[
         'herd_code'=>'cooperative_code','coop_code'=>'cooperative_code',
         'name_of_coop_association'=>'cooperative_name','name_of_cooperative'=>'cooperative_name','coop_name'=>'cooperative_name',
         'name_of_farmer'=>'farmer_name','farmer'=>'farmer_name',
         'actual_date_of_collection'=>'record_date','actual_collection_date'=>'record_date',
         'am'=>'collection_period','pm'=>'collection_period','am_pm'=>'collection_period','collection_period_am'=>'collection_period',
         'total'=>'total_liters','total_milk_production'=>'total_liters','total_milk_production_kg'=>'total_liters','milk_production'=>'total_liters',
         'no_of_cows'=>'number_of_cows','price_per_kg_raw_milk'=>'price_per_kg','price_per_kilogram'=>'price_per_kg',
         'milk_traded_kg'=>'milk_traded',
     ];
     $mapped=[];
     foreach($headers as $index=>$header){
         $name=$normalize($header);
         if(str_starts_with($name,'value_of_total_milk_production'))continue;
         $mapped[$index]=$aliases[$name]??$name;
     }
     $required=['cooperative_name','farmer_name','record_date','collection_period','total_liters','number_of_cows','price_per_kg','milk_traded'];
     foreach($required as $field){
         if(!in_array($field,$mapped,true)&&!($field==='cooperative_name'&&in_array('cooperative_code',$mapped,true))){
             fclose($file);
             throw \Illuminate\Validation\ValidationException::withMessages(['file'=>"The CSV is missing the required {$field} column."]);
         }
     }
     $cooperatives=DB::table('cooperatives')->get(['id','cooperative_name','cooperative_code']);
     $normalText=fn($value)=>trim(preg_replace('/\s+/',' ',Str::lower(Str::ascii(trim((string)$value)))));
     $parseNumber=function($value,$integer=false){
         $value=preg_replace('/[,\s₱]/u','',trim((string)$value));
         if($value===''||!is_numeric($value)||(float)$value<0)return null;
         if($integer&&floor((float)$value)!==(float)$value)return null;
         return $integer?(int)$value:(string)$value;
     };
     $parsePeriod=function($value){
         $period=Str::upper(trim((string)$value));
         if(in_array($period,['AM','1','MORNING'],true))return 'AM';
         if(in_array($period,['PM','2','AFTERNOON','EVENING'],true))return 'PM';
         return null;
     };
     $errors=[];$records=[];$farmersByCooperative=[];$line=1;
     while(($row=fgetcsv($file))!==false){
         $line++;
         if(count(array_filter($row,fn($value)=>trim((string)$value)!==''))===0)continue;
         if(count($row)!==count($headers)){$errors[]="Row {$line}: column count does not match the header.";continue;}
         $source=[];$data=[];
         foreach($mapped as $index=>$field){
             $value=trim((string)($row[$index]??''));
             $source[(string)$headers[$index]]=$value;
             $data[$field]=$value;
         }
         $cooperativeName=trim((string)($data['cooperative_name']??''));
         $cooperativeCode=trim((string)($data['cooperative_code']??''));
         $matches=$cooperatives->filter(function($cooperative)use($cooperativeName,$cooperativeCode,$normalText){
             $codeMatch=$cooperativeCode!==''&&$normalText($cooperative->cooperative_code)===$normalText($cooperativeCode);
             $nameMatch=$cooperativeName!==''&&$normalText($cooperative->cooperative_name)===$normalText($cooperativeName);
             return ($cooperativeCode===''||$codeMatch)&&($cooperativeName===''||$nameMatch);
         })->values();
         if(($cooperativeName===''&&$cooperativeCode==='')||$matches->count()!==1){
             $errors[]="Row {$line}: cooperative name/code is missing, unmatched, or ambiguous.";
             continue;
         }
         $dateValue=trim((string)($data['record_date']??''));
         try{
             if($dateValue==='')throw new \RuntimeException('Missing actual collection date.');
             if(is_numeric($dateValue)){
                 if((float)$dateValue<20000||(float)$dateValue>80000)throw new \RuntimeException('Invalid Excel date serial.');
                 $date=\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$dateValue)->format('Y-m-d');
             }else{
                 $date=Carbon::parse($dateValue)->format('Y-m-d');
             }
         }catch(\Throwable){
             $errors[]="Row {$line}: actual collection date is missing or invalid.";
             continue;
         }
         $period=$parsePeriod($data['collection_period']??'');
         $total=$parseNumber($data['total_liters']??'');
         $cows=$parseNumber($data['number_of_cows']??'',true);
         $price=$parseNumber($data['price_per_kg']??'');
         $traded=$parseNumber($data['milk_traded']??'');
         $farmerName=trim((string)($data['farmer_name']??''));
         if($period===null||$total===null||$cows===null||$price===null||$traded===null||$farmerName===''){
             $errors[]="Row {$line}: farmer, AM/PM period, milk production, cow count, price, and traded quantity are required and must be valid.";
             continue;
         }
         $cooperative=$matches->first();
         $farmerId=null;
         if(!array_key_exists($cooperative->id,$farmersByCooperative)){
             $farmersByCooperative[$cooperative->id]=DB::table('farmers')->where('cooperative_id',$cooperative->id)->get(['id','first_name','middle_name','last_name']);
         }
         $farmerMatches=$farmersByCooperative[$cooperative->id];
         $farmerNeedle=$normalText(str_replace(',',' ',$farmerName));
         $farmerCandidates=$farmerMatches->filter(function($farmer)use($normalText,$farmerNeedle){
             $full=$normalText(implode(' ',array_filter([$farmer->first_name,$farmer->middle_name,$farmer->last_name])));
             $parts=explode(' ',$farmerNeedle);
             $reversed=count($parts)>1?$normalText(implode(' ',array_merge(array_slice($parts,1),[$parts[0]]))):$farmerNeedle;
             return $full===$farmerNeedle||$full===$reversed;
         });
         if($farmerCandidates->count()===1)$farmerId=(int)$farmerCandidates->first()->id;
         $records[]=[
             'cooperative_id'=>(int)$cooperative->id,'farmer_id'=>$farmerId,'farmer_name'=>$farmerName,
             'record_date'=>$date,'collection_period'=>$period,'total_liters'=>$total,
             'number_of_cows'=>$cows,'price_per_kg'=>$price,'milk_traded'=>$traded,
             'source_data'=>json_encode($source,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
         ];
     }
     fclose($file);
     if($errors!==[]) throw \Illuminate\Validation\ValidationException::withMessages(['file'=>implode(' ',$errors)]);
     if($records===[]) abort(422,'The CSV contains no milk production records.');
     DB::transaction(function()use($records){
         foreach($records as $record){
             $query=DB::table('milk_records')->where('cooperative_id',$record['cooperative_id'])
                 ->where('record_date',$record['record_date'])
                 ->where(function($builder)use($record){$builder->where('collection_period',$record['collection_period'])->orWhereNull('collection_period');})
                 ->where(function($builder)use($record){
                     $builder->where('farmer_name',$record['farmer_name']);
                     if($record['farmer_id']!==null)$builder->orWhere('farmer_id',$record['farmer_id']);
                 });
             $existing=$query->first();
             $record['updated_at']=now();
             if($existing)DB::table('milk_records')->where('id',$existing->id)->update($record);
             else{$record['created_at']=now();DB::table('milk_records')->insert($record);}
         }
     });
     return back()->with('success','Imported or updated '.count($records).' milk production record(s).');
 }

 public function import(Request $r,$module){abort_if($module==='fhpr',404);if($module==='milk')return $this->importMilkCsv($r);$t=$this->table($module);$r->validate(['file'=>'required|file|mimes:csv,txt|max:10240']);$f=fopen($r->file('file')->getRealPath(),'r');$heads=fgetcsv($f);abort_if(!$heads,422,'CSV header is missing.');$allowed=collect(Schema::getColumnListing($t))->reject(fn($c)=>in_array($c,['id','created_at','updated_at'])||($module==='health'&&$c==='remarks'))->all();$heads=array_map(fn($h)=>Str::snake(trim($h)),$heads);$n=0;DB::transaction(function()use($f,$heads,$allowed,$t,&$n){while(($row=fgetcsv($f))!==false){if(count($row)!==count($heads))continue;$d=array_intersect_key(array_combine($heads,$row),array_flip($allowed));if(!$d)continue;$d=array_filter($d,fn($v)=>$v!==null&&trim((string)$v)!=='');$d['updated_at']=now();DB::table($t)->insert($d+['created_at'=>now()]);$n++;}});fclose($f);return back()->with('success',"Imported {$n} row(s).");}
}
