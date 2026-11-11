{{-- resources/views/semen-bull.blade.php --}}
@extends('layouts.admin')
@section('title', 'Semen Bull Management | CBED')
@section('page-heading', 'Semen Bull Management')

@section('content')
<div x-data="semenBullPage()" x-init="init()" x-cloak class="semen-bull-page min-w-0 bg-slate-50 text-slate-800">
    {{-- PAGE HEADER: matching Breeding / Carabao Management UI --}}
    <div class="dashboard-hero mb-7 flex flex-col justify-between gap-4 rounded-2xl p-5 text-white shadow-lg sm:flex-row sm:items-center sm:p-6">
        <div>
            <div class="mb-2 flex flex-wrap items-center gap-2 text-sm text-blue-100"><i class="fa-solid fa-house"></i><span>/</span><span>CBED Data System</span><span>/</span><span class="font-semibold text-white">Semen Bull Management</span></div>
            <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">Semen Bull Management</h1>
            <p class="mt-2 text-sm text-slate-200">Manage semen bull profiles, registration details, and recorded semen usage.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="exportXLSX()" class="inline-flex items-center gap-2 rounded-lg border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-white/20"><i class="fa-solid fa-file-export"></i> Export XLSX</button>
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-white/20"><i class="fa-solid fa-file-import"></i> Import XLSX<input type="file" accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" class="hidden" @change="importXLSX($event)"></label>
            <button type="button" @click="openAdd()" class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2.5 text-sm font-bold text-[#0b1f3a] shadow-sm hover:bg-blue-50"><i class="fa-solid fa-plus"></i> Add Semen Bull</button>
        </div>
    </div>

    {{-- SUMMARY CARDS --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <template x-for="card in summaryCards()" :key="card.title">
            <div class="cbed-card dashboard-stat-card p-5"><div class="flex items-center justify-between gap-3"><div><p class="text-sm font-medium text-slate-500" x-text="card.title"></p><p class="mt-2 text-3xl font-bold text-slate-800" x-text="card.value"></p><p class="mt-1 text-xs text-slate-400" x-text="card.description"></p></div><div class="dashboard-stat-icon flex h-12 w-12 items-center justify-center rounded-xl" :class="card.iconBg"><i :class="card.icon + ' text-xl ' + card.iconColor"></i></div></div></div>
        </template>
    </div>

    {{-- SEMEN BULL DIRECTORY --}}
    <section class="cbed-card dashboard-panel overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div><h2 class="text-lg font-bold text-[#0b1f3a]">Semen Bull Records</h2><p class="mt-1 text-sm text-slate-500">Search, filter, view, import, export, or add semen bull profiles using Excel workbooks.</p></div>
            <div class="flex flex-wrap items-center gap-2"><span class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700"><span x-text="filteredBulls().length"></span> records</span><button type="button" @click="resetFilters()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"><i class="fa-solid fa-rotate-left mr-1"></i> Reset Filters</button><button type="button" @click="fetchBulls()" :disabled="loading" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50"><i class="fa-solid fa-rotate mr-1" :class="loading ? 'animate-spin' : ''"></i> Refresh</button></div>
        </div>

        {{-- FILTERS --}}
        <div class="grid grid-cols-1 gap-3 border-b border-slate-200 bg-slate-50 p-5 sm:grid-cols-2 xl:grid-cols-5">
            <div class="xl:col-span-2"><label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search Records</label><div class="relative"><i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i><input type="search" x-model="search" @input="page=1" placeholder="Bull ID, name, breed, registration..." class="filter-input pl-9"></div></div>
            <div><label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Breed</label><select x-model="filterBreed" @change="page=1" class="filter-input"><option value="">All Breeds</option><template x-for="breed in breeds()" :key="breed"><option :value="breed" x-text="breed"></option></template></select></div>
            <div><label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label><select x-model="filterStatus" @change="page=1" class="filter-input"><option value="">All Statuses</option><option value="Active">Active</option><option value="Inactive">Inactive</option><option value="Retired">Retired</option></select></div>
            <div><label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Source</label><input type="search" x-model="filterSource" @input="page=1" placeholder="Filter by source..." class="filter-input"></div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1450px] text-left text-sm">
                <thead class="bg-[#0b1f3a] text-xs uppercase tracking-wider text-white"><tr><th class="px-4 py-4">No.</th><th class="px-4 py-4">Bull ID</th><th class="px-4 py-4">Bull Name</th><th class="px-4 py-4">Breed</th><th class="px-4 py-4">Registration No.</th><th class="px-4 py-4">Date of Birth</th><th class="px-4 py-4">Source</th><th class="px-4 py-4">Sire</th><th class="px-4 py-4">Dam</th><th class="px-4 py-4">Semen Used</th><th class="px-4 py-4">Status</th><th class="px-4 py-4">Actions</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-for="(bull, i) in paginatedBulls()" :key="bull.key"><tr class="hover:bg-blue-50/50">
                        <td class="px-4 py-4 text-slate-500" x-text="(page - 1) * perPage + i + 1"></td>
                        <td class="px-4 py-4 font-bold text-slate-900" x-text="bull.id || '—'"></td>
                        <td class="px-4 py-4 font-semibold" x-text="bull.name || '—'"></td>
                        <td class="px-4 py-4" x-text="bull.breed || '—'"></td>
                        <td class="px-4 py-4" x-text="bull.registration || '—'"></td>
                        <td class="px-4 py-4" x-text="bull.dob || '—'"></td>
                        <td class="px-4 py-4" x-text="bull.source || '—'"></td>
                        <td class="px-4 py-4" x-text="bull.sire || '—'"></td>
                        <td class="px-4 py-4" x-text="bull.dam || '—'"></td>
                        <td class="px-4 py-4"><span class="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700" x-text="Number(bull.semenUsed || 0).toLocaleString()"></span></td>
                        <td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(bull.status)" x-text="bull.status || 'Active'"></span></td>
                        <td class="whitespace-nowrap px-4 py-4"><button type="button" @click="view(bull)" class="mr-1 rounded-lg border border-slate-200 px-3 py-2 hover:bg-blue-50" title="View details"><i class="fa-solid fa-eye"></i></button><button type="button" @click="openEdit(bull)" class="mr-1 rounded-lg border border-slate-200 px-3 py-2 hover:bg-amber-50" title="Edit record"><i class="fa-solid fa-pen-to-square"></i></button><button type="button" @click="remove(bull)" class="rounded-lg px-3 py-2 text-red-600 hover:bg-red-50" title="Delete record"><i class="fa-solid fa-trash"></i></button></td>
                    </tr></template>
                    <tr x-show="!loading && filteredBulls().length === 0"><td colspan="12" class="px-5 py-12 text-center text-slate-500"><i class="fa-solid fa-folder-open mb-2 block text-2xl text-slate-300"></i><span x-text="errorMessage || 'No semen bull records found. Change your filters or add a record.'"></span></td></tr>
                    <tr x-show="loading"><td colspan="12" class="px-5 py-12 text-center text-slate-500"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Loading database records...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="flex flex-col justify-between gap-3 border-t border-slate-200 px-5 py-4 sm:flex-row sm:items-center"><p class="text-sm text-slate-500">Showing <b x-text="filteredBulls().length ? ((page - 1) * perPage + 1) : 0"></b>–<b x-text="Math.min(page * perPage, filteredBulls().length)"></b> of <b x-text="filteredBulls().length"></b> filtered records (<b x-text="bulls.length"></b> total)</p><div class="flex items-center gap-2"><label class="text-sm text-slate-500">Rows</label><select x-model.number="perPage" @change="page=1" class="filter-input !w-20"><option :value="10">10</option><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select><button type="button" @click="page=Math.max(1,page-1)" :disabled="page<=1" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-40">Previous</button><span class="px-2 text-sm text-slate-600">Page <b x-text="page"></b> of <b x-text="totalPages()"></b></span><button type="button" @click="page=Math.min(totalPages(),page+1)" :disabled="page>=totalPages()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-40">Next</button></div></div>
    </section>
    <footer class="py-5 text-center text-xs text-slate-400">CBED Data System · Semen Bull Management</footer>

    {{-- ADD / EDIT MODAL --}}
    <div x-cloak x-show="showForm" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-5">
        <div @click.outside="showForm=false" class="max-h-[94vh] w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 bg-white p-5"><div><h3 class="text-lg font-bold text-slate-900 sm:text-xl" x-text="editingKey ? 'Edit Semen Bull' : 'Add Semen Bull'"></h3><p class="mt-1 text-sm text-slate-500">Enter the bull profile and semen usage details.</p></div><button type="button" @click="showForm=false" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"><i class="fa-solid fa-xmark text-xl"></i></button></div>
            <form @submit.prevent="save()" class="max-h-[calc(94vh-85px)] overflow-auto">
                <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div><label class="field-label">Bull ID *</label><input x-model.trim="form.id" class="form-input" required maxlength="100" placeholder="e.g. SB-0001"></div>
                    <div><label class="field-label">Bull Name <span class="font-normal text-slate-400">(Optional)</span></label><input x-model.trim="form.name" class="form-input" maxlength="255" placeholder="Leave blank if unknown"></div>
                    <div><label class="field-label">Breed</label><input x-model.trim="form.breed" class="form-input" maxlength="100" placeholder="Breed"></div>
                    <div><label class="field-label">Registration No.</label><input x-model.trim="form.registration" class="form-input" maxlength="100" placeholder="Registration number"></div>
                    <div><label class="field-label">Date of Birth</label><input type="date" x-model="form.dob" class="form-input"></div>
                    <div><label class="field-label">Source</label><input x-model.trim="form.source" class="form-input" maxlength="255" placeholder="Source / institution"></div>
                    <div><label class="field-label">Sire</label><input x-model.trim="form.sire" class="form-input" maxlength="255" placeholder="Sire name or ID"></div>
                    <div><label class="field-label">Dam</label><input x-model.trim="form.dam" class="form-input" maxlength="255" placeholder="Dam name or ID"></div>
                    <div><label class="field-label">Semen Used</label><input type="number" min="0" step="1" x-model.number="form.semenUsed" class="form-input" required></div>
                    <div><label class="field-label">Status *</label><select x-model="form.status" class="form-input" required><option value="Active">Active</option><option value="Inactive">Inactive</option><option value="Retired">Retired</option></select></div>
                    <div class="sm:col-span-2 lg:col-span-3"><label class="field-label">Remarks</label><textarea x-model.trim="form.remarks" rows="3" class="form-input" placeholder="Additional notes..."></textarea></div>
                </div>
                <div class="flex flex-col-reverse gap-2 border-t border-slate-200 bg-slate-50 p-4 sm:flex-row sm:justify-end"><button type="button" @click="showForm=false" class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold hover:bg-slate-100">Cancel</button><button type="submit" :disabled="saving" class="rounded-lg bg-[#0b1f3a] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#122b4d] disabled:opacity-50"><i class="fa-solid fa-floppy-disk mr-2"></i><span x-text="saving ? 'Saving...' : (editingKey ? 'Update Record' : 'Save Record')"></span></button></div>
            </form>
        </div>
    </div>

    {{-- VIEW MODAL --}}
    <div x-cloak x-show="showView" x-transition.opacity class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/60 p-4"><div @click.outside="showView=false" class="w-full max-w-4xl overflow-hidden rounded-xl bg-white shadow-xl"><div class="flex items-center justify-between border-b border-slate-200 p-5"><div><h3 class="text-lg font-bold text-slate-900">Semen Bull Details</h3><p class="mt-1 text-sm text-slate-500">Full registered bull profile</p></div><button type="button" @click="showView=false" class="rounded-lg p-2 hover:bg-slate-100"><i class="fa-solid fa-xmark"></i></button></div><div class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3"><template x-for="field in fields" :key="field"><div class="rounded-lg border border-slate-200 p-3"><p class="text-xs text-slate-500" x-text="label(field)"></p><p class="mt-1 break-words font-semibold text-slate-800" x-text="selected && selected[field] !== '' && selected[field] != null ? selected[field] : '—'"></p></div></template></div><div class="flex justify-end border-t border-slate-200 p-4"><button type="button" @click="showView=false" class="rounded-lg bg-[#0b1f3a] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#122b4d]">Close</button></div></div></div>

    {{-- DELETE CONFIRMATION --}}
    <div x-cloak x-show="showDelete" x-transition.opacity class="fixed inset-0 z-[65] flex items-center justify-center bg-slate-950/60 p-4"><div @click.outside="showDelete=false" class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl"><div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-red-50 text-red-600"><i class="fa-solid fa-trash text-xl"></i></div><h3 class="text-lg font-bold text-slate-900">Delete Semen Bull?</h3><p class="mt-2 text-sm text-slate-600">Delete <b x-text="selected?.name || 'this record'"></b> (<span x-text="selected?.id || ''"></span>)? This action cannot be undone.</p><div class="mt-6 flex justify-end gap-2"><button type="button" @click="showDelete=false" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold">Cancel</button><button type="button" @click="deleteSelected()" :disabled="deleting" class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50" x-text="deleting ? 'Deleting...' : 'Delete Record'"></button></div></div></div>

    {{-- TOAST --}}
    <div x-cloak x-show="notice" x-transition class="fixed bottom-5 right-5 z-[70] max-w-sm rounded-xl px-5 py-3 text-sm font-medium text-white shadow-xl" :class="noticeType==='error'?'bg-rose-700':'bg-slate-900'" x-text="notice"></div>
</div>
@endsection

@push('styles')
<style>
    [x-cloak]{display:none!important}
    .form-input,.filter-input{width:100%;border:1px solid #cbd5e1;border-radius:.5rem;background:#fff;padding:.625rem .75rem;font-size:.875rem;color:#1e293b;outline:none}
    .form-input:focus,.filter-input:focus{border-color:#1d4ed8;box-shadow:0 0 0 2px #dbeafe}
    .field-label{display:block;margin-bottom:.375rem;font-size:.75rem;font-weight:700;color:#475569}
    .dashboard-hero{position:relative;isolation:isolate;overflow:hidden;background:radial-gradient(circle at 88% 10%,rgba(255,255,255,.15),transparent 20%),linear-gradient(115deg,#07152b,#0b1f3a 55%,#1e477c)}
    .dashboard-hero::after{content:"";position:absolute;z-index:-1;width:18rem;height:18rem;right:8%;top:-12rem;border:1px solid rgba(255,255,255,.18);border-radius:50%;box-shadow:0 0 0 28px rgba(255,255,255,.035),0 0 0 58px rgba(255,255,255,.025)}
    .dashboard-stat-card{border:1px solid #dbe4ee;border-top:4px solid #0b1f3a;border-radius:1rem;box-shadow:0 5px 18px rgba(15,23,42,.05);transition:transform .15s ease,box-shadow .15s ease}
    .dashboard-stat-card:hover{transform:translateY(-2px);box-shadow:0 12px 24px rgba(11,31,58,.12)}
    .dashboard-stat-icon{background:linear-gradient(145deg,#07152b,#1e477c)!important;color:#fff!important;border-radius:.85rem;box-shadow:0 7px 15px rgba(11,31,58,.2)}
    .dashboard-stat-icon [class*="text-"]{color:#fff!important}
    .dashboard-panel{border:1px solid #dbe4ee;border-radius:1rem;box-shadow:0 5px 20px rgba(15,23,42,.05)}
    .dashboard-panel h2{color:#0b1f3a}
    .semen-bull-page .dashboard-panel>div:first-child{border-bottom-color:#dbe4ee}
    .semen-bull-page table tbody tr{transition:background-color .15s ease}
    .semen-bull-page table tbody tr:hover{background:#f1f5f9}
    /* PCC-inspired dashboard styling aligned with Mating Attempts */
    .management-page .management-hero,.semen-bull-page .dashboard-hero { background:radial-gradient(circle at 90% 18%,rgba(255,196,56,.25),transparent 19%),linear-gradient(118deg,#082d50 0%,#075785 55%,#0786a5 100%);box-shadow:0 16px 32px rgba(7,62,99,.2); }
    .management-page .management-hero::after,.semen-bull-page .dashboard-hero::after { content:"";position:absolute;z-index:-1;width:19rem;height:19rem;right:11%;top:-14rem;border:1px solid rgba(255,214,92,.45);border-radius:50%;box-shadow:0 0 0 26px rgba(255,214,92,.08),0 0 0 54px rgba(255,255,255,.045); }
    .management-page .management-hero button,.management-page .management-hero a { border-color:rgba(255,255,255,.32)!important;background:rgba(255,255,255,.12)!important;color:#fff!important;backdrop-filter:blur(8px); }
    .management-page .management-hero button:hover,.management-page .management-hero a:hover { background:rgba(255,255,255,.22)!important; }
    .management-page .management-hero button[class*="bg-[#0b1f3a]"] { border-color:#ffd05b!important;background:#ffd05b!important;color:#12345a!important; }
    .management-page .management-hero button[class*="bg-[#0b1f3a]"]:hover { background:#ffdc7d!important; }
    .management-page .management-summary-card,.semen-bull-page .dashboard-stat-card { border-top:4px solid #0b5e8b!important;border-radius:1rem!important;box-shadow:0 5px 18px rgba(15,45,67,.06)!important;transition:transform .16s ease,box-shadow .16s ease; }
    .management-page .management-summary-card:nth-child(2),.semen-bull-page .dashboard-stat-card:nth-child(2) { border-top-color:#e87523!important; }
    .management-page .management-summary-card:nth-child(3),.semen-bull-page .dashboard-stat-card:nth-child(3) { border-top-color:#25835b!important; }
    .management-page .management-summary-card:nth-child(4),.semen-bull-page .dashboard-stat-card:nth-child(4) { border-top-color:#0786a5!important; }
    .management-page .management-summary-card:hover,.semen-bull-page .dashboard-stat-card:hover { transform:translateY(-3px);box-shadow:0 14px 28px rgba(7,62,99,.13)!important; }
    .management-page .management-summary-card > div > div:last-child,.semen-bull-page .dashboard-stat-icon { background:linear-gradient(145deg,#082d50,#0b6d9b)!important;color:#fff!important;border-radius:1rem;box-shadow:0 8px 18px rgba(7,62,99,.2); }
    .management-page .management-summary-card:nth-child(2) > div > div:last-child,.semen-bull-page .dashboard-stat-card:nth-child(2) .dashboard-stat-icon { background:linear-gradient(145deg,#a94b0b,#f28a32)!important; }
    .management-page .management-summary-card:nth-child(3) > div > div:last-child,.semen-bull-page .dashboard-stat-card:nth-child(3) .dashboard-stat-icon { background:linear-gradient(145deg,#176342,#38a36f)!important; }
    .management-page .management-summary-card:nth-child(4) > div > div:last-child,.semen-bull-page .dashboard-stat-card:nth-child(4) .dashboard-stat-icon { background:linear-gradient(145deg,#075785,#0798b6)!important; }
    .management-page .management-summary-card > div > div:last-child [class*="text-"],.semen-bull-page .dashboard-stat-icon [class*="text-"] { color:#fff!important; }
    .management-page .overflow-hidden.rounded-xl.border,.semen-bull-page .dashboard-panel { border-color:#dce6ed!important;border-radius:1rem!important;box-shadow:0 7px 24px rgba(15,45,67,.06)!important; }
    .management-page table thead th,.semen-bull-page table thead th { background:#103c60!important;color:#fff!important;box-shadow:inset 0 -3px 0 #e8b22f; }
    .management-page table tbody tr,.semen-bull-page table tbody tr { transition:background-color .14s ease; }
    .management-page table tbody tr:hover,.semen-bull-page table tbody tr:hover { background:#f0f8fb!important; }
    .management-page input:focus,.management-page select:focus,.management-page textarea:focus,.semen-bull-page input:focus,.semen-bull-page select:focus,.semen-bull-page textarea:focus { border-color:#0786a5!important;box-shadow:0 0 0 3px rgba(7,134,165,.14)!important; }</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
function semenBullPage(){
    return {
        baseUrl: @json(url('/admin/semen-bulls-api')),
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || @json(csrf_token()),
        bulls:[], search:'', filterBreed:'', filterStatus:'', filterSource:'',
        showForm:false, showView:false, showDelete:false, selected:null, editingKey:null,
        loading:false, saving:false, deleting:false, errorMessage:'', notice:'', noticeType:'success', noticeTimer:null,
        page:1, perPage:10,
        fields:['id','name','breed','registration','dob','source','sire','dam','semenUsed','status','remarks'],
        form:{id:'',name:'',breed:'',registration:'',dob:'',source:'',sire:'',dam:'',status:'Active',semenUsed:0,remarks:''},

        async init(){ await this.fetchBulls(); },
        async request(url, options={}){
            const headers={'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':this.csrfToken,...(options.headers||{})};
            const response=await fetch(url,{...options,headers});
            const contentType=response.headers.get('content-type')||'';
            let result;
            if(contentType.includes('application/json')) result=await response.json();
            else result={message:await response.text()||'Unexpected server response.'};
            if(!response.ok || result.success===false){
                let message=result.message||'The request could not be completed.';
                if(result.errors) message=Object.values(result.errors).flat().join(' ');
                throw new Error(message);
            }
            return result;
        },
        async fetchBulls(){
            this.loading=true; this.errorMessage='';
            try{
                let page=1,lastPage=1; const rows=[];
                do{
                    const url=new URL(this.baseUrl,window.location.origin);
                    url.searchParams.set('page',page); url.searchParams.set('per_page','100');
                    const result=await this.request(url.toString());
                    const payload=Array.isArray(result)?result:result.data;
                    const data=Array.isArray(payload)?payload:(payload?.data||[]);
                    data.forEach(row=>rows.push(this.mapRow(row)));
                    lastPage=Array.isArray(payload)?1:Number(payload?.last_page||1);
                    page++;
                }while(page<=lastPage);
                this.bulls=rows; this.page=Math.min(this.page,this.totalPages());
            }catch(error){this.errorMessage=error.message||'Unable to load database records.';this.msg(this.errorMessage,'error');}
            finally{this.loading=false;}
        },
        mapRow(row){
            let metadata={};
            try{metadata=typeof row.remarks==='string'?JSON.parse(row.remarks):{};}catch(error){}
            const metadataValue=prefix=>Object.entries(metadata).find(([key,value])=>
                key.toLowerCase().startsWith(prefix)&&value!==null&&String(value).trim()!==''
            )?.[1]??'';
            return {
                key:Number(row.id),id:row.bull_id??'',name:row.bull_name||metadata.bull_name||metadataValue('bull name_')||'',breed:row.breed||metadata.breed||metadataValue('type of animal_')||'',
                registration:row.registration_no||metadata.registration_no||metadataValue('reg id_')||'',
                dob:(row.date_of_birth??metadata.date_of_birth)?String(row.date_of_birth??metadata.date_of_birth).slice(0,10):'',
                source:row.source||metadata.source||metadataValue('bull farm_')||metadataValue('source_')||'',
                sire:row.sire||metadata.sire||metadataValue('sire id_')||'',dam:row.dam||metadata.dam||metadataValue('dam id_')||'',
                status:row.status??'Active',semenUsed:Number(row.semen_used??metadata.semen_used??0),remarks:row.remarks??''
            };
        },
        filteredBulls(){
            const q=this.search.toLowerCase().trim();
            return this.bulls.filter(b=>{
                const matchSearch=!q||this.fields.some(f=>String(b[f]??'').toLowerCase().includes(q));
                const matchBreed=!this.filterBreed||b.breed===this.filterBreed;
                const matchStatus=!this.filterStatus||b.status===this.filterStatus;
                const matchSource=!this.filterSource||String(b.source||'').toLowerCase().includes(this.filterSource.toLowerCase().trim());
                return matchSearch&&matchBreed&&matchStatus&&matchSource;
            });
        },
        breeds(){return [...new Set(this.bulls.map(b=>b.breed).filter(Boolean))].sort((a,b)=>a.localeCompare(b));},
        summaryCards(){const list=this.filteredBulls();return [
            {title:'Total Semen Bulls',value:list.length,description:'Records matching active filters',icon:'fa-solid fa-list-check',iconBg:'bg-slate-100',iconColor:'text-slate-700'},
            {title:'Active Bulls',value:list.filter(b=>b.status==='Active').length,description:'Active bulls in filtered records',icon:'fa-solid fa-circle-check',iconBg:'bg-emerald-50',iconColor:'text-emerald-700'},
            {title:'Inactive / Retired',value:list.filter(b=>b.status!=='Active').length,description:'Inactive or retired records',icon:'fa-solid fa-pause-circle',iconBg:'bg-amber-50',iconColor:'text-amber-700'},
            {title:'Semen Used',value:list.reduce((sum,b)=>sum+(Number(b.semenUsed)||0),0).toLocaleString(),description:'Sum of recorded semen usage',icon:'fa-solid fa-vials',iconBg:'bg-violet-50',iconColor:'text-violet-700'}
        ];},
        totalPages(){return Math.max(1,Math.ceil(this.filteredBulls().length/this.perPage));},
        paginatedBulls(){const list=this.filteredBulls();if(this.page>this.totalPages())this.page=this.totalPages();return list.slice((this.page-1)*this.perPage,this.page*this.perPage);},
        resetFilters(){this.search='';this.filterBreed='';this.filterStatus='';this.filterSource='';this.page=1;},
        resetForm(){this.form={id:'',name:'',breed:'',registration:'',dob:'',source:'',sire:'',dam:'',status:'Active',semenUsed:0,remarks:''};},
        openAdd(){this.editingKey=null;this.resetForm();this.showForm=true;},
        openEdit(b){this.editingKey=b.key;this.form={id:b.id||'',name:b.name||'',breed:b.breed||'',registration:b.registration||'',dob:b.dob||'',source:b.source||'',sire:b.sire||'',dam:b.dam||'',status:b.status||'Active',semenUsed:Number(b.semenUsed||0),remarks:b.remarks||''};this.showForm=true;},
        view(b){this.selected={...b};this.showView=true;},
        async save(){
            if(!this.form.id.trim()){this.msg('Bull ID is required. Bull Name can be left blank.','error');return;}
            if(!Number.isInteger(Number(this.form.semenUsed))||Number(this.form.semenUsed)<0){this.msg('Semen Used must be a non-negative whole number.','error');return;}
            this.saving=true;
            const payload={bull_id:this.form.id.trim(),bull_name:this.form.name.trim()||null,breed:this.form.breed.trim()||null,registration_no:this.form.registration.trim()||null,date_of_birth:this.form.dob||null,source:this.form.source.trim()||null,sire:this.form.sire.trim()||null,dam:this.form.dam.trim()||null,status:this.form.status,semen_used:Number(this.form.semenUsed||0),remarks:this.form.remarks.trim()||null};
            try{
                if(this.editingKey!==null){await this.request(`${this.baseUrl}/${encodeURIComponent(this.editingKey)}`,{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});this.msg('Semen bull record updated successfully.');}
                else{await this.request(this.baseUrl,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});this.msg('Semen bull record saved successfully.');}
                this.showForm=false;await this.fetchBulls();
            }catch(error){this.msg(error.message||'Unable to save record.','error');}
            finally{this.saving=false;}
        },
        remove(b){this.selected={...b};this.showDelete=true;},
        async deleteSelected(){if(!this.selected?.key){this.msg('Database record ID is missing.','error');return;}this.deleting=true;try{await this.request(`${this.baseUrl}/${encodeURIComponent(this.selected.key)}`,{method:'DELETE'});this.showDelete=false;this.msg('Semen bull record deleted.');await this.fetchBulls();}catch(error){this.msg(error.message||'Unable to delete record.','error');}finally{this.deleting=false;}},
        async importXLSX(event){
            const file=event.target.files?.[0];
            if(!file)return;
            if(!/\.(xlsx|xls)$/i.test(file.name)){
                this.msg('Please select an Excel workbook (.xlsx or .xls).','error');
                event.target.value='';
                return;
            }
            if(typeof XLSX==='undefined'){
                this.msg('Excel import library could not be loaded. Check your internet connection.','error');
                event.target.value='';
                return;
            }
            this.loading=true;
            try{
                const buffer=await file.arrayBuffer();
                const workbook=XLSX.read(buffer,{type:'array',cellDates:true});
                const firstSheet=workbook.Sheets[workbook.SheetNames[0]];
                const rows=XLSX.utils.sheet_to_json(firstSheet,{defval:'',raw:false});
                if(!rows.length) throw new Error('The first worksheet is empty.');

                const normalizeKey=value=>String(value??'').trim().toLowerCase().replace(/[^a-z0-9]/g,'');
                const getValue=(row,aliases)=>{
                    const keys=Object.keys(row);
                    const key=keys.find(k=>aliases.includes(normalizeKey(k)));
                    return key===undefined?'':row[key];
                };
                let created=0,updated=0,failed=0;
                const errors=[];
                for(let i=0;i<rows.length;i++){
                    const row=rows[i];
                    const bullId=String(getValue(row,['bullid','id'])??'').trim();
                    const bullName=String(getValue(row,['bullname','name'])??'').trim();
                    if(!bullId){failed++;errors.push(`Row ${i+2}: Bull ID is required.`);continue;}
                    let dob=getValue(row,['dateofbirth','dob']);
                    if(dob instanceof Date && !isNaN(dob)) dob=dob.toISOString().slice(0,10);
                    else if(typeof dob==='number' && Number.isFinite(dob)){
                        const parsed=XLSX.SSF.parse_date_code(dob);
                        dob=parsed?`${parsed.y}-${String(parsed.m).padStart(2,'0')}-${String(parsed.d).padStart(2,'0')}`:'';
                    } else if(dob){
                        const parsedDate=new Date(dob);
                        dob=!isNaN(parsedDate)?parsedDate.toISOString().slice(0,10):'';
                    }
                    const rawSemen=getValue(row,['semenused','semenusage','semenusedcount']);
                    const semenUsed=rawSemen===''?0:Number(rawSemen);
                    if(!Number.isInteger(semenUsed)||semenUsed<0){failed++;errors.push(`Row ${i+2}: Semen Used must be a non-negative whole number.`);continue;}
                    const rawStatus=String(getValue(row,['status'])||'Active').trim();
                    const status=['active','inactive','retired'].includes(rawStatus.toLowerCase())
                        ? rawStatus.charAt(0).toUpperCase()+rawStatus.slice(1).toLowerCase() : 'Active';
                    const payload={
                        bull_id:bullId,
                        bull_name:bullName||null,
                        breed:String(getValue(row,['breed'])||'').trim()||null,
                        registration_no:String(getValue(row,['registrationno','registrationnumber','registration'])||'').trim()||null,
                        date_of_birth:dob||null,
                        source:String(getValue(row,['source','institution'])||'').trim()||null,
                        sire:String(getValue(row,['sire','sireid','sirename'])||'').trim()||null,
                        dam:String(getValue(row,['dam','damid','damname'])||'').trim()||null,
                        status,
                        semen_used:semenUsed,
                        remarks:String(getValue(row,['remarks','remark','notes'])||'').trim()||null
                    };
                    try{
                        const existing=this.bulls.find(b=>String(b.id).toLowerCase()===bullId.toLowerCase());
                        if(existing){
                            await this.request(`${this.baseUrl}/${encodeURIComponent(existing.key)}`,{
                                method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)
                            });
                            updated++;
                        }else{
                            await this.request(this.baseUrl,{
                                method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)
                            });
                            created++;
                        }
                    }catch(error){
                        failed++;
                        errors.push(`Row ${i+2} (${bullId}): ${error.message||'Import failed.'}`);
                    }
                }
                await this.fetchBulls();
                let message=`Excel import finished: ${created} added, ${updated} updated, ${failed} failed.`;
                if(errors.length) message+=' First issue: '+errors[0];
                this.msg(message,failed?'error':'success');
            }catch(error){
                this.msg(error.message||'Unable to read the Excel workbook.','error');
            }finally{
                this.loading=false;
                event.target.value='';
            }
        },
        exportXLSX(){
            if(typeof XLSX==='undefined'){
                this.msg('Excel export library could not be loaded. Check your internet connection.','error');
                return;
            }
            const rows=this.filteredBulls().map(b=>({
                'Bull ID':b.id,
                'Bull Name':b.name,
                'Breed':b.breed,
                'Registration No.':b.registration,
                'Date of Birth':b.dob,
                'Source':b.source,
                'Sire':b.sire,
                'Dam':b.dam,
                'Semen Used':Number(b.semenUsed||0),
                'Status':b.status,
                'Remarks':b.remarks
            }));
            const worksheet=XLSX.utils.json_to_sheet(rows);
            worksheet['!cols']=[{wch:18},{wch:28},{wch:18},{wch:22},{wch:16},{wch:25},{wch:24},{wch:24},{wch:14},{wch:14},{wch:40}];
            const workbook=XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook,worksheet,'Semen Bulls');
            XLSX.writeFile(workbook,'cbed_semen_bulls.xlsx');
            this.msg('Excel workbook exported successfully.');
        },
        statusClass(status){if(String(status||'').toLowerCase()==='active')return 'bg-emerald-50 text-emerald-700';if(String(status||'').toLowerCase()==='retired')return 'bg-slate-100 text-slate-700';return 'bg-amber-50 text-amber-700';},
        label(field){return ({id:'Bull ID',name:'Bull Name',breed:'Breed',registration:'Registration Number',dob:'Date of Birth',source:'Source',sire:'Sire',dam:'Dam',semenUsed:'Semen Used',status:'Status',remarks:'Remarks'})[field]||field;},
        msg(message,type='success'){this.notice=message;this.noticeType=type;if(this.noticeTimer)clearTimeout(this.noticeTimer);this.noticeTimer=setTimeout(()=>{this.notice='';},4000);},
        csvEscape(v){return '"'+String(v??'').replace(/"/g,'""')+'"';},
    };
}
</script>
@endpush
