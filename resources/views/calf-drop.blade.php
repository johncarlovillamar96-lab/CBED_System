{{-- resources/views/calf-drop.blade.php --}}
@extends('layouts.admin')
@section('title', 'Calf Records | CBED')
@section('page-heading', 'Calf Records')

@section('content')
<div x-data="calfDropPage()" x-init="init()" x-cloak class="calf-records-page management-page min-w-0 bg-slate-50 text-slate-800">
    <div class="management-hero mb-7 flex flex-col justify-between gap-4 rounded-2xl p-5 text-white shadow-lg sm:flex-row sm:items-center sm:p-6">
        <div>
            <div class="mb-2 flex flex-wrap items-center gap-2 text-sm text-blue-100"><i class="fa-solid fa-house"></i><span>/</span><span>CBED Data System</span><span>/</span><span class="font-semibold text-white">Calf Records</span></div>
            <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">Calf Records</h1>
            <p class="mt-2 text-sm text-slate-200">Review births and manage calf records linked to Carabao Inventory.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="exportCSV()" class="inline-flex items-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-semibold shadow-sm"><i class="fa-solid fa-file-export"></i> Export CSV</button>
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-semibold shadow-sm"><i class="fa-solid fa-file-import"></i> Import CSV<input type="file" accept=".csv,text/csv" class="hidden" @change="importCSV($event)"></label>
            <button type="button" @click="openBulk()" class="inline-flex items-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-semibold shadow-sm"><i class="fa-solid fa-plus"></i> Add Calf Records</button>
            <span class="rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-sm text-white"><i class="fa-regular fa-calendar mr-2"></i>{{ now()->format('F d, Y') }}</span>
        </div>
    </div>
    {{-- INVENTORY STATUS --}}
    <div class="mb-6 flex flex-col justify-between gap-3 rounded-xl border p-4 text-sm sm:flex-row sm:items-center"
         :class="inventoryLoaded ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : (inventoryLoading ? 'border-blue-200 bg-blue-50 text-blue-800' : 'border-amber-200 bg-amber-50 text-amber-900')">
        <div class="flex items-start gap-3">
            <i class="fa-solid mt-0.5" :class="inventoryLoaded ? 'fa-circle-check' : (inventoryLoading ? 'fa-spinner fa-spin' : 'fa-circle-info')"></i>
            <div>
                <p class="font-semibold" x-text="inventoryLoaded ? 'Calves loaded from Carabao Inventory' : (inventoryLoading ? 'Loading calves from Carabao Inventory...' : 'Carabao Inventory not connected')"></p>
                <p class="mt-1" x-text="inventoryLoaded ? (inventoryCalves.length + ' animal(s) with a Dam ID are listed here automatically. Live births added here are saved to Carabao Inventory; stillbirth and abortion records are stored in this browser.') : (inventoryError || 'Check /admin/carabao-inventory-api. Only records added on this page are shown.')"></p>
            </div>
        </div>
        <button type="button" @click="loadInventoryCalves()" :disabled="inventoryLoading" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-50"><i class="fa-solid fa-rotate"></i> Refresh</button>
    </div>

    {{-- Summary cards --}}
    <div class="mb-7 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <template x-for="card in summaryCards" :key="card.title"><div class="cbed-card dashboard-stat-card management-summary-card p-5"><div class="flex items-center justify-between"><div><p class="text-sm font-medium text-slate-500" x-text="card.title"></p><h3 class="mt-3 text-3xl font-bold text-slate-800" x-text="card.value"></h3><p class="mt-2 text-xs text-slate-400" x-text="card.description"></p></div><div class="dashboard-stat-icon flex h-12 w-12 items-center justify-center rounded-xl text-xl"><i :class="card.icon"></i></div></div></div></template>
    </div>

    {{-- Distribution panels --}}
    <section class="mb-6 grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="cbed-card dashboard-panel overflow-hidden rounded-xl border p-6"><div class="mb-6"><h2 class="font-bold text-slate-800">Calf Records per Province</h2><p class="mt-1 text-xs text-slate-500">Based on current filters</p></div><div class="space-y-4"><template x-for="d in distribution('province')" :key="d.name"><div><div class="mb-1 flex justify-between gap-3 text-sm"><span x-text="d.name"></span><b x-text="d.count"></b></div><div class="h-2.5 rounded-full bg-slate-100"><div class="h-2.5 rounded-full bg-blue-700 transition-all" :style="`width:${d.percent}%`"></div></div></div></template><p x-show="!distribution('province').length" class="text-sm text-slate-500">No records found.</p></div></div>
        <div class="cbed-card dashboard-panel overflow-hidden rounded-xl border p-6"><div class="mb-6"><h2 class="font-bold text-slate-800">Calves Born per Year</h2><p class="mt-1 text-xs text-slate-500">Counts are grouped by Date of Birth and follow the current filters, except Reported Year.</p></div><div class="space-y-4"><template x-for="item in birthYearSummary" :key="item.year"><div><div class="mb-1 flex justify-between gap-3 text-sm"><span x-text="item.year"></span><b x-text="item.count"></b></div><div class="h-2.5 rounded-full bg-slate-100"><div class="h-2.5 rounded-full bg-blue-700 transition-all" :style="`width:${item.percent}%`"></div></div></div></template><p x-show="birthYearSummary.length===0" class="text-sm text-slate-500">No calf birth dates found.</p></div></div>
    </section>

    {{-- Directory and filters --}}
    <section class="cbed-card dashboard-panel overflow-hidden rounded-xl border">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-lg font-bold text-slate-900">Calf Records</h2><p class="mt-1 text-sm text-slate-500">Search, filter, view, import, export, or add calf records.</p></div><div class="flex flex-wrap items-center gap-2"><span class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700"><span x-text="filteredRecords.length"></span> records</span><button type="button" @click="resetFilters()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"><i class="fa-solid fa-rotate-left mr-1"></i> Reset Filters</button></div></div>
        <div class="grid grid-cols-1 gap-3 border-b border-slate-200 bg-slate-50 p-5 sm:grid-cols-2 xl:grid-cols-6">
            <div class="xl:col-span-2"><label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search Records</label><div class="relative"><i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i><input type="search" x-model="search" @input="page=1" placeholder="Calf ID, mother ID, farmer, sire..." class="filter-input pl-9"></div></div>
            <div><label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Province</label><select x-model="filterProvince" @change="page=1" class="filter-input"><option value="">All Provinces</option><template x-for="v in options('province')" :key="v"><option :value="v" x-text="v"></option></template></select></div>
            <div><label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Breed</label><select x-model="filterBreed" @change="page=1" class="filter-input"><option value="">All Breeds</option><template x-for="v in options('breed')" :key="v"><option :value="v" x-text="v"></option></template></select></div>
            <div><label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Sex</label><select x-model="filterSex" @change="page=1" class="filter-input"><option value="">All Calf Sexes</option><option value="Male">Male</option><option value="Female">Female</option></select></div>
            <div><label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Reported Year</label><select x-model="filterYear" @change="page=1" class="filter-input"><option value="">All Years</option><template x-for="v in options('reported_year')" :key="v"><option :value="String(v)" x-text="v"></option></template></select></div>
        </div>
        <div class="overflow-x-auto"><table class="w-full min-w-[1900px] text-left text-sm"><thead class="bg-slate-100 text-xs uppercase tracking-wider text-slate-600"><tr><th class="px-4 py-4">No.</th><th class="px-4 py-4">Calf ID</th><th class="px-4 py-4">Mother ID</th><th class="px-4 py-4">Farmer</th><th class="px-4 py-4">Province</th><th class="px-4 py-4">Cooperative</th><th class="px-4 py-4">Birth / Event Date</th><th class="px-4 py-4">Sex</th><th class="px-4 py-4">Breed</th><th class="px-4 py-4">Sire / Bull ID</th><th class="px-4 py-4">Reported Year</th><th class="px-4 py-4">Outcome</th><th class="px-4 py-4">Animal Status</th><th class="px-4 py-4">Actions</th></tr></thead><tbody class="divide-y divide-slate-100">
            <template x-for="(r, i) in paginatedRecords()" :key="r.id"><tr class="hover:bg-blue-50/50"><td class="px-4 py-4 text-slate-500" x-text="(page-1)*perPage+i+1"></td><td class="px-4 py-4 font-bold text-slate-900"><span x-text="r.calf_id || '—'"></span><span x-show="r.source==='Inventory'" class="ml-2 rounded-full bg-sky-50 px-2 py-0.5 text-[.65rem] font-semibold uppercase text-sky-700" title="Listed automatically from the Carabao Inventory (has a Dam ID)">Inventory</span></td><td class="px-4 py-4" x-text="r.mother_id || '—'"></td><td class="px-4 py-4" x-text="r.farmer || '—'"></td><td class="px-4 py-4" x-text="r.province || '—'"></td><td class="px-4 py-4" x-text="r.cooperative || '—'"></td><td class="px-4 py-4" x-text="r.date_of_birth || r.monitored_date || '—'"></td><td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="r.sex==='Male'?'bg-blue-50 text-blue-700':r.sex==='Female'?'bg-pink-50 text-pink-700':'bg-slate-100 text-slate-600'" x-text="r.sex || '—'"></span></td><td class="px-4 py-4" x-text="r.breed || '—'"></td><td class="px-4 py-4" x-text="r.sire_id || '—'"></td><td class="px-4 py-4" x-text="r.reported_year || '—'"></td><td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="r.birth_outcome==='Stillbirth'?'bg-rose-50 text-rose-700':r.birth_outcome==='Abortion'?'bg-amber-50 text-amber-700':'bg-emerald-50 text-emerald-700'" x-text="r.birth_outcome || 'Live Birth'"></span></td><td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="r.animal_status==='Live'?'bg-emerald-50 text-emerald-700':r.animal_status==='Out'?'bg-slate-100 text-slate-700':r.animal_status==='Dead'?'bg-rose-50 text-rose-700':'bg-amber-50 text-amber-700'" x-text="r.animal_status || '—'"></span></td><td class="whitespace-nowrap px-4 py-4"><button type="button" @click="view(r)" class="mr-1 rounded-lg border border-slate-200 px-3 py-2 hover:bg-blue-50" title="View details"><i class="fa-solid fa-eye"></i></button><button type="button" x-show="r.source!=='Inventory'" @click="remove(r.id)" class="rounded-lg px-3 py-2 text-red-600 hover:bg-red-50" title="Delete record"><i class="fa-solid fa-trash"></i></button></td></tr></template>
            <tr x-show="filteredRecords.length===0"><td colspan="14" class="px-5 py-12 text-center text-slate-500"><i class="fa-solid fa-folder-open mb-2 block text-2xl text-slate-300"></i>No calf drop records found. Change your filters or add records.</td></tr>
        </tbody></table></div>
        <div class="flex flex-col justify-between gap-3 border-t border-slate-200 px-5 py-4 sm:flex-row sm:items-center"><p class="text-sm text-slate-500">Showing <b x-text="filteredRecords.length ? ((page-1)*perPage+1) : 0"></b>–<b x-text="Math.min(page*perPage,filteredRecords.length)"></b> of <b x-text="filteredRecords.length"></b> filtered records (<b x-text="allRecords.length"></b> total)</p><div class="flex items-center gap-2"><button type="button" @click="page=Math.max(1,page-1)" :disabled="page<=1" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-40">Previous</button><span class="px-2 text-sm text-slate-600">Page <b x-text="page"></b> of <b x-text="totalPages()"></b></span><button type="button" @click="page=Math.min(totalPages(),page+1)" :disabled="page>=totalPages()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-40">Next</button></div></div>
    </section>
    <footer class="py-5 text-center text-xs text-slate-400">CBED Data System · Calf Records</footer>

    {{-- Bulk Add modal --}}
    <div x-cloak x-show="showBulk" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-5">
        <div @click.outside="showBulk = false" class="max-h-[94vh] w-full max-w-[96vw] overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 bg-white p-5"><div class="min-w-0"><h3 class="text-lg font-bold text-slate-900 sm:text-xl">Bulk Add Calf Drop Records</h3><p class="mt-1 text-sm text-slate-500">Enter multiple calf records below. Each row represents one calf.</p><div class="mt-3 flex flex-wrap items-center gap-2"><span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-800"><i class="fa-solid fa-list-ol mr-1"></i><span x-text="bulkRows.length"></span> row(s) in this batch</span><span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">Required: Mother ID, Outcome, and Event Date</span></div></div><div class="flex shrink-0 items-center gap-3"><span class="whitespace-nowrap text-sm font-bold text-[#0b1f3a]" x-text="bulkRows.length + ' animal(s)'"></span><button @click="showBulk = false" type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900" aria-label="Close modal"><i class="fa-solid fa-xmark text-xl"></i></button></div></div>
            <div class="max-h-[calc(94vh-110px)] overflow-auto p-4 sm:p-5">
                <div class="mb-4 flex justify-end"><button @click="addRow()" type="button" class="rounded-lg bg-[#0b1f3a] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#122b4d]"><i class="fa-solid fa-plus mr-2"></i>Add Calf</button></div>
                <div class="overflow-x-auto rounded-xl border border-slate-200"><table class="min-w-[2200px] w-full text-left text-sm"><thead class="bg-slate-100 text-xs uppercase tracking-wider text-slate-600"><tr><th class="p-3">Calf ID *</th><th class="p-3">Mother ID *</th><th class="p-3">Farmer</th><th class="p-3">Province</th><th class="p-3">Cooperative</th><th class="p-3">Date of Birth</th><th class="p-3">Sex *</th><th class="p-3">Breed</th><th class="p-3">Sire / Bull ID</th><th class="p-3">Monitored Date</th><th class="p-3">Reported Year</th><th class="p-3">Birth Outcome *</th><th class="p-3">Animal Status</th><th class="p-3">Breed Series</th><th class="p-3">Remarks</th><th class="p-3">Action</th></tr></thead><tbody class="divide-y divide-slate-100">
                    <template x-for="(r, i) in bulkRows" :key="r._key"><tr class="align-top">
                        <td class="min-w-36 p-2"><input x-model.trim="r.calf_id" class="form-input" placeholder="Calf ID"></td><td class="min-w-36 p-2"><input x-model.trim="r.mother_id" @input="refreshGeneratedId(r)" class="form-input" placeholder="Mother animal ID"></td><td class="min-w-40 p-2"><input x-model="r.farmer" class="form-input" placeholder="Farmer name"></td>
                        <td class="min-w-40 p-2"><select x-model="r.province" class="form-input"><option value="">Select Province</option><template x-for="v in provinceOptions" :key="v"><option :value="v" x-text="v"></option></template></select></td><td class="min-w-44 p-2"><input x-model="r.cooperative" class="form-input" placeholder="Cooperative"></td><td class="min-w-40 p-2"><input type="date" x-model="r.date_of_birth" class="form-input"></td><td class="min-w-32 p-2"><select x-model="r.sex" class="form-input"><option value="">Select Sex</option><option value="Male">Male</option><option value="Female">Female</option></select></td><td class="min-w-36 p-2"><select x-model="r.breed" class="form-input"><option value="">Select Breed</option><template x-for="v in breedOptions" :key="v"><option :value="v" x-text="v"></option></template></select></td><td class="min-w-36 p-2"><input x-model="r.sire_id" class="form-input" placeholder="Sire / Bull ID"></td><td class="min-w-40 p-2"><input type="date" x-model="r.monitored_date" class="form-input"></td><td class="min-w-32 p-2"><input type="number" min="2000" max="2100" x-model="r.reported_year" @input="refreshGeneratedId(r)" class="form-input" placeholder="2026"></td><td class="min-w-40 p-2"><select x-model="r.birth_outcome" @change="refreshGeneratedId(r)" class="form-input"><option value="Live Birth">Live Birth</option><option value="Stillbirth">Stillbirth (SB)</option><option value="Abortion">Abortion (AB)</option></select></td><td class="min-w-36 p-2"><select x-model="r.animal_status" class="form-input"><option value="Live">Live</option><option value="Out">Out</option><option value="Dead">Dead</option><option value="Not Applicable">Not Applicable</option></select></td><td class="min-w-28 p-2"><input x-model.trim="r.breed_series" @input="refreshGeneratedId(r)" class="form-input" placeholder="6"></td><td class="min-w-48 p-2"><input x-model="r.remarks" class="form-input" placeholder="Remarks"></td><td class="p-2 text-center"><button @click="removeRow(i)" type="button" class="rounded-lg px-3 py-2 text-red-600 hover:bg-red-50" title="Remove row"><i class="fa-solid fa-trash"></i></button></td>
                    </tr></template><tr x-show="bulkRows.length === 0"><td colspan="16" class="px-4 py-10 text-center text-slate-500">No calf rows. Click “Add Calf” to begin.</td></tr>
                </tbody></table></div>
                <div class="mt-4 flex flex-col gap-3 rounded-xl bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-sm font-semibold text-slate-800">Batch total: <span x-text="bulkRows.length"></span> animal row(s)</p><p class="mt-1 text-xs text-slate-500">Rows need Mother ID and Event Date. Live births also need Sex. Stillbirth IDs use SB; abortion IDs use AB.</p></div><div class="flex justify-end gap-2"><button @click="showBulk = false" type="button" class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold hover:bg-slate-100">Cancel</button><button @click="saveBulk()" type="button" class="rounded-lg bg-[#0b1f3a] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#122b4d]"><i class="fa-solid fa-floppy-disk mr-2"></i>Save All</button></div></div>
            </div>
        </div>
    </div>

    {{-- View modal --}}
    <div x-cloak x-show="showView" x-transition.opacity class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/60 p-4"><div @click.outside="showView = false" class="w-full max-w-3xl overflow-hidden rounded-xl bg-white shadow-xl"><div class="flex items-center justify-between border-b border-slate-200 p-5"><div><h3 class="text-lg font-bold">Calf Drop Record Details</h3><p class="mt-1 text-sm text-slate-500">Full calf birth record</p></div><button @click="showView = false" type="button" class="rounded-lg p-2 hover:bg-slate-100"><i class="fa-solid fa-xmark"></i></button></div><div class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3"><template x-for="f in fields" :key="f"><div class="rounded-lg border border-slate-200 p-3"><p class="text-xs text-slate-500" x-text="label(f)"></p><p class="mt-1 break-words font-semibold text-slate-800" x-text="selected && selected[f] != null && selected[f] !== '' ? selected[f] : '-' "></p></div></template></div><div class="flex justify-end border-t border-slate-200 p-4"><button @click="showView = false" type="button" class="rounded-lg bg-[#0b1f3a] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#122b4d]">Close</button></div></div></div>
    <div x-cloak x-show="notice" x-transition class="fixed bottom-5 right-5 z-[70] max-w-sm rounded-xl px-5 py-3 text-sm font-medium text-white shadow-xl" x-text="notice"></div>
</div>
@endsection

@push('styles')
<style>
    [x-cloak]{display:none!important}
    .calf-records-page { background:radial-gradient(circle at 92% 0%,rgba(11,31,58,.06),transparent 25%),#f5f8fc; }
    .calf-records-page .management-hero { position:relative;isolation:isolate;overflow:hidden;background:radial-gradient(circle at 90% 18%,rgba(255,196,56,.25),transparent 19%),linear-gradient(118deg,#082d50 0%,#075785 55%,#0786a5 100%);box-shadow:0 16px 32px rgba(7,62,99,.2); }
    .calf-records-page .management-hero::after { content:"";position:absolute;z-index:-1;width:19rem;height:19rem;right:11%;top:-14rem;border:1px solid rgba(255,214,92,.45);border-radius:50%;box-shadow:0 0 0 26px rgba(255,214,92,.08),0 0 0 54px rgba(255,255,255,.045); }
    .calf-records-page .management-hero h1,.calf-records-page .management-hero p,.calf-records-page .management-hero span { color:#fff!important; }
    .calf-records-page .management-hero button,.calf-records-page .management-hero label { border-color:rgba(255,255,255,.32)!important;background:rgba(255,255,255,.12)!important;color:#fff!important;backdrop-filter:blur(8px);cursor:pointer; }
    .calf-records-page .management-hero button:hover,.calf-records-page .management-hero label:hover { background:rgba(255,255,255,.22)!important; }
    .calf-records-page .management-summary-card { border-top:4px solid #0b5e8b!important;border-radius:1rem!important;box-shadow:0 5px 18px rgba(15,45,67,.06)!important;transition:transform .16s ease,box-shadow .16s ease; }
    .calf-records-page .management-summary-card:nth-child(2) { border-top-color:#e87523!important; }
    .calf-records-page .management-summary-card:nth-child(3) { border-top-color:#25835b!important; }
    .calf-records-page .management-summary-card:nth-child(4) { border-top-color:#0786a5!important; }
    .calf-records-page .management-summary-card:hover { transform:translateY(-3px);box-shadow:0 14px 28px rgba(7,62,99,.13)!important; }
    .calf-records-page .management-summary-card .dashboard-stat-icon { background:linear-gradient(145deg,#082d50,#0b6d9b)!important;color:#fff!important;border-radius:1rem;box-shadow:0 8px 18px rgba(7,62,99,.2); }
    .calf-records-page .management-summary-card:nth-child(2) .dashboard-stat-icon { background:linear-gradient(145deg,#a94b0b,#f28a32)!important; }
    .calf-records-page .management-summary-card:nth-child(3) .dashboard-stat-icon { background:linear-gradient(145deg,#176342,#38a36f)!important; }
    .calf-records-page .management-summary-card:nth-child(4) .dashboard-stat-icon { background:linear-gradient(145deg,#075785,#0798b6)!important; }
    .calf-records-page .dashboard-panel { border-color:#dce6ed!important;border-radius:1rem!important;box-shadow:0 7px 24px rgba(15,45,67,.06)!important; }
    .form-input,.filter-input{width:100%;border:1px solid #cbd5e1;border-radius:.5rem;background:#fff;padding:.625rem .75rem;font-size:.875rem;color:#1e293b;outline:none}
    .form-input:focus,.filter-input:focus{border-color:#1d4ed8;box-shadow:0 0 0 2px #dbeafe}
</style>
@endpush

@push('scripts')
<script>
function calfDropPage() {
    return {
        storageKey: 'cbed_calfdrop_records', records: [], inventoryCalves: [], inventoryLoaded: false, inventoryLoading: false, inventoryError: '', search: '', filterProvince: '', filterBreed: '', filterSex: '', filterYear: '',
        showBulk: false, showView: false, bulkRows: [], selected: null, notice: '', rowKey: 0, noticeTimer: null, page: 1, perPage: 10,
        provinceOptions: ['Aurora','Bataan','Bulacan','Nueva Ecija','Pampanga','Tarlac','Zambales'],
        breedOptions: ['RV','SP','BrMB','BMB','CB50','Crossbred','Other'],
        fields: ['calf_id','mother_id','farmer','province','cooperative','date_of_birth','sex','breed','sire_id','monitored_date','reported_year','birth_outcome','animal_status','breed_series','remarks'],
        init() {
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
            this.loadInventoryCalves();
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
        get allRecords() { const manual = new Set(this.records.map(r => String(r.calf_id || '').trim().toLowerCase()).filter(Boolean)); return [...this.records, ...this.inventoryCalves.filter(c => !manual.has(String(c.calf_id || '').trim().toLowerCase()))]; },
        // An inventory animal is a calf when it has a Dam ID (mother). Placeholder values do not count.
        hasDam(a) { const v = String(a?.dam_id ?? '').trim().toLowerCase(); return v !== '' && !['-','--','n/a','na','none','unknown','0'].includes(v); },
        normalizeProvince(value) { const text = String(value ?? '').replace(/\s+/g,' ').trim(); if (!text) return ''; const aliases = {nuevaecija:'Nueva Ecija', nuevaecjia:'Nueva Ecija', nuevaecijaprovince:'Nueva Ecija'}; return aliases[text.toLowerCase().replace(/[^a-z]/g,'')] || text; },
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
                id: 'inv-' + a.id, source: 'Inventory',
                calf_id: String(a.registry_number || a.bolus_id || a.tag_no || a.animal_id || a.id).trim(),
                mother_id: String(a.dam_id).trim(), farmer, province: this.normalizeProvince(a.province), cooperative,
                date_of_birth: dob, sex, breed: String(a.breed ?? '').trim(), sire_id: String(a.sire_id ?? '').trim(),
                monitored_date: '', reported_year: /^\d{4}$/.test(dob.slice(0,4)) ? dob.slice(0,4) : '',
                birth_outcome: 'Live Birth', animal_status, breed_series: '',
                remarks: [a.dam_name ? 'Dam: ' + a.dam_name : '', a.sire_name ? 'Sire: ' + a.sire_name : '', a.remarks || ''].filter(Boolean).join(' · ')
            };
        },
        async loadInventoryCalves() {
            this.inventoryLoading = true; this.inventoryError = '';
            const readList = async url => { const res = await fetch(url, {headers:{Accept:'application/json'}}); if (!res.ok) throw new Error(`${url} returned HTTP ${res.status}`); const p = await res.json(); const list = Array.isArray(p) ? p : (p?.data ?? p?.records ?? p?.farmers ?? p?.cooperatives ?? []); if (!Array.isArray(list)) throw new Error(`${url} did not return a list.`); return list; };
            try {
                const animals = await readList('/admin/carabao-inventory-api');
                let coops = [], farmers = [];
                try { [coops, farmers] = await Promise.all([readList('/admin/cooperatives/data'), readList('/admin/farmers/data')]); } catch (e) { console.warn('Farmer/cooperative names unavailable:', e); }
                const seenCalfIds=new Set();
                this.inventoryCalves=animals.filter(a=>this.hasDam(a)).map(a=>this.inventoryToCalf(a,coops,farmers))
                    .filter(calf=>{
                        const id=String(calf.calf_id||'').trim().toLowerCase();
                        if(!id||seenCalfIds.has(id))return false;
                        seenCalfIds.add(id);
                        return true;
                    })
                    .sort((a,b)=>String(b.date_of_birth||'').localeCompare(String(a.date_of_birth||'')));
                this.inventoryLoaded = true; this.page = 1;
            } catch (e) {
                this.inventoryLoaded = false; this.inventoryCalves = [];
                this.inventoryError = e.message || 'Could not load the Carabao Inventory.';
                console.error('Calf Drop inventory load failed:', e);
            } finally { this.inventoryLoading = false; }
        },
        get filteredRecords() { const q = this.search.toLowerCase().trim(); return this.allRecords.filter(r => { const matchesSearch = !q || this.fields.some(f => String(r[f] ?? '').toLowerCase().includes(q)); return matchesSearch && (!this.filterProvince || r.province === this.filterProvince) && (!this.filterBreed || r.breed === this.filterBreed) && (!this.filterSex || r.sex === this.filterSex) && (!this.filterYear || String(r.reported_year) === this.filterYear); }); },
        get summaryCards() { const list = this.filteredRecords; return [
            {title:'Total Calves',value:list.length,description:'Records matching filters',icon:'fa-solid fa-list-check',color:'text-slate-900'},
            {title:'Male Calves',value:list.filter(r=>r.sex==='Male').length,description:'Male calves recorded',icon:'fa-solid fa-mars',color:'text-blue-700'},
            {title:'Female Calves',value:list.filter(r=>r.sex==='Female').length,description:'Female calves recorded',icon:'fa-solid fa-venus',color:'text-pink-700'},
            {title:'Breeds',value:new Set(list.map(r=>r.breed).filter(Boolean)).size,description:'Breeds represented',icon:'fa-solid fa-dna',color:'text-emerald-700'}
        ]; },
        get birthYearSummary() {
            const counts=new Map();
            const query=this.search.toLowerCase().trim();
            const records=this.allRecords.filter(record=>{
                const matchesSearch=!query||this.fields.some(field=>String(record[field]??'').toLowerCase().includes(query));
                return matchesSearch
                    &&(!this.filterProvince||record.province===this.filterProvince)
                    &&(!this.filterBreed||record.breed===this.filterBreed)
                    &&(!this.filterSex||record.sex===this.filterSex);
            });
            records.forEach(record=>{
                const date=String(record.date_of_birth??'').slice(0,10);
                if(!/^\d{4}-\d{2}-\d{2}$/.test(date))return;
                const parsed=new Date(`${date}T00:00:00`);
                if(Number.isNaN(parsed.getTime())||parsed.getFullYear()!==Number(date.slice(0,4))
                    ||parsed.getMonth()+1!==Number(date.slice(5,7))||parsed.getDate()!==Number(date.slice(8,10)))return;
                const year=date.slice(0,4);
                if(!counts.has(year))counts.set(year,new Set());
                counts.get(year).add(String(record.calf_id||record.id));
            });
            const years=[...counts.entries()].map(([year,ids])=>({year,count:ids.size}));
            const max=Math.max(1,...years.map(item=>item.count));
            return years.map(item=>({...item,percent:item.count/max*100})).sort((a,b)=>Number(b.year)-Number(a.year));
        },
        totalPages() { return Math.max(1, Math.ceil(this.filteredRecords.length / this.perPage)); },
        paginatedRecords() { const list=this.filteredRecords; if(this.page>this.totalPages()) this.page=this.totalPages(); return list.slice((this.page-1)*this.perPage,this.page*this.perPage); },
        options(field) { return [...new Set(this.allRecords.map(r=>String(r[field] ?? '').trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b,undefined,{numeric:true})); },
        distribution(field) { const counts={}; this.filteredRecords.forEach(r=>{const name=String(r[field]||'Not specified');counts[name]=(counts[name]||0)+1;}); const max=Math.max(1,...Object.values(counts)); return Object.entries(counts).map(([name,count])=>({name,count,percent:count/max*100})).sort((a,b)=>b.count-a.count||a.name.localeCompare(b.name)); },
        label(field) { const labels={calf_id:'Calf ID',mother_id:'Mother ID',farmer:'Farmer Name',province:'Province',cooperative:'Cooperative',date_of_birth:'Date of Birth / Event Date',sex:'Sex',breed:'Breed',sire_id:'Sire / Bull ID',monitored_date:'Monitored Date',reported_year:'Reported Year',birth_outcome:'Birth Outcome',animal_status:'Animal Status',breed_series:'Breed Series',remarks:'Remarks'}; return labels[field]||field; },
        generatedId(r) { const mother=String(r.mother_id||'').trim().replace(/\s+/g,'-'); const year=String(r.reported_year|| (r.date_of_birth ? r.date_of_birth.slice(0,4) : new Date().getFullYear())).slice(-2); const series=String(r.breed_series||'6').trim()||'6'; if (!mother || r.birth_outcome === 'Live Birth') return ''; const code=r.birth_outcome==='Stillbirth'?'SB':'AB'; return `${series}-${code}-${year}-${mother}`; },
        refreshGeneratedId(r) { const id=this.generatedId(r); if(id) r.calf_id=id; else if(r.birth_outcome==='Live Birth' && /-(SB|AB)-/.test(String(r.calf_id||''))) r.calf_id=''; },
        emptyRow() { return {_key:++this.rowKey,calf_id:'',mother_id:'',farmer:'',province:'',cooperative:'',date_of_birth:'',sex:'',breed:'',sire_id:'',monitored_date:new Date().toISOString().slice(0,10),reported_year:String(new Date().getFullYear()),birth_outcome:'Live Birth',animal_status:'Live',breed_series:'6',remarks:''}; },
        openBulk() { this.bulkRows=[this.emptyRow()]; this.showBulk=true; }, addRow() { this.bulkRows.push(this.emptyRow()); }, removeRow(index) { this.bulkRows.splice(index,1); },
        persist() { try { localStorage.setItem(this.storageKey,JSON.stringify(this.records)); return true; } catch(e) { this.msg('Unable to save browser data. Check browser storage settings.'); return false; } },
        async saveBulk() {
            const valid=this.bulkRows.filter(r=>
                String(r.mother_id||'').trim()
                &&String(r.date_of_birth||r.monitored_date||'').trim()
                &&(r.birth_outcome!=='Live Birth'||String(r.sex||'').trim())
            );
            valid.forEach(r=>{
                const generated=this.generatedId(r);
                if(generated)r.calf_id=generated;
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
                if(row.birth_outcome!=='Live Birth'){
                    const record={...row};
                    delete record._key;
                    record.calf_id=String(record.calf_id||'').trim();
                    record.mother_id=String(record.mother_id).trim();
                    record.id='calf-'+Date.now()+'-'+Math.random().toString(36).slice(2,9);
                    localRecords.push(record);
                    savedRows.add(row._key);
                    continue;
                }

                try{
                    const csrf=document.querySelector('meta[name="csrf-token"]')?.content;
                    const response=await fetch('/admin/calf-drop-api',{
                        method:'POST',
                        headers:{Accept:'application/json','Content-Type':'application/json',...(csrf?{'X-CSRF-TOKEN':csrf}:{})},
                        body:JSON.stringify({
                            calf_id:row.calf_id||null,
                            mother_id:row.mother_id,
                            date_of_birth:row.date_of_birth||row.monitored_date,
                            sex:row.sex,
                            animal_status:row.animal_status||'Live',
                            breed:row.breed||null,
                            sire_id:row.sire_id||null,
                            province:row.province||null,
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
                    console.error('Could not add live calf to inventory:',error);
                }
            }

            if(localRecords.length){
                const previous=this.records;
                this.records=[...localRecords,...this.records];
                if(!this.persist()){
                    this.records=previous;
                    this.bulkRows=this.bulkRows.filter(row=>!savedLiveRows.has(row._key));
                    if(savedLiveRows.size)await this.loadInventoryCalves();
                    this.showBulk=true;
                    this.msg('Live births were sent to inventory, but non-live birth records could not be saved in this browser.');
                    return;
                }
            }
            this.bulkRows=this.bulkRows.filter(row=>!savedRows.has(row._key));
            if(savedRows.size){
                await this.loadInventoryCalves();
                this.page=1;
            }
            if(failures.length){
                this.showBulk=true;
                this.msg(`Saved ${savedRows.size} record(s). ${failures.join(' ')}`);
                return;
            }
            this.showBulk=false;
            this.msg(`Saved ${savedRows.size} calf record(s). Live births are now in Carabao Inventory.`);
        },
        view(record) { this.selected={...record}; this.showView=true; }, remove(id) { if(!confirm('Delete this calf drop record?'))return;const previous=this.records;this.records=this.records.filter(r=>r.id!==id);if(this.persist())this.msg('Calf record deleted.');else this.records=previous; },
        resetFilters() { this.search='';this.filterProvince='';this.filterBreed='';this.filterSex='';this.filterYear='';this.page=1; },
        msg(message) { this.notice=message;clearTimeout(this.noticeTimer);this.noticeTimer=setTimeout(()=>{this.notice='';},3500); },
        exportCSV() { const escapeCSV=value=> '"'+String(value??'').replace(/"/g,'""')+'"';const lines=[this.fields.map(escapeCSV).join(','),...this.filteredRecords.map(r=>this.fields.map(f=>escapeCSV(r[f])).join(','))];const blob=new Blob(['\uFEFF'+lines.join('\r\n')],{type:'text/csv;charset=utf-8;'});const url=URL.createObjectURL(blob);const link=document.createElement('a');link.href=url;link.download='calfdrop_records.csv';document.body.appendChild(link);link.click();link.remove();URL.revokeObjectURL(url); },
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
                        if(generated)row.calf_id=generated;
                        if(String(row.mother_id||'').trim()&&String(row.date_of_birth||row.monitored_date||'').trim()&&(row.birth_outcome!=='Live Birth'||String(row.sex||'').trim())){
                            if(row.birth_outcome==='Stillbirth')row.animal_status='Dead';
                            else if(row.birth_outcome==='Abortion')row.animal_status='Not Applicable';
                            imported.push(row);
                        }
                    });
                    if(!imported.length){this.msg('No valid rows found. Check Calf ID, Mother ID, and Sex columns.');return;}
                    this.bulkRows=imported;
                    await this.saveBulk();
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