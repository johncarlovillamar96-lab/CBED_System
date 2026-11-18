<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use App\Support\AnimalClassification;
class ModuleController extends Controller {
 private array $map=['farmers'=>'farmers','cooperatives'=>'cooperatives','carabaos'=>'carabaos','mating'=>'mating_records','fhpr'=>'fhpr_records','calves'=>'calves','milk'=>'milk_records','mtd'=>'mtd_records','health'=>'health_records','bcs'=>'bcs_records','nature-of-exit'=>'nature_of_exits','exits'=>'nature_of_exits','semen-bulls'=>'semen_bulls','semen-usage'=>'semen_usage','meat'=>'cooperative_product_records','manure'=>'cooperative_product_records'];
 private function table($m){abort_unless(isset($this->map[$m]),404);return $this->map[$m];}
 public function monitoring(Request $request){
     $cooperatives=DB::table('cooperatives')->orderBy('cooperative_name')->get(['id','cooperative_name','province','municipality','barangay']);
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
     $reportYear=(int)$request->query('year',2026);if($reportYear<2000||$reportYear>2100)$reportYear=2026;$yearStart=sprintf('%04d-01-01',$reportYear);$yearEnd=sprintf('%04d-12-31',$reportYear);$calendarStart='2026-01-01';$calendarEnd='2026-12-31';$fetchStart=min($yearStart,$calendarStart);$fetchEnd=max($yearEnd,$calendarEnd);$monitoringModules=['calf_monitoring'=>collect(),'breeding'=>collect(),'health_nutrition'=>collect(),'other_services'=>collect()];$calendarEvents=collect();$breedingHistoryRecords=collect();
     if($hasReportsTable){
         $yearReports=DB::table('monitoring_reports')->whereBetween('report_date',[$fetchStart,$fetchEnd])->orderBy('report_date')->get();$yearDetails=$hasDetails&&$yearReports->isNotEmpty()?DB::table('monitoring_report_details')->whereIn('monitoring_report_id',$yearReports->pluck('id'))->orderBy('id')->get()->groupBy('monitoring_report_id'):collect();
         foreach($yearReports as $report){
             $details=$yearDetails->get($report->id,collect());
             foreach($details as $detail){$data=json_decode((string)$detail->monitoring_data,true)?:[];$spec=$detail->monitoring_specification?:'other_services';$category=in_array($spec,['calf_monitoring','breeding','health_nutrition'],true)?$spec:'other_services';if(!empty($detail->destination_record_id)&&in_array($category,['calf_monitoring','breeding','health_nutrition'],true))continue;$eventDate=$data['date_of_birth']??$data['dry_off_date']??$data['breeding_date']??$data['service_date']??$data['monitoring_date']??$report->report_date;
                 $item=(object)['date'=>$eventDate,'monitoring_date'=>$report->report_date,'service'=>$spec==='calf_monitoring'?'Calf Drop':($spec==='breeding'?'Mating / Breeding':($spec==='health_nutrition'?'Health and Nutrition':($data['service_type']??'Other Services'))),'animal_id'=>$detail->registry_number?:$detail->animal_database_id??$detail->bolus_id,'bolus_id'=>$detail->bolus_id,'animal_name'=>$detail->animal_name,'farmer_name'=>$detail->farmer_name,'cooperative_name'=>$report->cooperative_name,'details'=>$data,'remarks'=>$detail->remarks,'source_id'=>$detail->id];$monitoringModules[$category]->push($item);$calendarEvents->push($item);
             }
         }
     }
     if(Schema::hasTable('calves')){$calves=DB::table('calves as calf')->leftJoin('carabaos as dam','dam.id','=','calf.dam_id')->leftJoin('carabaos as sire','sire.animal_id','=','calf.sire_id')->leftJoin('semen_bulls as sire_bull','sire_bull.bull_id','=','calf.sire_id')->leftJoin('farmers as dam_farmer','dam_farmer.id','=','dam.farmer_id')->leftJoin('cooperatives as dam_cooperative','dam_cooperative.id','=',DB::raw('COALESCE(dam.cooperative_id, dam_farmer.cooperative_id)'))->where('calf.birth_date','<=',$fetchEnd)->orderBy('calf.birth_date')->orderBy('calf.id')->get(['calf.*','dam.animal_id as dam_animal_id','dam.animal_name as dam_name','sire.animal_id as matched_sire_id','sire.animal_name as animal_sire_name','sire_bull.bull_name as bull_sire_name','dam_farmer.first_name as farmer_first_name','dam_farmer.middle_name as farmer_middle_name','dam_farmer.last_name as farmer_last_name','dam_cooperative.cooperative_name']);foreach($calves as $c){$item=(object)['date'=>$c->birth_date,'monitoring_date'=>$c->monitoring_date,'service'=>'Calf Drop','animal_id'=>$c->calf_animal_id,'animal_name'=>null,'dam_id'=>$c->dam_animal_id,'dam_name'=>$c->dam_name,'sire_id'=>$c->sire_id,'sire_name'=>$c->animal_sire_name?:$c->bull_sire_name,'farmer_name'=>trim(implode(' ',array_filter([$c->farmer_first_name,$c->farmer_middle_name,$c->farmer_last_name]))),'cooperative_name'=>$c->cooperative_name,'remarks'=>$c->remarks??$c->calf_findings??null,'birth_outcome'=>$c->birth_outcome??null,'animal_status'=>$c->animal_status??null,'details'=>$c,'source_id'=>$c->id];$monitoringModules['calf_monitoring']->push($item);if($item->date>=$calendarStart&&$item->date<=$calendarEnd)$calendarEvents->push($item);}}
     if(Schema::hasTable('mating_records')){$breedingStart=Carbon::parse($fetchStart)->subDays(330)->toDateString();$breedings=DB::table('mating_records as mr')->leftJoin('carabaos as animal','animal.id','=','mr.carabao_id')->leftJoin('farmers as farmer','farmer.id','=','animal.farmer_id')->leftJoin('cooperatives as cooperative','cooperative.id','=',DB::raw('COALESCE(mr.cooperative_id, animal.cooperative_id, farmer.cooperative_id)'))->leftJoin('semen_bulls as bull','bull.bull_id','=','mr.bull_id')->whereBetween('mr.mating_date',[$breedingStart,$fetchEnd])->orderBy('mr.mating_date')->orderBy('mr.id')->get(['mr.*','animal.animal_id','animal.animal_name','animal.bolus_id','bull.bull_name','farmer.first_name as farmer_first_name','farmer.middle_name as farmer_middle_name','farmer.last_name as farmer_last_name','cooperative.cooperative_name']);foreach($breedings as $b){$source=json_decode((string)($b->source_data??''),true)?:[];$item=(object)['date'=>$b->mating_date,'monitoring_date'=>$source['monitoring_date']??$source['record_date']??null,'service'=>'Mating / Breeding ('.$b->mating_type.')','animal_id'=>$b->animal_id?:$b->registry_number,'bolus_id'=>$b->bolus_id,'animal_name'=>$b->animal_name,'farmer_name'=>trim(implode(' ',array_filter([$b->farmer_first_name,$b->farmer_middle_name,$b->farmer_last_name]))),'cooperative_name'=>$b->cooperative_name,'sire_id'=>$b->bull_id,'sire_name'=>$b->bull_name,'pregnancy_status'=>$b->pregnancy_result,'details'=>$b,'source_id'=>$b->id];$monitoringModules['breeding']->push($item);$breedingHistoryRecords->push($item);if($item->date>=$calendarStart&&$item->date<=$calendarEnd)$calendarEvents->push($item);}}
     if(Schema::hasTable('health_records')){$health=DB::table('health_records as hr')->leftJoin('carabaos as animal','animal.id','=','hr.carabao_id')->leftJoin('farmers as farmer','farmer.id','=','animal.farmer_id')->leftJoin('cooperatives as cooperative','cooperative.id','=',DB::raw('COALESCE(hr.cooperative_id, animal.cooperative_id, farmer.cooperative_id)'))->whereBetween('hr.monitoring_date',[$fetchStart,$fetchEnd])->orderBy('hr.monitoring_date')->orderBy('hr.id')->get(['hr.*','animal.animal_id','animal.animal_name','animal.bolus_id','farmer.first_name as farmer_first_name','farmer.middle_name as farmer_middle_name','farmer.last_name as farmer_last_name','cooperative.cooperative_name']);foreach($health as $h){$source=json_decode((string)($h->source_data??''),true)?:[];$data=$source['monitoring_fields']??[];$services=$source['services']??$data['services']??[];$data['services']=$services;$data['treatment']=$h->treatment;$serviceNames=collect($services)->filter(fn($s)=>is_array($s)&&($s['selected']??true))->map(fn($s)=>($s['type']??'')==='Other Services'?($s['other_service_name']??'Other Services'):($s['type']??''))->filter()->values();foreach(['blood_collection'=>'BC - Blood Collection','fecalysis'=>'FS - Fecalysis','vaccine'=>'Vac - Vaccination','deworming'=>'Deworming','vi_supp'=>'Vitamin Supplementation','ear_tagged'=>'Ear Tagging','bolus'=>'Bolus Application','flashing'=>'Flushing'] as $field=>$label){$value=$data[$field]??$source[$field]??null;if($value!==null&&$value!==''&&!in_array(mb_strtolower((string)$value),['0','no','false','none','n/a','na'],true))$serviceNames->push($label);}$service=$serviceNames->isNotEmpty()?$serviceNames->implode(', '):($h->treatment?:$h->record_category??'Health and Nutrition');$item=(object)['date'=>$h->monitoring_date,'monitoring_date'=>$h->monitoring_date,'service'=>$service,'record_category'=>$h->record_category??null,'animal_id'=>$h->animal_id?:($h->registry_number??null),'bolus_id'=>$h->bolus_id,'animal_name'=>$h->animal_name,'farmer_name'=>trim(implode(' ',array_filter([$h->farmer_first_name,$h->farmer_middle_name,$h->farmer_last_name]))),'cooperative_name'=>$h->cooperative_name,'details'=>$data,'treatment'=>$h->treatment,'health_status'=>$h->health_status,'remarks'=>$h->remarks,'source_id'=>$h->id];$category='health_nutrition';$monitoringModules[$category]->push($item);if($item->date>=$calendarStart&&$item->date<=$calendarEnd)$calendarEvents->push($item);}}
     $calendarEvents=$calendarEvents->filter(fn($e)=>!empty($e->date)&&$e->date>=$calendarStart&&$e->date<=$calendarEnd)->sortBy('date')->values();$calendarEventsByDate=$calendarEvents->groupBy('date');
     $selectedDate=(string)$request->query('date','');if(!preg_match('/^2026-\d{2}-\d{2}$/',$selectedDate))$selectedDate='';$calendarView=in_array($request->query('view'),['day','month','year'],true)?$request->query('view'):'month';
     $inventoryAnimals=Schema::hasTable('carabaos')?DB::table('carabaos')->get():collect();$exitOutTerms=['out','dead','death','deceased','culled','sold','transferred','disposed','exited'];$exitAnimalIds=Schema::hasTable('nature_of_exits')?DB::table('nature_of_exits as exit_record')->join('carabaos as animal','animal.id','=','exit_record.carabao_id')->get(['animal.animal_id','exit_record.nature_of_exit'])->filter(fn($exit)=>preg_match('/out|dead|deceas|culled|sold|transfer|dispos|exit/i',(string)$exit->nature_of_exit))->pluck('animal_id')->all():[];$isLiveAnimal=fn($animal)=>in_array(mb_strtolower(trim((string)($animal->status??''))),['live','alive','active'],true)&&empty($animal->death_date)&&!preg_match('/out|dead|deceas|culled|sold|transfer|dispos|exit/i',(string)($animal->nature_of_exit??'').' '.(string)($animal->exit_reason??''))&&!in_array(mb_strtolower(trim((string)($animal->availability_status??$animal->availability??''))),$exitOutTerms,true)&&!in_array($animal->animal_id,$exitAnimalIds,true);$outAnimals=$inventoryAnimals->reject($isLiveAnimal);$outIds=$outAnimals->pluck('id')->all();$animalAvailability=['available'=>0,'unavailable'=>0,'out'=>$outAnimals->count()];foreach($inventoryAnimals->filter($isLiveAnimal) as $a){$aliases=array_filter([$a->animal_id??null,$a->registry_number??null,$a->bolus_id??null,$a->tag_number??null]);$hasHistory=$calendarEvents->contains(fn($e)=>in_array($e->animal_id??null,$aliases,true)||in_array($e->bolus_id??null,$aliases,true));$animalAvailability[$hasHistory?'available':'unavailable']++;}
      $animalDirectory=Schema::hasTable('carabaos')?DB::table('carabaos as animal')->leftJoin('farmers as farmer','farmer.id','=','animal.farmer_id')->leftJoin('cooperatives as cooperative','cooperative.id','=',DB::raw('COALESCE(animal.cooperative_id, farmer.cooperative_id)'))->leftJoin('carabaos as dam','dam.animal_id','=','animal.dam_id')->get(['animal.*','farmer.id as matched_farmer_id','farmer.first_name','farmer.middle_name','farmer.last_name','cooperative.id as matched_cooperative_id','cooperative.cooperative_name','cooperative.province as cooperative_province','cooperative.municipality as cooperative_municipality','cooperative.barangay as cooperative_barangay','dam.breed as dam_breed'])->filter(fn($animal)=>$isLiveAnimal($animal) && in_array(mb_strtolower(trim((string)$animal->sex)),['female','f'],true))->values():collect();
     $gipDateInput=(string)$request->query('gip_reporting_date',now()->toDateString());
     try{$gipReportingDate=$gipDateInput!==''?Carbon::parse($gipDateInput)->toDateString():sprintf('%04d-12-31',$reportYear);}catch(\Throwable){$gipReportingDate=sprintf('%04d-12-31',$reportYear);}
     $calvingDates=[];$dryOffDates=[];$pregnancyChecks=[];
     if(Schema::hasTable('calves'))foreach(DB::table('calves')->whereDate('birth_date','<=',$gipReportingDate)->where(fn($q)=>$q->whereNull('birth_outcome')->orWhere('birth_outcome','<>','Abortion'))->selectRaw('dam_id, MAX(birth_date) as last_calving_date')->groupBy('dam_id')->get() as $signal)$calvingDates[(int)$signal->dam_id]=$signal->last_calving_date;
     if(Schema::hasTable('mating_records'))foreach(DB::table('mating_records')->whereNotNull('actual_calving_date')->whereDate('actual_calving_date','<=',$gipReportingDate)->selectRaw('carabao_id, MAX(actual_calving_date) as last_calving_date')->groupBy('carabao_id')->get() as $signal)$calvingDates[(int)$signal->carabao_id]=max($calvingDates[(int)$signal->carabao_id]??'',(string)$signal->last_calving_date);
     if(Schema::hasTable('fhpr_records')&&Schema::hasColumn('fhpr_records','present_calving_date'))foreach(DB::table('fhpr_records')->whereNotNull('carabao_id')->whereNotNull('present_calving_date')->whereDate('present_calving_date','<=',$gipReportingDate)->selectRaw('carabao_id, MAX(present_calving_date) as last_calving_date')->groupBy('carabao_id')->get() as $signal)$calvingDates[(int)$signal->carabao_id]=max($calvingDates[(int)$signal->carabao_id]??'',(string)$signal->last_calving_date);
     if(Schema::hasTable('mtd_records')&&Schema::hasColumn('mtd_records','dryoff_date'))foreach(DB::table('mtd_records')->whereNotNull('dryoff_date')->whereDate('dryoff_date','<=',$gipReportingDate)->selectRaw('carabao_id, MAX(dryoff_date) as dry_off_date')->groupBy('carabao_id')->get() as $signal)if($signal->carabao_id)$dryOffDates[(int)$signal->carabao_id]=$signal->dry_off_date;
     if(Schema::hasTable('fhpr_records')&&Schema::hasColumn('fhpr_records','dry_off_date'))foreach(DB::table('fhpr_records')->whereNotNull('dry_off_date')->whereDate('dry_off_date','<=',$gipReportingDate)->selectRaw('carabao_id, MAX(dry_off_date) as dry_off_date')->groupBy('carabao_id')->get() as $signal)if($signal->carabao_id)$dryOffDates[(int)$signal->carabao_id]=max($dryOffDates[(int)$signal->carabao_id]??'',(string)$signal->dry_off_date);
     if(Schema::hasTable('mating_records'))foreach(DB::table('mating_records')->whereNotNull('pregnancy_check_date')->whereDate('pregnancy_check_date','<=',$gipReportingDate)->whereIn('pregnancy_result',['Positive','Negative','Pregnant','Not Pregnant'])->orderBy('pregnancy_check_date')->orderBy('id')->get(['carabao_id','pregnancy_check_date','pregnancy_result']) as $signal)$pregnancyChecks[(int)$signal->carabao_id]=['result'=>$signal->pregnancy_result,'date'=>$signal->pregnancy_check_date];
     $dryOffReviewDim=max(1,(int)config('cbed.dry_off_review_dim',280));
     $categoryLabels=['calf_monitoring'=>'Calf Monitoring','breeding'=>'Breeding','health_nutrition'=>'Health and Nutrition','other_services'=>'Other Services'];
     $makeRowKey=fn($identity,$farmerId,$cooperativeId)=>mb_strtolower(implode('|',[(string)$identity,(string)($farmerId??''),(string)($cooperativeId??'')]));
     $animalLookup=[];$consolidated=[];$animalRowKeys=[];$rowInventoryIds=[];$inventoryClassifications=[];
     foreach($animalDirectory as $animal){
         $farmerName=trim(implode(' ',array_filter([$animal->first_name??null,$animal->middle_name??null,$animal->last_name??null])));
         $cooperativeId=$animal->matched_cooperative_id??$animal->cooperative_id??null;$farmerId=$animal->matched_farmer_id??$animal->farmer_id??null;$identity=(string)($animal->animal_id??$animal->registry_number??$animal->id);
         $rowKey=$makeRowKey($identity,$farmerId,$cooperativeId);$rowInventoryIds[$rowKey]=(int)$animal->id;$inventoryClassifications[$rowKey]=$animal->classification??null;
         $isOut=in_array(mb_strtolower((string)($animal->status??'')),['dead','death'],true)||in_array(mb_strtolower((string)($animal->nature_of_exit??'')),$exitOutTerms,true)||in_array($animal->animal_id,$exitAnimalIds,true);
         $consolidated[$rowKey]=['cooperative'=>$animal->cooperative_name??'N/A','farmer'=>$farmerName?:'N/A','animal_id'=>$identity,'province'=>$animal->cooperative_province??$animal->province??'','municipality'=>$animal->cooperative_municipality??$animal->municipality??'','barangay'=>$animal->cooperative_barangay??$animal->barangay??'','birth_date'=>$animal->birth_date??null,'breed'=>$animal->breed??null,'dam_breed'=>$animal->dam_breed??null,'sex'=>$animal->sex??null,'bcs'=>null,'bcs_date'=>null,'bolus_id'=>$animal->bolus_id??null,'tag_number'=>$animal->tag_number??null,'calf'=>null,'breeding'=>null,'health'=>[],'other'=>[],'health_monitoring_date'=>null,'other_monitoring_date'=>null,'monitoring_date'=>null,'monitoring_date_is_visit'=>false,'remarks'=>[],'remarks_date'=>null,'availability'=>'Available','_search'=>[$identity,$farmerName,$animal->cooperative_name??'']];
         $info=['row_key'=>$rowKey,'farmer_id'=>$farmerId,'cooperative_id'=>$cooperativeId,'animal_name'=>$animal->animal_name??null,'sex'=>$animal->sex??null,'breed'=>$animal->breed??null,'farmer_name'=>$farmerName,'cooperative_name'=>$animal->cooperative_name??null,'inventory'=>$animal];
         foreach(['animal_id','registry_number','bolus_id','tag_number','id'] as $key)if(!empty($animal->{$key})){$alias=mb_strtolower((string)$animal->{$key});$animalLookup[$alias]=$info;$animalRowKeys[$alias]=$rowKey;}
     }
      if(Schema::hasTable('bcs_records')){$bcsRecords=DB::table('bcs_records')->whereNotNull('record_date')->whereNotNull('body_condition_score')->orderByDesc('record_date')->orderByDesc('id')->get(['carabao_id','registry_number','record_date','body_condition_score']);foreach($bcsRecords as $bcs){$lookup=$animalLookup[mb_strtolower((string)($bcs->carabao_id??''))]??$animalLookup[mb_strtolower((string)($bcs->registry_number??''))]??[];$rowKey=$lookup['row_key']??null;if($rowKey!==null&&isset($consolidated[$rowKey])&&$consolidated[$rowKey]['bcs']===null){$consolidated[$rowKey]['bcs']=$bcs->body_condition_score;$consolidated[$rowKey]['bcs_date']=$bcs->record_date;}}}
      foreach($breedingHistoryRecords as $history){$lookup=$animalLookup[mb_strtolower((string)($history->animal_id??''))]??$animalLookup[mb_strtolower((string)($history->bolus_id??''))]??[];$rowKey=$lookup['row_key']??null;if(!$rowKey||!isset($consolidated[$rowKey]))continue;$detail=(array)($history->details??[]);$source=json_decode((string)($detail['source_data']??''),true)?:[];$fields=$source['mating_api_fields']??$source['monitoring_fields']??[];$consolidated[$rowKey]['_breedingHistory'][]=['date'=>$history->date,'method'=>$detail['mating_type']??null,'sire_id'=>$history->sire_id??null,'sire_name'=>$history->sire_name??null,'estrus'=>$fields['estrus_type']??null,'technician'=>$detail['technician']??null];}
     foreach($monitoringModules as $category=>$items)foreach($items as $item){
         if(empty($item->date)||$item->date<$yearStart||$item->date>$yearEnd)continue;
         $isCalf=$category==='calf_monitoring';$identity=(string)($isCalf?($item->dam_id??$item->animal_id??''):($item->animal_id??$item->bolus_id??''));
         $aliases=array_filter([$identity,(string)($item->animal_id??''),(string)($item->bolus_id??'')]);$lookup=[];foreach($aliases as $alias){$lookup=$animalLookup[mb_strtolower($alias)]??[];if($lookup)break;}
         $inventory=$lookup['inventory']??null;if(!$inventory)continue;$farmerName=($item->farmer_name??null)?:($lookup['farmer_name']??'');$cooperativeName=($item->cooperative_name??null)?:($lookup['cooperative_name']??'');
         $rowKey=$lookup['row_key']??$makeRowKey($identity?:($item->source_id??'unknown'),$lookup['farmer_id']??$farmerName,$lookup['cooperative_id']??$cooperativeName);
         $animalOut=$inventory&&(in_array(mb_strtolower((string)($inventory->status??'')),['dead','death'],true)||in_array(mb_strtolower((string)($inventory->nature_of_exit??'')),$exitOutTerms,true)||in_array($inventory->animal_id,$exitAnimalIds,true));
         if(!isset($consolidated[$rowKey]))$consolidated[$rowKey]=['cooperative'=>$cooperativeName?:'N/A','farmer'=>$farmerName?:'N/A','animal_id'=>$identity?:'N/A','birth_date'=>$inventory->birth_date??null,'breed'=>$inventory->breed??null,'dam_breed'=>$inventory->dam_breed??null,'sex'=>$inventory->sex??null,'bcs'=>null,'bcs_date'=>null,'bolus_id'=>$item->bolus_id??$inventory->bolus_id??null,'calf'=>null,'breeding'=>null,'health'=>[],'other'=>[],'monitoring_date'=>null,'monitoring_date_is_visit'=>false,'remarks'=>[],'remarks_date'=>null,'availability'=>$animalOut?'Out':'Unavailable','_search'=>[]];
         $row=&$consolidated[$rowKey];if($animalOut)$row['availability']='Out';elseif($row['availability']!=='Out')$row['availability']='Available';
         $detail=$item->details??[];$detail=is_object($detail)?(array)$detail:(is_array($detail)?$detail:[]);$data=$detail['monitoring_fields']??$detail;
         $monitoringDate=$item->monitoring_date??null;
         if($monitoringDate&&(!$row['monitoring_date']||$monitoringDate>=$row['monitoring_date'])){$row['monitoring_date']=$monitoringDate;$row['monitoring_date_is_visit']=true;}
         if($category==='calf_monitoring'){
             $candidate=['date'=>$item->date,'monitoring_date'=>$item->monitoring_date??$data['monitoring_date']??null,'sex'=>$data['sex']??null,'outcome'=>$item->birth_outcome??$data['birth_outcome']??null,'method'=>$item->mating_method??$data['mating_method']??$data['nm_ai']??null,'sire_id'=>$item->sire_id??$data['sire_id']??null,'sire_name'=>$item->sire_name??$data['sire_name']??null,'calf_id'=>$item->calf_id??$data['calf_id']??($item->dam_id?$item->animal_id:null),'dam_id'=>$item->dam_id??$data['dam_id']??null,'dam_name'=>$item->dam_name??$data['dam_name']??null];
             if(!$row['calf']||($candidate['date']??'')>=($row['calf']['date']??''))$row['calf']=$candidate;
             if(!empty($candidate['monitoring_date']))$row['calf']['monitoring_date']=$candidate['monitoring_date'];
         }elseif($category==='breeding'){
             $source=json_decode((string)($detail['source_data']??''),true)?:[];$fields=$source['mating_api_fields']??$source['monitoring_fields']??[];
             $candidate=['date'=>$item->date,'monitoring_date'=>$item->monitoring_date??$fields['record_date']??null,'pd'=>$item->pregnancy_status??$detail['pregnancy_result']??$fields['pregnancy_status']??null,'pd_date'=>$detail['pregnancy_check_date']??$fields['pd_date']??null,'estrus'=>$detail['estrus_type']??$fields['estrus_type']??null,'method'=>$detail['mating_type']??$fields['method']??null,'sire_id'=>$item->sire_id??$detail['bull_id']??$fields['semen_bull_id']??null,'sire_name'=>$item->sire_name??null,'technician'=>$detail['technician']??$fields['technician']??null];
             if(!$row['breeding']||($candidate['date']??'')>=($row['breeding']['date']??''))$row['breeding']=$candidate;
         }else{
             $categoryDate=$item->monitoring_date??$item->date;if($category==='health_nutrition'&&$categoryDate)$row['health_monitoring_date']=max(array_filter([$row['health_monitoring_date'],$categoryDate]));if($category==='other_services'&&$categoryDate)$row['other_monitoring_date']=max(array_filter([$row['other_monitoring_date'],$categoryDate]));
               $serviceName=(string)($item->service??'');$serviceLines=[];
               foreach(($data['services']??[]) as $service){if(!is_array($service)||isset($service['selected'])&&!filter_var($service['selected'],FILTER_VALIDATE_BOOLEAN))continue;$isOther=($service['type']??'')==='Other Services';$name=$isOther?($service['other_service_name']??'Other Services'):($service['type']??$service['other_service_name']??'');if($name!=='')$serviceLines[]=['name'=>$name,'dosage'=>$service['dosage_ml']??null,'is_other'=>$isOther];}
             if(!$serviceLines&&$serviceName!=='')foreach(preg_split('/\s*,\s*/',$serviceName) as $name)if($name!=='')$serviceLines[]=['name'=>$name,'dosage'=>null];
               foreach($serviceLines as $serviceLine){$name=trim($serviceLine['name']);$field=null;$target=null;if(preg_match('/blood\s*collection|\bBC\b/i',$name)){$target='health';$field='blood_collection';}elseif(preg_match('/fecalysis|\bFS\b/i',$name)){$target='health';$field='fecalysis';}elseif(preg_match('/vaccin|\bVac\b/i',$name)){$target='health';$field='vaccine';}elseif(preg_match('/de.?worm/i',$name)){$target='health';$field='deworming';}elseif(preg_match('/vitamin|vi.?supp/i',$name)){$target='health';$field='vi_supp';}elseif(preg_match('/uterine.?flush|flush/i',$name)){$target='health';$field='flushing';}elseif(preg_match('/ear.?tag/i',$name)){$target='other';$field='ear_tagging';}elseif(preg_match('/bolus/i',$name)){$target='other';$field='bolus';}elseif(($serviceLine['is_other']??false)||($item->record_category??null)==='other_services'||preg_match('/recruit|milking|forage|animal visit|monitoring|other service/i',$name)){$target='other';$field='other:'.Str::slug($name);}elseif($name!==''){$target='health';$field='health:'.Str::slug($name);}if(!$field)continue;$value=!empty($serviceLine['dosage'])?$serviceLine['dosage'].' mL':$name;if(preg_match('/ear.?tag/i',$name))$value=$data['tag_number']??$data['tag_no']??$data['ear_tag_number']??'Done';elseif(preg_match('/bolus/i',$name))$value=$data['bolus_id']??'Done';$record=['date'=>$item->date,'visit_date'=>$item->monitoring_date??$item->date,'name'=>$name,'dosage'=>$serviceLine['dosage']??null,'value'=>$value,'text'=>trim($item->date.' · '.$name.(!empty($serviceLine['dosage'])?' · '.$serviceLine['dosage'].' mL':'').(!empty($item->treatment)?' · '.$item->treatment:''))];if(empty($row[$target][$field])||$record['date']>=$row[$target][$field]['date'])$row[$target][$field]=$record;}
         }
         if(!empty($item->remarks)&&(!$row['remarks_date']||($item->monitoring_date??$item->date)>=$row['remarks_date'])){$row['remarks_date']=$item->monitoring_date??$item->date;$row['remarks']=[(string)$item->remarks];}elseif(!empty($item->remarks)&&($item->monitoring_date??$item->date)===$row['remarks_date'])$row['remarks'][]=(string)$item->remarks;
         $activityDate=$item->monitoring_date??$item->date;if($activityDate&&(!$row['monitoring_date']||$activityDate>=$row['monitoring_date'])){$row['monitoring_date']=$activityDate;$row['monitoring_date_is_visit']=!empty($item->monitoring_date);}
         foreach([$item->animal_id??null,$item->bolus_id??null,$item->service??null,$farmerName,$cooperativeName] as $term)if($term)$row['_search'][]=(string)$term;
         unset($row);
     }
       foreach($consolidated as &$row){if($row['calf']){if(empty($row['calf']['method'])||empty($row['calf']['sire_id'])){$birth=Carbon::parse($row['calf']['date']);$matches=collect($row['_breedingHistory']??[])->filter(function($event)use($birth){$eventDate=Carbon::parse($event['date']);$days=$eventDate->diffInDays($birth,false);return $days>0&&$days<=330&&$days>=240;})->sortByDesc('date');$sireMatch=$matches->first(fn($event)=>!empty($row['calf']['sire_id'])&&(string)$event['sire_id']===(string)$row['calf']['sire_id']);$match=$sireMatch??$matches->first();if($match){$row['calf']['method']=$row['calf']['method']??$match['method'];$row['calf']['sire_id']=$row['calf']['sire_id']??$match['sire_id'];$row['calf']['sire_name']=$row['calf']['sire_name']??$match['sire_name']??null;}}}}unset($row);
     foreach($consolidated as $key=>&$row){$inventoryId=$rowInventoryIds[$key]??null;$pregnancyCheck=$inventoryId?($pregnancyChecks[$inventoryId]??[]):[];$classification=AnimalClassification::classify(['sex'=>$row['sex']??null,'birth_date'=>$row['birth_date']??null,'inventory_classification'=>$inventoryClassifications[$key]??null,'last_calving_date'=>$inventoryId?($calvingDates[$inventoryId]??null):null,'dry_off_date'=>$inventoryId?($dryOffDates[$inventoryId]??null):null,'pregnancy_result'=>$pregnancyCheck['result']??null,'pregnancy_date'=>$pregnancyCheck['date']??null,'as_of'=>$gipReportingDate,'dryoff_review_dim'=>$dryOffReviewDim]);$row=array_merge($row,$classification);$labels=[];$visitDate=$row['monitoring_date']??null;
      foreach(($row['health']??[]) as $entry){if(($entry['visit_date']??$entry['date']??null)!==$visitDate)continue;$name=mb_strtolower((string)($entry['name']??''));$label=$entry['name']??'';if(preg_match('/de.?worm/',$name))$label='DW';elseif(preg_match('/vitamin|mineral|vi.?supp/',$name))$label='VS';elseif(preg_match('/fecalysis|\bfs\b/',$name))$label='FS';elseif(preg_match('/vaccin/',$name))$label='Vac';elseif(preg_match('/flush/',$name))$label='UF';$labels[]=$label;}
      foreach(($row['other']??[]) as $entry){if(($entry['visit_date']??$entry['date']??null)!==$visitDate)continue;$name=mb_strtolower((string)($entry['name']??''));$label=$entry['name']??'';if(preg_match('/ear.?tag/',$name))$label='ET';elseif(preg_match('/bolus/',$name))$label='Bolus';$labels[]=$label;}
      if(($row['breeding']['monitoring_date']??$row['breeding']['date']??null)===$visitDate&&!empty($row['breeding']['method']))$labels[]=$row['breeding']['method'];
      if(($row['breeding']['monitoring_date']??$row['breeding']['date']??null)===$visitDate&&!empty($row['breeding']['pd']))$labels[]='PD';
      if(($row['calf']['monitoring_date']??$row['calf']['date']??null)===$visitDate&&!empty($row['calf']['date']))$labels[]='Calving';
      if(($row['bcs_date']??null)===$visitDate&&!empty($row['bcs']))$labels[]='BCS';
      $row['compact_services']=array_values(array_unique(array_filter($labels)));}
      $consolidatedRows=collect(array_values($consolidated))->map(function($row){$row['remarks']=implode(' · ',array_unique($row['remarks']));$row['_searchText']=mb_strtolower(implode(' ',array_merge([$row['cooperative'],$row['farmer'],$row['animal_id'],$row['bolus_id']??'',$row['province']??'',$row['municipality']??''],$row['_search'])));return $row;});
      $tableSearch=trim((string)$request->query('table_search',''));$tableCooperative=trim((string)$request->query('table_cooperative',''));$tableFarmer=trim((string)$request->query('table_farmer',''));$tableAnimal=trim((string)$request->query('table_animal',''));$tableProvince=trim((string)$request->query('table_province',''));$tableMunicipality=trim((string)$request->query('table_municipality',''));$tableBarangay=trim((string)$request->query('table_barangay',''));$tableDateStart=trim((string)$request->query('table_date_start',''));$tableDateEnd=trim((string)$request->query('table_date_end',''));$tableService=trim((string)$request->query('table_service',''));
     if($tableSearch!=='')$consolidatedRows=$consolidatedRows->filter(fn($r)=>str_contains($r['_searchText'],mb_strtolower($tableSearch)));
     if($tableCooperative!=='')$consolidatedRows=$consolidatedRows->filter(fn($r)=>str_contains(mb_strtolower($r['cooperative']),mb_strtolower($tableCooperative)));
      if($tableFarmer!=='')$consolidatedRows=$consolidatedRows->filter(fn($r)=>str_contains(mb_strtolower($r['farmer']),mb_strtolower($tableFarmer)));
      if($tableAnimal!=='')$consolidatedRows=$consolidatedRows->filter(fn($r)=>str_contains(mb_strtolower((string)$r['animal_id']),mb_strtolower($tableAnimal)));
      if($tableProvince!=='')$consolidatedRows=$consolidatedRows->filter(fn($r)=>str_contains(mb_strtolower((string)$r['province']),mb_strtolower($tableProvince)));
      if($tableMunicipality!=='')$consolidatedRows=$consolidatedRows->filter(fn($r)=>str_contains(mb_strtolower((string)$r['municipality']),mb_strtolower($tableMunicipality)));
      if($tableBarangay!=='')$consolidatedRows=$consolidatedRows->filter(fn($r)=>str_contains(mb_strtolower((string)$r['barangay']),mb_strtolower($tableBarangay)));

$gipRows=collect(array_values($consolidated));
if($tableCooperative!=='')$gipRows=$gipRows->filter(fn($r)=>str_contains(mb_strtolower($r['cooperative']),mb_strtolower($tableCooperative)));
if($tableProvince!=='')$gipRows=$gipRows->filter(fn($r)=>str_contains(mb_strtolower((string)$r['province']),mb_strtolower($tableProvince)));
if($tableMunicipality!=='')$gipRows=$gipRows->filter(fn($r)=>str_contains(mb_strtolower((string)$r['municipality']),mb_strtolower($tableMunicipality)));
$gipClassCounts=$gipRows->countBy('classification');
$gipSummary=['CowDryPregnant'=>(int)$gipClassCounts->get('CowDryPregnant',0),'CowLactatingPregnant'=>(int)$gipClassCounts->get('CowLactatingPregnant',0),'HeiferPregnant'=>(int)$gipClassCounts->get('HeiferPregnant',0),'Total Pregnant'=>(int)$gipClassCounts->get('CowDryPregnant',0)+(int)$gipClassCounts->get('CowLactatingPregnant',0)+(int)$gipClassCounts->get('HeiferPregnant',0),'CowDry'=>(int)$gipClassCounts->get('CowDry',0),'CowLactating'=>(int)$gipClassCounts->get('CowLactating',0),'Heifer'=>(int)$gipClassCounts->get('Heifer',0),'Total Breeding-age Female'=>0,'Total Live Female'=>$gipRows->count()];
$gipSummary['Total Breeding-age Female']=$gipSummary['CowDryPregnant']+$gipSummary['CowLactatingPregnant']+$gipSummary['HeiferPregnant']+$gipSummary['CowDry']+$gipSummary['CowLactating']+$gipSummary['Heifer'];
if($tableDateStart!==''||$tableDateEnd!=='')$consolidatedRows=$consolidatedRows->filter(function($r)use($tableDateStart,$tableDateEnd){$values=array_filter(array_merge([$r['monitoring_date']??null,$r['bcs_date']??null,$r['calf']['date']??null,$r['calf']['monitoring_date']??null,$r['breeding']['date']??null,$r['breeding']['monitoring_date']??null,$r['health_monitoring_date']??null,$r['other_monitoring_date']??null],collect($r['health'])->pluck('date')->all(),collect($r['other'])->pluck('date')->all()));return collect($values)->contains(fn($v)=>($tableDateStart===''||(string)$v>=$tableDateStart)&&($tableDateEnd===''||(string)$v<=$tableDateEnd));});
     if($tableService!=='')$consolidatedRows=$consolidatedRows->filter(function($r)use($tableService){return match($tableService){'calf_monitoring'=>!empty($r['calf']),'breeding'=>!empty($r['breeding']),'health_nutrition'=>!empty($r['health']),'other_services'=>!empty($r['other']),default=>true};});
      if($request->query('table_export')==='xlsx'){$exportRows=$consolidatedRows->values();$spreadsheet=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$sheet=$spreadsheet->getActiveSheet();$sheet->setTitle('Animal Monitoring');$sheet->fromArray(['No.','Name of Farmer'],null,'A1');$sheet->setCellValue('C1','Animal Information');$sheet->mergeCells('C1:F1');$sheet->setCellValue('G1','Calf Monitoring');$sheet->mergeCells('G1:K1');$sheet->setCellValue('L1','Breeding');$sheet->mergeCells('L1:Q1');$sheet->setCellValue('R1','Health and Nutrition');$sheet->mergeCells('R1:X1');$sheet->setCellValue('Y1','Other Services');$sheet->mergeCells('Y1:AB1');$sheet->fromArray(['Latest Monitoring Date','Remarks'],null,'AC1');$sheet->mergeCells('A1:A2');$sheet->mergeCells('B1:B2');$sheet->mergeCells('AC1:AC2');$sheet->mergeCells('AD1:AD2');$headers=['Animal ID','Date of Birth','Dam Breed','BCS','Last Calving','Calf Sex','Breeding Method','Sire ID','Monitoring Date','PD','Type of Estrus','Breeding Method','Sire ID','Breeding Date','Monitoring Date','BCS','Fecalysis (FS)','Vaccination','Deworming','Vitamins','Uterine Flushing','Monitoring Date','Ear Tagging','Bolus','Other Service','Monitoring Date'];$sheet->fromArray($headers,null,'C2');$date=fn($value)=>$value?Carbon::parse($value)->format('F j, Y'):'–';foreach($exportRows as $index=>$row){$health=$row['health'];$other=$row['other'];$otherList=collect($other)->reject(fn($value,$key)=>in_array($key,['ear_tagging','bolus'],true))->pluck('name')->unique()->implode(', ');$values=[$index+1,$row['farmer'],$row['animal_id'],$date($row['birth_date']),$row['dam_breed']?:'–',$row['bcs']??'–',$date($row['calf']['date']??null),$row['calf']['sex']??'–',$row['calf']['method']??'–',$row['calf']['sire_id']??'–',$date($row['calf']['monitoring_date']??null),$row['breeding']['pd']??'–',$row['breeding']['estrus']??'–',$row['breeding']['method']??'–',$row['breeding']['sire_id']??'–',$date($row['breeding']['date']??null),$date($row['breeding']['monitoring_date']??null),$health['bcs']['value']??($row['bcs']??'–'),$health['fecalysis']['value']??'–',$health['vaccine']['value']??'–',$health['deworming']['value']??'–',$health['vi_supp']['value']??'–',$health['flushing']['value']??'–',$date($row['health_monitoring_date']),$other['ear_tagging']['value']??($row['tag_number']?:'–'),$other['bolus']['value']??($row['bolus_id']?:'–'),$otherList?:'–',$date($row['other_monitoring_date']),$date($row['monitoring_date']),$row['remarks']?:'–'];$sheet->fromArray($values,null,'A'.(3+$index));}$lastRow=max(3,2+$exportRows->count());$sheet->getStyle('A1:AD'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);$sheet->getStyle('A1:AD2')->getFont()->setBold(true);$sheet->getStyle('A1:AD2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);$sheet->getStyle('A1:AD2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);$sheet->getStyle('A1:AD'.$lastRow)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);foreach(range('A','Z') as $column)$sheet->getColumnDimension($column)->setWidth(in_array($column,['A'],true)?6:14);foreach(['AA','AB','AC','AD'] as $column)$sheet->getColumnDimension($column)->setWidth(16);$sheet->freezePane('C3');$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LEGAL)->setFitToWidth(1)->setFitToHeight(0);$sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1,2);return response()->streamDownload(function()use($spreadsheet){(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');},'animal-monitoring-'.$reportYear.'.xlsx',['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);}
      if($request->query('table_export')==='csv'){$exportRows=$consolidatedRows->values();return response()->streamDownload(function()use($exportRows){$file=fopen('php://output','w');fwrite($file,"\xEF\xBB\xBF");fputcsv($file,['No.','Name of Farmer','Animal ID','Date of Birth','Dam Breed','BCS','Last Calving','Calf Sex','Calf Breeding Method','Calf Sire ID','Calf Monitoring Date','PD','Type of Estrus','Breeding Method','Breeding Sire ID','Breeding Date','Breeding Monitoring Date','Health BCS','Fecalysis (FS)','Vaccination','Deworming','Vitamins','Uterine Flushing','Health Monitoring Date','Ear Tagging','Bolus','Other Service','Other Monitoring Date','Latest Monitoring Date','Remarks']);$date=fn($value)=>$value?Carbon::parse($value)->format('F j, Y'):'–';foreach($exportRows as $index=>$row){$health=$row['health'];$other=$row['other'];$otherList=collect($other)->reject(fn($value,$key)=>in_array($key,['ear_tagging','bolus'],true))->pluck('name')->unique()->implode(', ');fputcsv($file,[$index+1,$row['farmer'],$row['animal_id'],$date($row['birth_date']),$row['dam_breed']?:'–',$row['bcs']??'–',$date($row['calf']['date']??null),$row['calf']['sex']??'–',$row['calf']['method']??'–',$row['calf']['sire_id']??'–',$date($row['calf']['monitoring_date']??null),$row['breeding']['pd']??'–',$row['breeding']['estrus']??'–',$row['breeding']['method']??'–',$row['breeding']['sire_id']??'–',$date($row['breeding']['date']??null),$date($row['breeding']['monitoring_date']??null),$health['bcs']['value']??($row['bcs']??'–'),$health['fecalysis']['value']??'–',$health['vaccine']['value']??'–',$health['deworming']['value']??'–',$health['vi_supp']['value']??'–',$health['flushing']['value']??'–',$date($row['health_monitoring_date']),$other['ear_tagging']['value']??($row['tag_number']?:'–'),$other['bolus']['value']??($row['bolus_id']?:'–'),$otherList?:'–',$date($row['other_monitoring_date']),$date($row['monitoring_date']),$row['remarks']?:'–']);}fclose($file);},'animal-monitoring-'.$reportYear.'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);}
      $farmerOptions=$animalDirectory->map(fn($animal)=>trim(implode(' ',array_filter([$animal->first_name??null,$animal->middle_name??null,$animal->last_name??null]))))->filter()->unique()->sort()->values();$tableTotal=$consolidatedRows->count();$tablePerPage=(int)$request->query('table_per_page',25);if(!in_array($tablePerPage,[10,25,50,100],true))$tablePerPage=25;$tablePage=max(1,(int)$request->query('table_page',1));$tablePage=min($tablePage,max(1,(int)ceil($tableTotal/$tablePerPage)));$consolidatedRowsPage=new LengthAwarePaginator($consolidatedRows->forPage($tablePage,$tablePerPage)->values(),$tableTotal,$tablePerPage,$tablePage,['path'=>$request->url(),'query'=>$request->query(),'pageName'=>'table_page']);$liveAnimalTotal=$animalDirectory->count();$technicianOptions=DB::table('mating_records')->whereNotNull('technician')->where('technician','<>','')->distinct()->orderBy('technician')->pluck('technician');
      return view('monitoring',compact('counts','cooperatives','reports','activeReport','calendarReports','calendarReportsByDay','selectedMonth','selectedCooperativeId','reportSearch','reportSpecification','reportAvailability','reportStatus','calendarDaysInMonth','calendarStartOffset','previousMonth','nextMonth','technicianOptions','calendarEvents','calendarEventsByDate','calendarView','selectedDate','monitoringModules','animalAvailability','consolidatedRows','consolidatedRowsPage','tableSearch','tableCooperative','tableFarmer','tableAnimal','tableProvince','tableMunicipality','tableBarangay','tableDateStart','tableDateEnd','tableService','categoryLabels','reportYear','farmerOptions','tablePerPage','liveAnimalTotal','gipSummary','gipReportingDate','dryOffReviewDim'));
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
     if (in_array($module, ['exits', 'nature-of-exit'], true)) return app(AnimalExitController::class)->index($request);
     if ($module === 'mtd') return app(MtdController::class)->index($request);
     $table=$this->table($module);$allColumns=Schema::getColumnListing($table);$columns=$module==='bcs'?['cooperative_name','farmer_name','record_date','body_condition_score']:collect($allColumns)->reject(fn($column)=>in_array($column,['id','created_at','updated_at']))->values()->all();$search=trim((string)$request->query('search',$request->query('q','')));
     if($module==='health'){
         $columns=['cooperative_name','farmer_name','registry_id','tag_number','breed','monitoring_date','services','dosage_ml','deworming','vitamins_minerals','vaccination','uterine_flushing','ear_tagging','no_of_visits_monitored','no_of_treatments','remarks','actions'];
         $farmerName = "CONCAT_WS(' ', NULLIF(TRIM(animal_farmer.first_name), ''), NULLIF(TRIM(animal_farmer.middle_name), ''), NULLIF(TRIM(animal_farmer.last_name), ''))";
         $registryId = "COALESCE(NULLIF(inventory_animal.registry_number, ''), NULLIF(health_records.registry_number, ''), NULLIF(inventory_animal.animal_id, ''))";
         $query=DB::table('health_records')
             ->leftJoin('carabaos as inventory_animal','inventory_animal.id','=','health_records.carabao_id')
             ->leftJoin('farmers as animal_farmer','animal_farmer.id','=','inventory_animal.farmer_id')
             ->leftJoin('cooperatives as animal_cooperative','animal_cooperative.id','=',DB::raw('COALESCE(inventory_animal.cooperative_id, animal_farmer.cooperative_id)'))
             ->select('health_records.id','health_records.carabao_id','health_records.monitoring_date','health_records.remarks','health_records.source_data','inventory_animal.tag_number','inventory_animal.breed')
             ->addSelect('animal_cooperative.cooperative_name as cooperative_name')
             ->selectRaw("$farmerName as farmer_name, $registryId as registry_id");
         $healthFilters = $request->validate([
             'cooperative_id' => ['nullable', 'integer', 'min:1'],
             'breed' => ['nullable', 'string', 'max:255'],
             'date_from' => ['nullable', 'date_format:Y-m-d'],
             'date_to' => ['nullable', 'date_format:Y-m-d'],
         ]);
         $healthCooperatives = (clone $query)->select('animal_cooperative.id', 'animal_cooperative.cooperative_name')
             ->whereNotNull('animal_cooperative.id')->distinct()->orderBy('animal_cooperative.cooperative_name')->get();
         $healthBreeds = (clone $query)->select('inventory_animal.breed')->whereNotNull('inventory_animal.breed')
             ->where('inventory_animal.breed', '!=', '')->distinct()->orderBy('inventory_animal.breed')->pluck('inventory_animal.breed');
         $query->when($healthFilters['cooperative_id'] ?? null, fn ($builder, $id) => $builder->where('animal_cooperative.id', $id))
             ->when($healthFilters['breed'] ?? null, fn ($builder, $breed) => $builder->where('inventory_animal.breed', $breed))
             ->when($healthFilters['date_from'] ?? null, fn ($builder, $date) => $builder->where('health_records.monitoring_date', '>=', $date))
             ->when($healthFilters['date_to'] ?? null, fn ($builder, $date) => $builder->where('health_records.monitoring_date', '<=', $date));
         if ($search !== '') {
             $pattern = '%'.$search.'%';
             $query->where(function ($builder) use ($pattern, $farmerName, $registryId) {
                 $builder->where('health_records.monitoring_date', 'like', $pattern)
                     ->orWhere('health_records.remarks', 'like', $pattern)
                     ->orWhere('inventory_animal.tag_number', 'like', $pattern)
                     ->orWhere('inventory_animal.breed', 'like', $pattern)
                     ->orWhere('animal_cooperative.cooperative_name', 'like', $pattern)
                     ->orWhereRaw("$farmerName LIKE ?", [$pattern])
                     ->orWhereRaw("$registryId LIKE ?", [$pattern])
                     ->orWhere('health_records.source_data', 'like', $pattern);
             });
         }
         $requestedPageSize = $request->query('per_page');
         $perPage = in_array($requestedPageSize, ['10', '15', '25', '50', '100'], true) ? (int) $requestedPageSize : 15;
         $rows=$query->orderByDesc('health_records.id')->paginate($perPage)->withQueryString();
         $rows->getCollection()->transform(function ($row) {
             $source = $this->decodeHealthSource($row->source_data ?? null);
             $worksheet = \App\Support\HealthWorksheetMapping::fromSource($source);
             $row->worksheet_services = \App\Support\HealthWorksheetMapping::selectedServices($worksheet);
             $row->worksheet_dosage = $worksheet['dosage'] ?? null;
             foreach (['deworming', 'vitamins_minerals', 'vaccination', 'uterine_flushing', 'ear_tagging'] as $field) {
                 $row->$field = $worksheet[$field] ?? null;
             }
             $row->remarks = $row->remarks ?? ($worksheet['remarks'] ?? null);
             $row->services = $this->healthServicesArray($source);
             $row->no_of_visits_monitored = $this->healthActivityCount($source, 'no_of_visits_monitored');
             $row->no_of_treatments = $this->healthActivityCount($source, 'no_of_treatments');
             $row->monitoring_date = $row->monitoring_date ? Carbon::parse($row->monitoring_date)->format('F j, Y') : '-';
             unset($row->source_data);

             return $row;
         });
         $healthSummary=$this->healthSummary();
         $healthAnimals=DB::table('carabaos as animal')->leftJoin('farmers as farmer','farmer.id','=','animal.farmer_id')->leftJoin('cooperatives as cooperative','cooperative.id','=',DB::raw('COALESCE(animal.cooperative_id, farmer.cooperative_id)'))->orderBy('animal.registry_number')->orderBy('animal.animal_id')->get(['animal.id','animal.registry_number','animal.animal_id','animal.tag_number','animal.breed','cooperative.cooperative_name'])->map(fn($animal)=>['id'=>$animal->id,'registry_id'=>$animal->registry_number?:$animal->animal_id,'tag_number'=>$animal->tag_number,'breed'=>$animal->breed,'cooperative_name'=>$animal->cooperative_name]);
     }elseif($module==='bcs'){
         $bcsFilters = $request->validate([
             'cooperative_id' => ['nullable', 'integer', 'min:1'],
             'score' => ['nullable', 'numeric'],
             'date_from' => ['nullable', 'date_format:Y-m-d'],
             'date_to' => ['nullable', 'date_format:Y-m-d'],
         ]);
         $bcsCooperatives = DB::table('cooperatives')->orderBy('cooperative_name')->get(['id', 'cooperative_name']);
         $cooperativeId = 'COALESCE(record_cooperative.id, animal_cooperative.id, farmer_cooperative.id)';
         $cooperativeName = "COALESCE(NULLIF(record_cooperative.cooperative_name, ''), NULLIF(animal_cooperative.cooperative_name, ''), NULLIF(farmer_cooperative.cooperative_name, ''), 'N/A')";
         $query=DB::table('bcs_records as bcs')
             ->leftJoin('carabaos as animal','animal.id','=','bcs.carabao_id')
             ->leftJoin('farmers as farmer','farmer.id','=','animal.farmer_id')
             ->leftJoin('cooperatives as record_cooperative','record_cooperative.id','=','bcs.cooperative_id')
             ->leftJoin('cooperatives as animal_cooperative','animal_cooperative.id','=','animal.cooperative_id')
             ->leftJoin('cooperatives as farmer_cooperative','farmer_cooperative.id','=','farmer.cooperative_id')
             ->select('bcs.id','bcs.record_date','bcs.body_condition_score')
             ->selectRaw("$cooperativeName as cooperative_name")
             ->selectRaw("COALESCE(NULLIF(TRIM(CONCAT_WS(' ', NULLIF(TRIM(farmer.first_name), ''), NULLIF(TRIM(farmer.middle_name), ''), NULLIF(TRIM(farmer.last_name), ''))), ''), 'N/A') as farmer_name");
         if (isset($bcsFilters['cooperative_id'])) $query->whereRaw("$cooperativeId = ?", [$bcsFilters['cooperative_id']]);
         if (isset($bcsFilters['score'])) $query->where('bcs.body_condition_score', $bcsFilters['score']);
         if (isset($bcsFilters['date_from'])) $query->where('bcs.record_date', '>=', $bcsFilters['date_from']);
         if (isset($bcsFilters['date_to'])) $query->where('bcs.record_date', '<=', $bcsFilters['date_to']);
         foreach (preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
             $pattern = '%'.$term.'%';
             $query->where(function ($builder) use ($cooperativeName, $pattern) {
                 $builder->whereRaw("$cooperativeName LIKE ?", [$pattern])
                     ->orWhereRaw("CONCAT_WS(' ', farmer.first_name, farmer.middle_name, farmer.last_name) LIKE ?", [$pattern])
                     ->orWhere('bcs.record_date', 'like', $pattern)->orWhere('bcs.body_condition_score', 'like', $pattern)
                     ->orWhere('bcs.registry_number', 'like', $pattern)->orWhere('animal.registry_number', 'like', $pattern)
                     ->orWhere('animal.animal_id', 'like', $pattern)->orWhere('animal.tag_number', 'like', $pattern);
             });
         }
         $bcsSummary = (clone $query)->select([])->selectRaw(
             'COUNT(*) as records, COUNT(DISTINCT bcs.carabao_id) as animals, AVG(bcs.body_condition_score) as average_score, MAX(bcs.record_date) as latest_date'
         )->first();
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
         $milkFarmers = DB::table('farmers')->whereNotNull('cooperative_id')->orderBy('last_name')->orderBy('first_name')
             ->select('id', 'cooperative_id')->selectRaw("CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), last_name) as name")->get();
         $milkAnimals = DB::table('carabaos')->whereNotNull('farmer_id')->orderBy('animal_id')
             ->select('id', 'farmer_id', 'animal_name')
             ->selectRaw("COALESCE(NULLIF(animal_id, ''), NULLIF(registry_number, ''), NULLIF(tag_number, '')) as animal_number")
             ->get()->filter(fn ($animal) => $animal->animal_number !== null)->values();
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
     }elseif(in_array($module, ['meat', 'manure'], true)){
         return $this->productIndex($request, $module, $search);
     }elseif($module==='fhpr'){
         $columns=$this->fhprDisplayColumns();
         $records=$this->fhprRecords();
         if($search!=='')$records=$records->filter(function($row)use($columns,$search){
             foreach($columns as $column){
                 if(str_contains(Str::lower((string)data_get($row,$column,'')),Str::lower($search)))return true;
                 if($column==='date_of_birth'&&str_contains(Str::lower((string)($row->_date_of_birth_search??'')),Str::lower($search)))return true;
             }
             return str_contains(Str::lower((string)($row->_date_search??'')),Str::lower($search));
         })->values();
         $records->each(function($row){unset($row->_date_of_birth_search,$row->_date_search);});
         $page=LengthAwarePaginator::resolveCurrentPage();$perPage=15;$total=$records->count();
         $rows=new LengthAwarePaginator($records->slice(($page-1)*$perPage,$perPage)->values(),$total,$perPage,$page,['path'=>$request->url(),'query'=>$request->query()]);
     }else{
         $query=DB::table($table);if(in_array($module,['meat','manure'],true))$query->where('product_type',$module);if($search!=='')$query->where(function($builder)use($columns,$search){foreach($columns as $column)$builder->orWhere($column,'like','%'.$search.'%');});$rows=$query->orderByDesc('id')->paginate(25)->withQueryString();
     }
     $moduleTitle=$module==='bcs'?'Body Condition Score Records':($module==='milk'?'Milk Production Records':null);
     $bcsScores=$module==='bcs'?DB::table('bcs_records')->whereNotNull('body_condition_score')->distinct()->orderBy('body_condition_score')->pluck('body_condition_score')->map(fn($score)=>number_format((float)$score,2,'.',''))->values():collect();
     return view('modules.index',compact('module','table','rows','columns','moduleTitle','bcsScores')+($module==='health'?compact('healthSummary','healthAnimals','healthFilters','healthCooperatives','healthBreeds'):[])+($module==='milk'?compact('milkSummary','milkCooperatives','milkFarmers','milkAnimals','milkYears','milkFilters','selectedYear','milkYearlySummaries','milkQuarterSummaries'):[])+($module==='bcs'?compact('bcsFilters','bcsCooperatives','bcsSummary'):[]));
 }

 private function productIndex(Request $request, string $module, string $search)
 {
     $moduleTitle = ucfirst($module).' Production';

     $columns = ['cooperative_name', 'actual_date', 'reported_date', 'volume_kg', 'other_source_volume_kg', 'price_per_kg', 'value_php'];
     $productFilters = $request->validate([
         'cooperative_id' => ['nullable', 'integer', 'min:1'], 'year' => ['nullable', 'digits:4'],
         'quarter' => ['nullable', 'in:1,2,3,4'], 'date' => ['nullable', 'date_format:Y-m-d'],
     ]);
     $productCooperatives = DB::table('cooperatives')->orderBy('cooperative_name')->get(['id', 'cooperative_name']);
     $date = 'COALESCE(record.actual_date, record.reported_date)';
     $query = DB::table('cooperative_product_records as record')
         ->leftJoin('cooperatives as cooperative', 'cooperative.id', '=', 'record.cooperative_id')
         ->where('record.product_type', $module);
     if ($search !== '') {
         $pattern = '%'.$search.'%';
         $query->where(fn ($builder) => $builder->where('cooperative.cooperative_name', 'like', $pattern)
             ->orWhere('cooperative.cooperative_code', 'like', $pattern)->orWhere('record.source_data', 'like', $pattern));
     }
     if (isset($productFilters['cooperative_id'])) $query->where('record.cooperative_id', $productFilters['cooperative_id']);
     $productYearly = (clone $query)->whereRaw("$date IS NOT NULL")->selectRaw("YEAR($date) as year, SUM(record.volume_kg) as volume")
         ->groupByRaw("YEAR($date)")->orderByDesc('year')->get();
     if (isset($productFilters['year'])) $query->whereRaw("YEAR($date) = ?", [$productFilters['year']]);
     $productQuarterly = (clone $query)->whereRaw("$date IS NOT NULL")->selectRaw("QUARTER($date) as quarter, SUM(record.volume_kg) as volume")
         ->groupByRaw("QUARTER($date)")->pluck('volume', 'quarter');
     if (isset($productFilters['quarter'])) $query->whereRaw("QUARTER($date) = ?", [$productFilters['quarter']]);
     if (isset($productFilters['date'])) $query->whereRaw("$date = ?", [$productFilters['date']]);
     $productSummary = (clone $query)->selectRaw('SUM(record.volume_kg) as volume, SUM(record.other_source_volume_kg) as other_volume, SUM(record.value_php) as value')->first();
     $rows = $query->select('record.*', 'cooperative.cooperative_name')->orderByRaw("$date DESC")->orderByDesc('record.id')->paginate(25)->withQueryString();

     return view('modules.index', compact('module', 'moduleTitle', 'columns', 'rows', 'productFilters', 'productCooperatives', 'productYearly', 'productQuarterly', 'productSummary'));
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
     $worksheet = \App\Support\HealthWorksheetMapping::fromSource($source);
     if (is_numeric($worksheet[$key] ?? null)) return max(0, (int) $worksheet[$key]);
     $selected = \App\Support\HealthWorksheetMapping::selectedServices($worksheet);
     if ($key === 'no_of_treatments' && $selected !== []) return count($selected);
     $services=$this->healthServicesArray($source);
     if($key==='no_of_visits_monitored')return 1;
     if($key==='no_of_treatments'&&$services!==[])return count($services);
     $fields=$source['health_activity']??$source['monitoring_fields']??$source;
     if(!array_key_exists($key,$fields)||$fields[$key]===null||$fields[$key]==='')return null;
     return max(0,(int)$fields[$key]);
 }

 private function healthServiceSummaryLabel(string $name): ?string
 {
     $name=mb_strtolower(trim($name));
     return match(true){
         preg_match('/de.?worm/',$name)===1=>'Deworming Services',
         preg_match('/vitamin|mineral|vi.?supp/',$name)===1=>'Vitamins Services',
         preg_match('/vaccin/',$name)===1=>'Vaccinations',
         preg_match('/uterine.?flush|flush/',$name)===1=>'Uterine Flushing Services',
         preg_match('/fecalysis|\\bfs\\b/',$name)===1=>'Fecalysis Activities',
         preg_match('/ear.?tag/',$name)===1=>'Ear Tagging Activities',
         preg_match('/artificial insemination|^ai$/',$name)===1=>'Artificial Insemination',
         preg_match('/recruit/',$name)===1=>'Recruitment Activities',
         preg_match('/hands.?on.*milk|milking/',$name)===1=>'Hands-on Milking Activities',
         preg_match('/forage/',$name)===1=>'Forage Demo Activities',
         default=>null,
     };
 }

 private function healthSummary(): array
 {
     $records=DB::table('health_records')->get(['carabao_id','source_data']);
     $services = array_fill_keys(['Deworming Services', 'Vitamins Services', 'Vaccinations', 'Uterine Flushing Services',
         'Fecalysis Activities', 'Ear Tagging Activities', 'Artificial Insemination', 'Recruitment Activities',
         'Hands-on Milking Activities', 'Forage Demo Activities'], 0);
     $visits=0;$treatments=0;
     foreach ($records as $record) {
         $source = $this->decodeHealthSource($record->source_data);
         $visits += $this->healthActivityCount($source, 'no_of_visits_monitored') ?? 0;
         $treatments += $this->healthActivityCount($source, 'no_of_treatments') ?? 0;
         $names = \App\Support\HealthWorksheetMapping::selectedServices(\App\Support\HealthWorksheetMapping::fromSource($source));
         if ($names === []) {
             $names = array_map(fn ($service) => $service['type'] === 'Other Services' ? $service['other_service_name'] : $service['type'], $this->healthServicesArray($source));
         }
         foreach ($names as $name) {
             $label = $this->healthServiceSummaryLabel($name);
             if ($label !== null) $services[$label]++;
         }
     }
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

 public function updateFhprDryOffDate(Request $request,int $id)
 {
     $data=$request->validate(['dry_off_date'=>['nullable','date_format:Y-m-d']]);
     return DB::transaction(function()use($data,$id){
         $row=DB::table('fhpr_records')->where('id',$id)->lockForUpdate()->first();
         abort_unless($row,404,'FHPR record not found.');
         $date=$data['dry_off_date']??null;
         $animal=DB::table('carabaos')->where('id',$row->carabao_id)->lockForUpdate()->first();
         $classification=$animal->classification??null;
         $start=\App\Support\FhprLactation::start($row);
         $updates=['dry_off_date'=>$date,'updated_at'=>now()];
         if($start){
             $days=\App\Support\FhprLactation::days($start,$date);
             if($days<0)throw \Illuminate\Validation\ValidationException::withMessages(['dry_off_date'=>'Dry Off Date cannot be before the lactation start date.']);
             $source=json_decode((string)$row->source_data,true)?:[];
             $source['fhpr_lactation_start_date']=$start->toDateString();
             $updates['source_data']=json_encode($source,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
             $updates['days_in_milk']=$days;
         }
         if($animal&&!\App\Support\InventoryClassification::isExcluded($animal->status,$animal->nature_of_exit??null)){
             $classification=\App\Support\FhprClassification::resolve($animal->classification,$updates['days_in_milk']??$row->days_in_milk,$date,\App\Support\FhprClassification::latestPdResult($animal->id));
             $updates['status_updated']=$classification;
             DB::table('carabaos')->where('id',$animal->id)->update(['classification'=>$classification,'updated_at'=>now()]);
         }
         DB::table('fhpr_records')->where('id',$id)->update($updates);
         return response()->json(['dry_off_date'=>$date,'display_date'=>$this->formatFhprDate($date),
             'days_in_milk'=>$updates['days_in_milk']??$row->days_in_milk,
             'lactation_start_date'=>$start?->toDateString(),'lactation_start_display'=>$start?->format('F j,Y'),
             'classification'=>$classification]);
     });
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
     $columns=array_values(array_filter($columns,fn($column)=>$column!=='animal_identifier'));
     $farmerIndex=array_search('farmer_name',$columns,true);
     array_splice($columns,$farmerIndex===false?0:$farmerIndex+1,0,['animal_identifier']);
     return array_values(array_unique($columns));
 }

 private function fhprRecords(): \Illuminate\Support\Collection
 {
     $dateColumns=collect(Schema::getColumns('fhpr_records'))
         ->filter(fn($column)=>in_array($column['type_name'],['date','datetime','timestamp'],true)
             &&!in_array($column['name'],['created_at','updated_at'],true))
         ->pluck('name')->all();
     $latestAi=DB::table('mating_records')
         ->whereRaw("UPPER(TRIM(mating_type)) IN ('AI', 'ARTIFICIAL INSEMINATION')")
         ->whereNotNull('carabao_id')->whereNotNull('mating_date')
         ->select('carabao_id')->selectRaw('MAX(mating_date) as last_ai_date, COUNT(*) as ai_count')->groupBy('carabao_id');
     $latestPd=DB::table('mating_records')
         ->whereColumn('mating_records.carabao_id','inventory_animal.id')
         ->whereNotNull('pregnancy_result')->whereRaw("TRIM(pregnancy_result) <> ''")
         ->orderByDesc('pregnancy_check_date')->orderByDesc('mating_date')->orderByDesc('id')
         ->select('pregnancy_result')->limit(1);
     $latestMating=DB::table('mating_records')
         ->whereColumn('mating_records.carabao_id','inventory_animal.id')
         ->whereNotNull('mating_type')->whereRaw("TRIM(mating_type) <> ''")
         ->orderByDesc('mating_date')->orderByDesc('pregnancy_check_date')->orderByDesc('id')
         ->limit(1);
     $records=DB::table('fhpr_records')
         ->leftJoin('carabaos as inventory_animal','inventory_animal.id','=','fhpr_records.carabao_id')
         ->leftJoinSub($latestAi,'latest_ai',fn($join)=>$join->on('latest_ai.carabao_id','=','inventory_animal.id'))
         ->leftJoin('farmers as inventory_farmer','inventory_farmer.id','=','inventory_animal.farmer_id')
         ->whereRaw("UPPER(TRIM(inventory_animal.sex)) IN ('F', 'FEMALE')")
         ->whereRaw("NULLIF(TRIM(inventory_animal.classification), '') IS NOT NULL")
         ->leftJoin('cooperatives as cooperative',function($join){
             $join->on('cooperative.id','=',DB::raw('COALESCE(inventory_animal.cooperative_id, inventory_farmer.cooperative_id, fhpr_records.cooperative_id)'));
         })
         ->select('fhpr_records.*','inventory_animal.sex as sex','inventory_animal.classification as status_updated')
         ->addSelect('latest_ai.last_ai_date as last_ai_date')
         ->selectRaw('COALESCE(latest_ai.ai_count, 0) as ai_count')
         ->selectSub($latestPd,'pregnancy_status')
         ->selectSub((clone $latestPd)->select('mating_date'),'_pregnancy_mating_date')
         ->selectSub((clone $latestPd)->select('pregnancy_check_date'),'actual_pd_date')
         ->selectSub((clone $latestPd)->select('expected_calving_date'),'expected_calving_date')
         ->selectSub((clone $latestMating)->select('mating_type'),'ai_or_n')
         ->selectSub((clone $latestMating)->select('bull_id'),'sire_used')
         ->selectSub((clone $latestMating)->select('source_data'),'_latest_mating_source')
         ->selectRaw("COALESCE(NULLIF(inventory_animal.registry_number, ''), NULLIF(inventory_animal.animal_id, ''), NULLIF(fhpr_records.registry_number, ''), 'N/A') as animal_identifier")
         ->selectRaw("COALESCE(NULLIF(TRIM(cooperative.cooperative_name), ''), 'N/A') as cooperative_name_display")
         ->get();
     $records->each(function($row)use($dateColumns){
         $lactationStart=\App\Support\FhprLactation::start($row);
         $row->_lactation_start_date=$lactationStart?->toDateString();
         $row->_lactation_start_display=$lactationStart?->format('F j,Y');
         $lactationSource=json_decode((string)($row->source_data??''),true)?:[];
         if($lactationStart&&!empty($lactationSource['fhpr_lactation_start_date'])){
             $row->days_in_milk=max(0,\App\Support\FhprLactation::days($lactationStart,$row->dry_off_date??null));
         }
         $row->ai_or_n=match(strtoupper(trim((string)$row->ai_or_n))){
             'AI','ARTIFICIAL INSEMINATION'=>'AI',
             'N','NM','NS','NATURAL','NATURAL MATING','NATURAL SERVICE'=>'NM',
             default=>$row->ai_or_n,
         };
         $matingSource=json_decode((string)$row->_latest_mating_source,true)?:[];
         $matingFields=$matingSource['mating_api_fields']??$matingSource['monitoring_fields']??[];
         $row->estrus_type=$row->ai_or_n==='AI'
             ?(array_key_exists('estrus_type',$matingFields)?$matingFields['estrus_type']
                 :($matingSource['mating_column_mapping']['estrus_type']??$matingSource['Type of estrus']??null))
             :null;
         $row->pregnancy_status=match(strtolower(trim((string)$row->pregnancy_status))){
             '+','p','positive','pregnant','yes'=>'Positive',
             '-','n','negative','not pregnant','non-pregnant','no'=>'Negative',
             'pending','null','n/a','na','?'=>'Pending',
             default=>$row->pregnancy_status,
         };
         $row->status_updated=\App\Support\FhprClassification::resolve($row->status_updated,$row->days_in_milk??null,$row->dry_off_date??null,$row->pregnancy_status);
         $row->days_pregnant=$row->pregnancy_status==='Positive'
             ?($row->_pregnancy_mating_date
                 ?max(0,(int)Carbon::parse($row->_pregnancy_mating_date,'Asia/Singapore')->startOfDay()->diffInDays(Carbon::today('Asia/Singapore'),false))
                 :0)
             :null;
         $row->expected_calving_date=$row->pregnancy_status==='Positive'
             ?($row->expected_calving_date?:($row->_pregnancy_mating_date
                 ?Carbon::parse($row->_pregnancy_mating_date)->addDays(310)->toDateString():null))
             :null;
         $row->_date_of_birth_search=$row->date_of_birth??'';
         $row->_dry_off_date=$row->dry_off_date??'';
         $row->_date_search=implode(' ',array_map(fn($column)=>(string)($row->{$column}??''),$dateColumns));
         $row->age=$this->animalAge($row->date_of_birth);
         $row->record_source='FHPR Breeding';
     });
     $ordered=$records->sortByDesc('id')->values();
     $ordered->each(function($row)use($dateColumns){
         $row->tag_number=$this->displayFhprTagNumber($row->tag_number??null);
         foreach($dateColumns as $column)$row->{$column}=$this->formatFhprDate($row->{$column}??null);
         unset($row->carabao_id,$row->cooperative_id,$row->registry_number,$row->source_data,$row->_pregnancy_mating_date,$row->_latest_mating_source);
     });
     return $ordered;
 }

 private function formatFhprDate($date): string
 {
     if($date===null||trim((string)$date)==='')return '-';
     try{return Carbon::parse($date)->format('F j,Y');}catch(\Throwable){return '-';}
 }

 private function displayFhprTagNumber($tagNumber): string
 {
     $tagNumber=trim((string)($tagNumber??''));
     return $tagNumber===''||(is_numeric($tagNumber)&&(float)$tagNumber===0.0)?'-':$tagNumber;
 }

 private const PRODUCT_CSV_COLUMNS = ['cooperative_id', 'actual_date', 'reported_date', 'volume_kg', 'other_source_volume_kg', 'price_per_kg', 'value_php'];

 public function template($module){if(in_array($module,['exits','nature-of-exit'],true))return app(AnimalExitController::class)->download(true);
     if($module==='mtd')return app(MtdController::class)->download(true);abort_if($module==='fhpr',404);if($module==='milk'){$cols=['Cooperative Code','Cooperative Name','Farmer Name','Actual Date of Collection','AM','Total','No. of cows','Price per kg (Raw Milk)','Milk Traded'];return response()->streamDownload(function()use($cols){if(ob_get_level()>0)ob_clean();$f=fopen('php://output','w');fputcsv($f,$cols);fclose($f);},'milk_template.csv');}$cols=in_array($module,['meat','manure'],true)?self::PRODUCT_CSV_COLUMNS:collect(Schema::getColumnListing($this->table($module)))->reject(fn($c)=>in_array($c,['id','created_at','updated_at']))->all();return response()->streamDownload(function()use($cols){$f=fopen('php://output','w');fputcsv($f,$cols);fclose($f);},$module.'_template.csv');}
 public function export($module){
     if (in_array($module, ['exits', 'nature-of-exit'], true)) return app(AnimalExitController::class)->download(false);
     if ($module === 'mtd') return app(MtdController::class)->download(false);
     if (in_array($module, ['meat', 'manure'], true)) return response()->streamDownload(function () use ($module) {
         $file = fopen('php://output', 'w');
         fputcsv($file, self::PRODUCT_CSV_COLUMNS);
         DB::table('cooperative_product_records')->where('product_type', $module)->orderBy('id')->chunk(500, function ($rows) use ($file) {
             foreach ($rows as $row) fputcsv($file, array_map(fn ($field) => $row->$field, self::PRODUCT_CSV_COLUMNS));
         });
         fclose($file);
     }, $module.'_production_export.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
     $table=$this->table($module);
     if($module==='fhpr'){$cols=$this->fhprDisplayColumns();$rows=$this->fhprRecords();$labels=['animal_identifier'=>'Animal ID','cooperative_name_display'=>'Cooperative','farmer_name'=>'Farmer Name','date_of_birth'=>'Date of Birth','status_updated'=>'Classification'];return response()->streamDownload(function()use($cols,$rows,$labels){$file=fopen('php://output','w');fputcsv($file,array_map(fn($column)=>$labels[$column]??ucwords(str_replace('_',' ',$column)),$cols));foreach($rows as $row)fputcsv($file,array_map(fn($column)=>data_get($row,$column,''),$cols));fclose($file);},'fhpr_export.csv');}
     if ($module === 'health') {
         return response()->streamDownload(function () {
             $file = fopen('php://output', 'w');
             fwrite($file, "\xEF\xBB\xBF");
             $serviceFields = ['deworming', 'vitamins_minerals', 'vaccination', 'uterine_flushing', 'ear_tagging'];
             fputcsv($file, ['Monitoring Date', 'Animal Registry ID', 'Tag Number', 'Cooperative Name', 'Breed',
                 'Type of Service', 'Dosage (mL)', 'No. of Visits / Monitored', 'No. of Treatments', 'Service Remarks', 'Activity Remarks',
                 'Deworming', 'Vitamins / Mineral Supplements', 'Vaccination', 'Uterine Flushing', 'Ear Tagging']);
             DB::table('health_records as record')
                 ->leftJoin('carabaos as animal', 'animal.id', '=', 'record.carabao_id')
                 ->leftJoin('farmers as farmer', 'farmer.id', '=', 'animal.farmer_id')
                 ->leftJoin('cooperatives as cooperative', 'cooperative.id', '=', DB::raw('COALESCE(record.cooperative_id, animal.cooperative_id, farmer.cooperative_id)'))
                 ->select('record.*', 'animal.registry_number as animal_registry_number', 'animal.animal_id', 'animal.tag_number', 'animal.breed', 'cooperative.cooperative_name')
                 ->orderBy('record.id')->chunk(500, function ($rows) use ($file, $serviceFields) {
                     foreach ($rows as $row) {
                         $source = $this->decodeHealthSource($row->source_data);
                         $worksheet = \App\Support\HealthWorksheetMapping::fromSource($source);
                         $importedServices = \App\Support\HealthWorksheetMapping::selectedServices($worksheet);
                         $services = $this->healthServicesArray($source);
                         if ($importedServices || ! $services) {
                             $services = [['type' => implode('; ', $importedServices), 'dosage_ml' => $worksheet['dosage'] ?? null, 'remarks' => '']];
                         } elseif (isset($worksheet['dosage']) && $worksheet['dosage'] !== '') {
                             $services[0]['dosage_ml'] = $worksheet['dosage'];
                         }
                         foreach ($services as $index => $service) {
                             $type = $service['type'] === 'Other Services' ? ($service['other_service_name'] ?: 'Other Services') : $service['type'];
                             fputcsv($file, [
                                 $row->monitoring_date ? Carbon::parse($row->monitoring_date)->format('F j, Y') : '-',
                                 $row->animal_registry_number ?: ($row->registry_number ?: $row->animal_id),
                                 $row->tag_number, $row->cooperative_name, $row->breed, $type, $service['dosage_ml'] ?? '-',
                                 $index === 0 ? $this->healthActivityCount($source, 'no_of_visits_monitored') : '',
                                 $index === 0 ? $this->healthActivityCount($source, 'no_of_treatments') : '',
                                 $service['remarks'], $row->remarks ?? ($worksheet['remarks'] ?? null),
                                 ...array_map(fn ($field) => $index === 0 ? ($worksheet[$field] ?? null) : null, $serviceFields),
                             ]);
                         }
                     }
                 });
             fclose($file);
         }, 'health_export.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
     }
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
 public function storeMilkProduction(Request $request)
 {
     $data = $request->validateWithBag('milkProduction', [
         'cooperative_id' => ['required', 'integer', 'exists:cooperatives,id'],
         'farmer_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('farmers', 'id')->where('cooperative_id', $request->integer('cooperative_id'))],
         'record_date' => ['required', 'date_format:Y-m-d'],
         'collection_period' => ['required', 'in:AM,PM'],
         'total_liters' => ['required', 'numeric', 'min:0', 'max:999999.99'],
         'number_of_cows' => ['required', 'integer', 'min:0', 'max:4294967295'],
         'carabao_id' => ['exclude_unless:number_of_cows,1', 'nullable', 'integer', \Illuminate\Validation\Rule::exists('carabaos', 'id')->where('farmer_id', $request->integer('farmer_id'))],
         'price_per_kg' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
         'milk_traded' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
     ]);

     DB::transaction(function () use ($data) {
         DB::table('cooperatives')->where('id', $data['cooperative_id'])->lockForUpdate()->first();
         $farmer = DB::table('farmers')->where('id', $data['farmer_id'])->where('cooperative_id', $data['cooperative_id'])->first();
         if (! $farmer) {
             throw \Illuminate\Validation\ValidationException::withMessages(['farmer_id' => 'Select a farmer from this cooperative.'])->errorBag('milkProduction');
         }

         $farmerName = implode(' ', array_filter([$farmer->first_name, $farmer->middle_name, $farmer->last_name]));
         $existing = DB::table('milk_records')->where('cooperative_id', $data['cooperative_id'])
             ->where('record_date', $data['record_date'])->where('collection_period', $data['collection_period'])
             ->where(fn ($query) => $query->where('farmer_id', $farmer->id)
                 ->orWhere(fn ($legacy) => $legacy->whereNull('farmer_id')->where('farmer_name', $farmerName)))->exists();
         if ($existing) {
             throw \Illuminate\Validation\ValidationException::withMessages(['record_date' => 'A record already exists for this farmer, collection date, and AM / PM period.'])->errorBag('milkProduction');
         }

         DB::table('milk_records')->insert($data + ['farmer_name' => $farmerName, 'created_at' => now(), 'updated_at' => now()]);
     });

     return redirect()->route('ui.milk', ['year' => substr($data['record_date'], 0, 4)])->with('success', 'Milk production record saved.');
 }

 private function productProductionRules(bool $import = false): array
 {
     return [
         'cooperative_id' => [$import ? 'nullable' : 'required', 'integer', 'exists:cooperatives,id'],
         'actual_date' => [$import ? 'nullable' : 'required', 'date_format:Y-m-d'],
         'reported_date' => ['nullable', 'date_format:Y-m-d'],
         'volume_kg' => [$import ? 'nullable' : 'required', 'numeric', 'min:0', 'max:9999999999.99'],
         'other_source_volume_kg' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
         'price_per_kg' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
         'value_php' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
     ];
 }

 public function storeProductProduction(Request $request, string $module)
 {
     $data = $request->validateWithBag('productProduction', $this->productProductionRules());
     DB::table('cooperative_product_records')->insert($data + ['product_type' => $module, 'created_at' => now(), 'updated_at' => now()]);

     return redirect()->route('ui.'.$module, ['year' => substr($data['actual_date'], 0, 4)])->with('success', ucfirst($module).' production record saved.');
 }

 private function importProductCsv(Request $request, string $module)
 {
     $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);
     $file = fopen($request->file('file')->getRealPath(), 'r');
     $records = [];
     try {
         $line = 0;
         do {
             $headerRow = fgetcsv($file, 0, ',', '"', '');
             $line++;
         } while ($headerRow === [null]);
         $headers = array_map(fn ($field) => trim($field, "\xEF\xBB\xBF \t"), $headerRow ?: []);
         if ($headers !== self::PRODUCT_CSV_COLUMNS) {
             throw \Illuminate\Validation\ValidationException::withMessages(['file' => 'Use the '.ucfirst($module).' Production CSV template.']);
         }
         while (($row = fgetcsv($file, 0, ',', '"', '')) !== false) {
             $line++;
             if ($row === [null]) continue;
             if (count($row) !== count($headers)) {
                 throw \Illuminate\Validation\ValidationException::withMessages(['file' => "Row {$line} has the wrong number of columns."]);
             }
             $values = array_map(fn ($value) => trim((string) $value) === '' ? null : trim((string) $value), $row);
             $validator = \Illuminate\Support\Facades\Validator::make(array_combine($headers, $values), $this->productProductionRules(true));
             if ($validator->fails()) {
                 throw \Illuminate\Validation\ValidationException::withMessages(['file' => "Row {$line}: ".$validator->errors()->first()]);
             }
             if (array_filter($values, fn ($value) => $value !== null)) $records[] = $validator->validated();
         }
     } finally {
         fclose($file);
     }
     if (! $records) throw \Illuminate\Validation\ValidationException::withMessages(['file' => 'The CSV contains no '.$module.' production records.']);

     DB::transaction(function () use ($records, $module) {
         foreach ($records as $record) {
             $key = $record + ['product_type' => $module];
             if (! DB::table('cooperative_product_records')->where($key)->exists()) {
                 DB::table('cooperative_product_records')->insert($key + ['created_at' => now(), 'updated_at' => now()]);
             }
         }
     });

     return back()->with('success', 'Processed '.count($records).' '.$module.' production record(s).');
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

 public function import(Request $r,$module){if(in_array($module,['exits','nature-of-exit'],true))return app(AnimalExitController::class)->import($r);
     if($module==='mtd')return app(MtdController::class)->import($r);abort_if($module==='fhpr',404);if($module==='milk')return $this->importMilkCsv($r);if(in_array($module,['meat','manure'],true))return $this->importProductCsv($r,$module);$t=$this->table($module);$r->validate(['file'=>'required|file|mimes:csv,txt|max:10240']);$f=fopen($r->file('file')->getRealPath(),'r');$heads=fgetcsv($f);abort_if(!$heads,422,'CSV header is missing.');$allowed=collect(Schema::getColumnListing($t))->reject(fn($c)=>in_array($c,['id','created_at','updated_at']))->all();$heads=array_map(fn($h)=>Str::snake(trim($h)),$heads);$n=0;DB::transaction(function()use($f,$heads,$allowed,$t,&$n){while(($row=fgetcsv($f))!==false){if(count($row)!==count($heads))continue;$d=array_intersect_key(array_combine($heads,$row),array_flip($allowed));if(!$d)continue;$d=array_filter($d,fn($v)=>$v!==null&&trim((string)$v)!=='');$d['updated_at']=now();DB::table($t)->insert($d+['created_at'=>now()]);$n++;}});fclose($f);return back()->with('success',"Imported {$n} row(s).");}
}
