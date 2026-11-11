<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
class ModuleController extends Controller {
 private array $map=['farmers'=>'farmers','cooperatives'=>'cooperatives','carabaos'=>'carabaos','mating'=>'mating_records','fhpr'=>'fhpr_records','calves'=>'calves','milk'=>'milk_records','mtd'=>'mtd_records','health'=>'health_records','nature-of-exit'=>'nature_of_exits','exits'=>'nature_of_exits','semen-bulls'=>'semen_bulls','semen-usage'=>'semen_usage','meat'=>'cooperative_product_records','manure'=>'cooperative_product_records'];
 private function table($m){abort_unless(isset($this->map[$m]),404);return $this->map[$m];}
 public function monitoring(Request $request){
     $search=trim((string)$request->query('search',''));
     $query=DB::table('health_records')
         ->leftJoin('carabaos as animal','animal.id','=','health_records.carabao_id')
         ->leftJoin('farmers as farmer','farmer.id','=','animal.farmer_id')
         ->leftJoin('cooperatives as cooperative','cooperative.id','=','animal.cooperative_id')
         ->select('health_records.id','health_records.monitoring_date','health_records.health_status','health_records.findings','health_records.treatment','health_records.follow_up_date')
         ->selectRaw('cooperative.cooperative_name as cooperative_name')
         ->selectRaw("CONCAT_WS(' ', NULLIF(TRIM(farmer.first_name), ''), NULLIF(TRIM(farmer.middle_name), ''), NULLIF(TRIM(farmer.last_name), '')) as farmer_name")
         ->selectRaw("COALESCE(NULLIF(animal.registry_number, ''), NULLIF(health_records.registry_number, '')) as registry_id");
     if($search!=='')$query->where(function($builder)use($search){$like='%'.$search.'%';$builder->where('cooperative.cooperative_name','like',$like)->orWhere('farmer.first_name','like',$like)->orWhere('farmer.middle_name','like',$like)->orWhere('farmer.last_name','like',$like)->orWhere('animal.registry_number','like',$like)->orWhere('health_records.registry_number','like',$like)->orWhere('health_records.health_status','like',$like)->orWhere('health_records.findings','like',$like);});
     $rows=$query->orderByDesc('health_records.monitoring_date')->orderByDesc('health_records.id')->paginate(25)->withQueryString();
     $counts=[
         'records'=>DB::table('health_records')->count(),
         'animals'=>DB::table('health_records')->whereNotNull('carabao_id')->distinct()->count('carabao_id'),
         'follow_ups'=>DB::table('health_records')->whereNotNull('follow_up_date')->whereDate('follow_up_date','>=',now()->toDateString())->count(),
         'cooperatives'=>DB::table('health_records')->join('carabaos','carabaos.id','=','health_records.carabao_id')->whereNotNull('carabaos.cooperative_id')->distinct()->count('carabaos.cooperative_id'),
     ];
     return view('monitoring',compact('rows','counts','search'));
 }
 public function index(\Illuminate\Http\Request $request, $module){
     $table=$this->table($module);$allColumns=Schema::getColumnListing($table);$columns=collect($allColumns)->reject(fn($column)=>in_array($column,['id','created_at','updated_at']))->values()->all();$search=trim((string)$request->query('search',$request->query('q','')));
     if($module==='health'){
         $healthColumns=array_values(array_diff($allColumns,['id','created_at','updated_at','remarks','carabao_id','registry_number','cooperative_id']));
         $columns=array_merge(
             ['cooperative_name','farmer_name','registry_id'],
             array_values(array_intersect(['monitoring_date'],$healthColumns)),
             array_values(array_diff($healthColumns,['monitoring_date']))
         );
         $dateColumns=array_values(array_filter(['monitoring_date','follow_up_date'],fn($column)=>in_array($column,$allColumns,true)));
         $query=DB::table('health_records')
             ->leftJoin('carabaos as inventory_animal','inventory_animal.id','=','health_records.carabao_id')
             ->leftJoin('farmers as animal_farmer','animal_farmer.id','=','inventory_animal.farmer_id')
             ->leftJoin('cooperatives as animal_cooperative','animal_cooperative.id','=','inventory_animal.cooperative_id')
             ->select(array_map(fn($column)=>'health_records.'.$column,$healthColumns))
             ->addSelect('animal_cooperative.cooperative_name as cooperative_name')
             ->selectRaw("COALESCE(NULLIF(inventory_animal.registry_number, ''), NULLIF(health_records.registry_number, '')) as registry_id")
             ->selectRaw("CONCAT_WS(' ', NULLIF(TRIM(animal_farmer.first_name), ''), NULLIF(TRIM(animal_farmer.middle_name), ''), NULLIF(TRIM(animal_farmer.last_name), '')) as farmer_name");
         if($search!=='')$query->where(function($builder)use($columns,$search){foreach($columns as $column){if($column==='registry_id')$builder->orWhereRaw("COALESCE(NULLIF(inventory_animal.registry_number, ''), NULLIF(health_records.registry_number, '')) LIKE ?",['%'.$search.'%']);elseif($column==='cooperative_name')$builder->orWhere('animal_cooperative.cooperative_name','like','%'.$search.'%');elseif($column==='farmer_name')$builder->orWhereRaw("CONCAT_WS(' ', NULLIF(TRIM(animal_farmer.first_name), ''), NULLIF(TRIM(animal_farmer.middle_name), ''), NULLIF(TRIM(animal_farmer.last_name), '')) LIKE ?",['%'.$search.'%']);else $builder->orWhere('health_records.'.$column,'like','%'.$search.'%');}});
         $rows=$query->orderByDesc('health_records.id')->paginate(25)->withQueryString();
         $rows->getCollection()->transform(function($row)use($dateColumns){foreach($dateColumns as $column){if(!empty($row->{$column}))$row->{$column}=Carbon::parse($row->{$column})->format('F j, Y');}return $row;});
     }elseif($module==='fhpr'){
         $columns=array_values(array_unique(array_merge($columns,['sex','age','record_source'])));
         $records=DB::table('fhpr_records')->leftJoin('carabaos as inventory_animal','inventory_animal.id','=','fhpr_records.carabao_id')->select('fhpr_records.*','inventory_animal.sex as sex')->get();
         $records->each(function($row){$row->age=$this->animalAge($row->date_of_birth);$row->record_source='FHPR Breeding';});
         $fhprCarabaoIds=$records->pluck('carabao_id')->filter()->map(fn($id)=>(string)$id)->all();
         $males=DB::table('carabaos as c')->leftJoin('farmers as f','f.id','=','c.farmer_id')->where('c.status','Live')->whereRaw('UPPER(c.sex) IN (?, ?)', ['M','MALE'])->select('c.id as carabao_id','c.cooperative_id','c.registry_number','c.tag_number','c.birth_date','c.animal_id','c.sex','f.first_name','f.middle_name','f.last_name')->get();
         foreach($males as $male){
             if(in_array((string)$male->carabao_id,$fhprCarabaoIds,true))continue;
             $age=$this->animalAge($male->birth_date);$category=$this->maleAgeCategory($male->birth_date);
             $records->push((object)[
                 'id'=>1000000000+(int)$male->carabao_id,'carabao_id'=>$male->carabao_id,'cooperative_id'=>$male->cooperative_id,
                 'farmer_name'=>trim(implode(' ',array_filter([$male->last_name ? $male->last_name.',' : '',$male->first_name,$male->middle_name]))),
                 'registry_number'=>$male->registry_number,'animal_type'=>$category,'tag_number'=>$male->tag_number,
                 'date_of_birth'=>$male->birth_date,'status_updated'=>$category,'sex'=>$male->sex,'age'=>$age,
                 'record_source'=>'Carabao Inventory (age-based)',
             ]);
         }
         $breedingRows=$records->where('record_source','FHPR Breeding')->sortByDesc('id')->values();
         $inventoryMaleRows=$records->where('record_source','Carabao Inventory (age-based)')->sortByDesc('carabao_id')->values();
         $records=collect();$maleIndex=0;
         foreach($breedingRows as $index=>$breedingRow){
             $records->push($breedingRow);
             if(($index+1)%5===0&&isset($inventoryMaleRows[$maleIndex]))$records->push($inventoryMaleRows[$maleIndex++]);
         }
         while(isset($inventoryMaleRows[$maleIndex]))$records->push($inventoryMaleRows[$maleIndex++]);
         if($search!=='')$records=$records->filter(fn($row)=>collect($columns)->contains(fn($column)=>str_contains(Str::lower((string)data_get($row,$column,'')),Str::lower($search))))->values();
         $page=LengthAwarePaginator::resolveCurrentPage();$perPage=25;$total=$records->count();
         $rows=new LengthAwarePaginator($records->slice(($page-1)*$perPage,$perPage)->values(),$total,$perPage,$page,['path'=>$request->url(),'query'=>$request->query()]);
     }else{
         $query=DB::table($table);if(in_array($module,['meat','manure'],true))$query->where('product_type',$module);if($search!=='')$query->where(function($builder)use($columns,$search){foreach($columns as $column)$builder->orWhere($column,'like','%'.$search.'%');});$rows=$query->orderByDesc('id')->paginate(25)->withQueryString();
     }
     return view('modules.index',compact('module','table','rows','columns'));
 }

 private function animalAge(?string $birthDate): string
 {
     if(!$birthDate)return '—';try{$age=Carbon::parse($birthDate)->diff(Carbon::now());return $age->y.'yrs '.$age->m.'mo';}catch(\Throwable){return '—';}
 }

 private function maleAgeCategory(?string $birthDate): string
 {
     if(!$birthDate)return 'Age Unknown';try{$age=Carbon::parse($birthDate)->diff(Carbon::now());if($age->y<1)return 'Male Calf';if($age->y<2)return 'Yearling Bull';if($age->y<3)return 'Junior Bull';return 'Senior Bull';}catch(\Throwable){return 'Age Unknown';}
 }
 public function template($module){$cols=collect(Schema::getColumnListing($this->table($module)))->reject(fn($c)=>in_array($c,['id','created_at','updated_at'])||($module==='health'&&$c==='remarks'))->all();return response()->streamDownload(function()use($cols){$f=fopen('php://output','w');fputcsv($f,$cols);fclose($f);},$module.'_template.csv');}
 public function export($module){$t=$this->table($module);$cols=collect(Schema::getColumnListing($t))->reject(fn($c)=>$c==='id'||($module==='health'&&$c==='remarks'))->all();return response()->streamDownload(function()use($t,$cols){$f=fopen('php://output','w');fputcsv($f,$cols);DB::table($t)->orderBy('id')->chunk(500,function($rows)use($f,$cols){foreach($rows as $r){$a=(array)$r;fputcsv($f,array_map(fn($c)=>$a[$c]??'',$cols));}});fclose($f);},$module.'_export.csv');}
 public function import(Request $r,$module){$t=$this->table($module);$r->validate(['file'=>'required|file|mimes:csv,txt|max:10240']);$f=fopen($r->file('file')->getRealPath(),'r');$heads=fgetcsv($f);abort_if(!$heads,422,'CSV header is missing.');$allowed=collect(Schema::getColumnListing($t))->reject(fn($c)=>in_array($c,['id','created_at','updated_at'])||($module==='health'&&$c==='remarks'))->all();$heads=array_map(fn($h)=>Str::snake(trim($h)),$heads);$n=0;DB::transaction(function()use($f,$heads,$allowed,$t,$module,&$n){while(($row=fgetcsv($f))!==false){if(count($row)!==count($heads))continue;$d=array_intersect_key(array_combine($heads,$row),array_flip($allowed));if(!$d)continue;$d=array_filter($d,fn($v)=>$v!==null&&trim((string)$v)!=='');$d['updated_at']=now();if($module==='fhpr'&&!empty($d['cooperative_id'])&&!empty($d['registry_number'])){$key=['cooperative_id'=>$d['cooperative_id'],'registry_number'=>$d['registry_number']];$existing=DB::table($t)->where($key)->first();if($existing)DB::table($t)->where('id',$existing->id)->update($d);else DB::table($t)->insert($d+['created_at'=>now()]);$this->syncFhprClassification($d);}else DB::table($t)->insert($d+['created_at'=>now()]);$n++;}});fclose($f);return back()->with('success',"Imported/updated {$n} row(s). FHPR status updates are reflected in Carabao Inventory classification.");}

 private function syncFhprClassification(array $record): void
 {
     $classification=trim((string)($record['status_updated']??''));$registry=trim((string)($record['registry_number']??''));$coopId=$record['cooperative_id']??null;
     if($classification===''||$registry===''||!$coopId)return;
     $animals=DB::table('carabaos')->where('cooperative_id',$coopId)->where('registry_number',$registry);
     if(!empty($record['carabao_id']))$animals->where('id',$record['carabao_id']);
     else{$farmerName=trim((string)($record['farmer_name']??''));if($farmerName==='')return;$farmer=DB::table('farmers')->where('cooperative_id',$coopId)->get()->first(function($farmer)use($farmerName){$fullName=trim(implode(' ',array_filter([$farmer->first_name??'',$farmer->middle_name??'',$farmer->last_name??''])));return mb_strtolower(preg_replace('/\\s+/',' ',$fullName))===mb_strtolower(preg_replace('/\\s+/',' ',$farmerName));});if(!$farmer)return;$animals->where('farmer_id',$farmer->id);}
     $animals->update(['classification'=>$classification,'updated_at'=>now()]);
 }
}
