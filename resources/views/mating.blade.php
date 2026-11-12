{{-- resources/views/breeding.blade.php --}}
@extends('layouts.admin')



@section('content')
<div x-data="breedingPage()" x-init="init()" x-cloak class="mating-dashboard min-w-0 bg-slate-50 text-slate-800">

    {{-- PAGE HEADER --}}
    <div class="pcc-mating-hero mb-6 flex flex-col gap-4 rounded-2xl p-5 text-white shadow-lg lg:flex-row lg:items-center lg:justify-between sm:p-6">    
        <div class="flex items-center gap-4">
            <div class="pcc-brand-mark hidden sm:flex" aria-hidden="true">
                <i class="fa-solid fa-cow"></i><span><i class="fa-solid fa-dna"></i></span>
            </div>
            <div>
            <div class="mb-2 flex items-center gap-2 text-sm text-blue-100">
                <i class="fa-solid fa-house"></i><span>/</span>
                <span>CBED Data System</span><span>/</span>
                <b class="text-white">Mating</b>
            </div>
            <h1 class="text-2xl font-bold text-white sm:text-3xl">Mating & Pregnancy Monitoring</h1>
            <p class="mt-1 text-sm text-blue-100">Breeding services, pregnancy checks, and yearly performance at a glance.</p>
            <span class="pcc-program-tag"><i class="fa-solid fa-seedling"></i> Genetic Improvement · AI & Natural Mating</span>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="button" @click="exportCSV()" class="action-secondary">
                <i class="fa-solid fa-file-export"></i> Export CSV
            </button>

            <button type="button" @click="downloadTemplate()" class="action-secondary">
                <i class="fa-solid fa-download"></i> Excel Template
            </button>

            <label class="action-secondary cursor-pointer">
                <i class="fa-solid fa-file-excel text-emerald-700"></i>
                Import Excel (.xlsx)
                <input type="file"
                    accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                    class="hidden"
                    @change="importXLSX($event)">
            </label>

            <button type="button" @click="openBulk()" class="action-primary">
                <i class="fa-solid fa-plus"></i> Add Mating Records
            </button>
        </div>
    </div>

    {{-- CONNECTION STATUS --}}
    <div class="mb-6 flex gap-3 rounded-xl border p-4 text-sm"
        :class="apiReady ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900'">
        <i class="fa-solid mt-0.5" :class="apiReady ? 'fa-circle-check' : 'fa-circle-info'"></i>
        <div>
            <b x-text="apiReady ? 'MySQL API connected' : 'Connecting to breeding API'"></b>
            <p class="mt-1" x-text="apiMessage"></p>
        </div>
    </div>

    {{-- SUMMARY CARDS --}}
    <div class="mb-7 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <template x-for="c in summaryCards()" :key="c.title">
            <div class="pcc-stat-card rounded-xl bg-white p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm text-slate-500" x-text="c.title"></p>
                        <p class="mt-2 text-3xl font-bold" x-text="c.value"></p>
                        <p class="mt-1 text-xs text-slate-500" x-text="c.description"></p>
                    </div>
                    <div class="pcc-stat-icon flex h-12 w-12 items-center justify-center rounded-xl" :class="c.bg">
                        <i :class="c.icon+' text-xl '+c.color"></i>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- RECORDS TABLE --}}
    <section class="pcc-records-panel overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-[#12345a]">Mating Records</h2>
                <p class="mt-1 text-sm text-slate-500">Search and review AI or natural service records by year.</p>
            </div>
            <div class="flex gap-2">
                <span class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold">
                    <span x-text="filteredRecords().length"></span> records
                </span>
                <button type="button" @click="resetFilters()" class="action-secondary">Reset Filters</button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 border-b bg-slate-50 p-5 sm:grid-cols-2 xl:grid-cols-6">
            <div class="xl:col-span-2">
                <label class="filter-label">Search</label>
                <input x-model="search" @input="page=1" class="filter-input"
                    placeholder="Animal, farmer, bull ID/name, technician">
            </div>
            <div>
                <label class="filter-label">Mating Type</label>
                <select x-model="filterMatingType" @change="page=1" class="filter-input">
                    <option value="">All types</option>
                    <option value="AI">Artificial Insemination (AI)</option>
                    <option value="NM">Natural Mating (NM)</option>
                </select>
            </div>
            <div>
                <label class="filter-label">Year</label>
                <select x-model="filterYear" @change="page=1" class="filter-input">
                    <option value="">All years</option>
                    <template x-for="year in availableYears()" :key="year"><option :value="String(year)" x-text="year"></option></template>
                </select>
            </div>
            <div>
                <label class="filter-label">PD Result</label>
                <select x-model="filterPD" @change="page=1" class="filter-input">
                    <option value="">All results</option>
                    <option>Pending</option>
                    <option>Positive</option>
                    <option>Negative</option>
                </select>
            </div>
            <div>
                <label class="filter-label">Mating Date</label>
                <input type="date" x-model="filterDate" @change="page=1" class="filter-input">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[2500px] text-left text-sm">
                <thead class="bg-slate-100 text-xs uppercase text-slate-600">
                    <tr>
                        <th class="th">No.</th>
                        <th class="th">Cooperative</th>
                        <th class="th">Farmer</th>
                        <th class="th">Animal ID</th>
                        <th class="th">Mating Date</th>
                        <th class="th">Mating Type</th>
                        <th class="th">Bull ID</th>
                        <th class="th">Bull Name</th>
                        <th class="th">Straws</th>
                        <th class="th">Estrus / Heat</th>
                        <th class="th">2nd Cycle (18–21 Days)</th>
                        <th class="th">Expected PD (60 Days)</th>
                        <th class="th">Expected PD (90 Days)</th>
                        <th class="th">Mating Technician</th>
                        <th class="th">PD Date</th>
                        <th class="th">PD Result</th>
                        <th class="th">PD Technician</th>
                        <th class="th">Pregnancy Days</th>
                        <th class="th">Expected Calving</th>
                        <th class="th">Actual Calving</th>
                        <th class="th">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-for="(r,i) in paginatedRecords()" :key="r.id">
                        <tr class="hover:bg-blue-50/50">
                            <td class="td text-slate-500" x-text="(page-1)*perPage+i+1"></td>
                            <td class="td" x-text="r.cooperative||'—'"></td>
                            <td class="td" x-text="r.farmer||'—'"></td>
                            <td class="td font-bold" x-text="r.animal_id||'—'"></td>
                            <td class="td" x-text="displayDate(r.breeding_date)"></td>
                            <td class="td"><span class="pcc-type-pill" :class="r.mating_type==='NM'?'pcc-type-nm':'pcc-type-ai'" x-text="r.mating_type==='NM'?'NM · Natural':'AI · Artificial Insemination'"></span></td>
                            <td class="td" x-text="r.bull_id||'—'"></td>
                            <td class="td" x-text="r.bull_name||'—'"></td>
                            <td class="td" x-text="r.mating_type==='AI'?(r.straws||'—'):'—'"></td>
                            <td class="td" x-text="r.heat_status||'—'"></td>
                            <td class="td" x-text="secondCycleRange(r.breeding_date)"></td>
                            <td class="td" x-text="expectedPDDate(r.breeding_date,60)"></td>
                            <td class="td" x-text="expectedPDDate(r.breeding_date,90)"></td>
                            <td class="td" x-text="r.mating_technician||'—'"></td>
                            <td class="td" x-text="displayDate(r.pd_date)"></td>
                            <td class="td">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold"
                                    :class="pdClass(r.pd_result)" x-text="r.pd_result||'Pending'"></span>
                            </td>
                            <td class="td" x-text="r.pd_technician||'—'"></td>
                            <td class="td" x-text="r.pd_result==='Positive'?pregnancyDays(r)+' days':'—'"></td>
                            <td class="td" x-text="r.pd_result==='Positive'?displayDate(r.expected_calving||calculateExpectedCalving(r.breeding_date)):'—'"></td>
                            <td class="td" x-text="displayDate(r.actual_calving)"></td>
                            <td class="td whitespace-nowrap">
                                <button type="button" @click="view(r)" class="mr-1 rounded-lg border px-3 py-2" title="View">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                <button type="button" @click="edit(r)" class="mr-1 rounded-lg border px-3 py-2" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button type="button" @click="remove(r.id)" class="rounded-lg px-3 py-2 text-red-600" title="Delete">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="filteredRecords().length===0">
                        <td colspan="21" class="px-5 py-12 text-center text-slate-500" x-text="filterYear?'No mating records for '+filterYear+' yet.':'No mating records found.'"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-col justify-between gap-3 border-t px-5 py-4 sm:flex-row sm:items-center">
            <p class="text-sm text-slate-500">
                Showing <b x-text="filteredRecords().length?((page-1)*perPage+1):0"></b>–
                <b x-text="Math.min(page*perPage,filteredRecords().length)"></b>
                of <b x-text="filteredRecords().length"></b>
                (<b x-text="records.length"></b> total)
            </p>
            <div class="flex items-center gap-2">
                <button type="button" @click="page=Math.max(1,page-1)" :disabled="page<=1" class="page-btn">Previous</button>
                <span class="text-sm">Page <b x-text="page"></b> of <b x-text="totalPages()"></b></span>
                <button type="button" @click="page=Math.min(totalPages(),page+1)" :disabled="page>=totalPages()" class="page-btn">Next</button>
            </div>
        </div>
    </section>

    <footer class="py-5 text-center text-xs text-slate-400">CBED Data System · Mating & Pregnancy Monitoring</footer>

    {{-- BULK ADD / EDIT MODAL --}}
    <div x-show="showBulk" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-5">
        <div @click.outside="closeModal()" class="max-h-[95vh] w-full max-w-[98vw] overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b p-5">
                <div>
                    <h3 class="text-xl font-bold" x-text="editingId?'Edit Mating Record':'Bulk Add Mating Records'"></h3>
                    <p class="mt-1 text-sm text-slate-500">Choose the cooperative once. Farmer options are filtered to that cooperative.</p>
                </div>
                <div class="flex items-center gap-3">
                    <b class="text-sm" x-text="bulkRows.length+' row(s)'"></b>
                    <button type="button" @click="closeModal()" class="rounded-lg p-2 hover:bg-slate-100">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>
            </div>

            <div class="max-h-[calc(95vh-90px)] overflow-auto p-4 sm:p-5">
                <div class="mb-5 grid grid-cols-1 gap-4 rounded-xl border bg-slate-50 p-4 md:grid-cols-3">
                    <div>
                        <label class="form-label">Cooperative *</label>
                        <select x-model="selectedCooperative" @change="cooperativeChanged()" class="form-input">
                            <option value="">Select cooperative</option>
                            <template x-for="c in cooperatives" :key="c.id">
                                <option :value="String(c.id)" x-text="c.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Filter Farmers</label>
                        <input x-model="farmerSearch" class="form-input" placeholder="Search farmer name">
                    </div>
                    <div>
                        <label class="form-label">Database Lookup Status</label>
                        <div class="rounded-lg border bg-white p-3">
                            <p class="text-sm" :class="lookupReady?'text-emerald-700':'text-amber-700'"
                                x-text="lookupReady?'Database lookup lists loaded':'Select a cooperative to load farmers'"></p>
                            <div class="mt-3 flex items-center justify-between border-t pt-3">
                                <span class="text-sm text-slate-600">Active bulls</span>
                                <span class="rounded-full bg-blue-50 px-3 py-1 text-sm font-bold text-blue-700" x-text="totalActiveBulls"></span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500"><span x-text="semenBulls.length"></span> bull records loaded into the selector.</p>
                        </div>
                    </div>
                </div>

                <div class="mb-4 flex flex-wrap justify-end gap-2">
                    <button type="button" x-show="!editingId" @click="addRow()" class="action-primary">
                        <i class="fa-solid fa-plus"></i> Add Row
                    </button>
                </div>

                <div class="overflow-x-auto rounded-xl border">
                    <table class="w-full min-w-[3500px] text-left text-sm">
                        <thead class="bg-slate-100 text-xs uppercase text-slate-600">
                            <tr>
                                <th class="p-3">Farmer *</th>
                                <th class="p-3">Animal ID *</th>
                                <th class="p-3">Mating Date *</th>
                                <th class="p-3">Mating Type *</th>
                                <th class="p-3">Bull search (ID or Name)</th>
                                <th class="p-3">Bull ID</th>
                                <th class="p-3">Bull Name</th>
                                <th class="p-3">Straws</th>
                                <th class="p-3">Estrus / Heat *</th>
                                <th class="p-3">2nd Cycle (18–21 Days)</th>
                                <th class="p-3">Expected PD (60 Days)</th>
                                <th class="p-3">Expected PD (90 Days)</th>
                                <th class="p-3">Mating Technician *</th>
                                <th class="p-3">PD Date</th>
                                <th class="p-3">PD Result</th>

                                {{-- FIX: ACTUAL PREGNANCY MONTHS INPUT --}}
                                <th class="p-3">Pregnancy Months *</th>

                                <th class="p-3">PD Technician</th>
                                <th class="p-3">Expected Calving</th>
                                <th class="p-3">Actual Calving</th>
                                <th class="p-3">Remarks</th>
                                <th class="p-3">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <template x-for="(r,i) in bulkRows" :key="r._key">
                                <tr class="align-top">
                                    <td class="min-w-56 p-2">
                                        <select x-model="r.farmer_id" @change="farmerChanged(r)" class="form-input">
                                            <option value="">Select farmer</option>
                                            <template x-for="f in filteredFarmers()" :key="f.id">
                                                <option :value="String(f.id)" x-text="f.name"></option>
                                            </template>
                                        </select>
                                        <p class="help-text" x-text="r.farmer||''"></p>
                                    </td>

                                    <td class="min-w-48 p-2">
                                        <select x-show="animalsForFarmer(r).length" x-model="r.carabao_id" @change="animalChanged(r)" class="form-input">
                                            <option value="">Select female animal</option>
                                            <template x-for="a in animalsForFarmer(r)" :key="a.id">
                                                <option :value="a.id" x-text="a.label"></option>
                                            </template>
                                        </select>
                                        <input x-show="!animalsForFarmer(r).length" x-model.trim="r.animal_id" class="form-input" placeholder="Animal ID">
                                    </td>

                                    <td class="min-w-40 p-2">
                                        <input type="date" x-model="r.breeding_date" @change="dateChanged(r)" class="form-input">
                                    </td>

                                    <td class="min-w-48 p-2">
                                        <select x-model="r.mating_type" @change="typeChanged(r)" class="form-input">
                                            <option value="AI">Artificial Insemination (AI)</option>
                                            <option value="Natural Mating">Natural Mating</option>
                                        </select>
                                    </td>

                                    <td class="min-w-64 p-2">
                                        <input x-model="r.bull_search" @input="matchBull(r)" list="semenBullOptions" class="form-input" placeholder="Type Bull ID or name">
                                        <datalist id="semenBullOptions">
                                            <template x-for="b in semenBulls" :key="b.id">
                                                <option :value="b.bull_id" :label="b.bull_id+' — '+(b.bull_name||'(No name)')"></option>
                                                <option x-show="b.bull_name" :value="b.bull_name" :label="b.bull_id+' — '+b.bull_name"></option>
                                            </template>
                                        </datalist>
                                    </td>

                                    <td class="min-w-36 p-2">
                                        <input x-model="r.bull_id" @input="matchBullField(r,'bull_id')" class="form-input" placeholder="Bull ID">
                                    </td>

                                    <td class="min-w-40 p-2">
                                        <input x-model="r.bull_name" @input="matchBullField(r,'bull_name')" class="form-input" placeholder="Optional / blank if none">
                                    </td>

                                    <td class="min-w-24 p-2">
                                        <input type="number" min="0" step="1" x-model="r.straws" :disabled="r.mating_type!=='AI'" class="form-input" placeholder="0">
                                    </td>

                                    <td class="min-w-36 p-2">
                                        <select x-model="r.heat_status" class="form-input">
                                            <option value="">Select</option>
                                            <option value="ES">ES</option>
                                            <option value="NH">NH</option>
                                            <option value="FTAI">FTAI</option>
                                        </select>
                                    </td>

                                    <td class="min-w-52 p-2">
                                        <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-800"
                                            x-text="secondCycleRange(r.breeding_date)"></div>
                                        <p class="help-text">AI / Natural Mating follow-up</p>
                                    </td>

                                    <td class="min-w-40 p-2">
                                        <div class="rounded-lg border bg-slate-50 px-3 py-2 text-sm font-semibold"
                                            x-text="expectedPDDate(r.breeding_date,60)"></div>
                                    </td>

                                    <td class="min-w-40 p-2">
                                        <div class="rounded-lg border bg-slate-50 px-3 py-2 text-sm font-semibold"
                                            x-text="expectedPDDate(r.breeding_date,90)"></div>
                                    </td>

                                    <td class="min-w-44 p-2">
                                        <input x-model.trim="r.mating_technician" class="form-input" placeholder="Technician name">
                                    </td>

                                    <td class="min-w-40 p-2">
                                        <input type="date" x-model="r.pd_date" @change="pdDateChanged(r)" class="form-input">
                                    </td>

                                    <td class="min-w-36 p-2">
                                        <select x-model="r.pd_result" @change="pdChanged(r)" class="form-input">
                                            <option>Pending</option>
                                            <option>Negative</option>
                                            <option>Positive</option>
                                        </select>
                                    </td>

                                    {{-- FIX: INPUT WAS MISSING HERE --}}
                                    <td class="min-w-40 p-2">
                                        <input
                                            type="number"
                                            min="1"
                                            max="11"
                                            step="1"
                                            x-model="r.pregnancy_months"
                                            :disabled="r.pd_result!=='Positive'"
                                            :required="r.pd_result==='Positive'"
                                            class="form-input"
                                            placeholder="Months"
                                            title="Required when PD Result is Positive">
                                        <p class="help-text"
                                            x-show="r.pd_result==='Positive'">
                                            Required for positive PD
                                        </p>
                                        <p class="help-text"
                                            x-show="r.pd_result!=='Positive'">
                                            Enter only for positive PD
                                        </p>
                                    </td>

                                    <td class="min-w-44 p-2">
                                        <input x-model.trim="r.pd_technician" :disabled="r.pd_result==='Pending'" class="form-input" placeholder="Confirming technician">
                                    </td>

                                    <td class="min-w-40 p-2">
                                        <input type="date" x-model="r.expected_calving" :disabled="r.pd_result!=='Positive'" class="form-input">
                                    </td>

                                    <td class="min-w-40 p-2">
                                        <input type="date" x-model="r.actual_calving" class="form-input">
                                    </td>

                                    <td class="min-w-48 p-2">
                                        <input x-model="r.remarks" class="form-input" placeholder="Remarks">
                                    </td>

                                    <td class="p-2">
                                        <button type="button" x-show="!editingId" @click="removeRow(i)" class="rounded-lg p-2 text-red-600">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>

                            <tr x-show="!bulkRows.length">
                                <td colspan="21" class="p-8 text-center text-slate-500">No rows. Click Add Row.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-col gap-3 rounded-xl bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <b class="text-sm">Batch total: <span x-text="bulkRows.length"></span> row(s)</b>
                        <p class="help-text">New records start Pending. Positive/Negative PD requires a PD date and confirming technician. Positive PD also requires pregnancy months.</p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="closeModal()" class="action-secondary">Cancel</button>
                        <button type="button" @click="saveBulk()" :disabled="saving" class="action-primary">
                            <i class="fa-solid" :class="saving?'fa-spinner fa-spin':'fa-floppy-disk'"></i>
                            <span x-text="saving?'Saving...':(editingId?'Update Record':'Save to MySQL')"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- VIEW MODAL --}}
    <div x-show="showView" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/60 p-4">
        <div @click.outside="showView=false" class="w-full max-w-5xl overflow-hidden rounded-xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b p-5">
                <h3 class="text-lg font-bold">Mating Record Details</h3>
                <button type="button" @click="showView=false" class="rounded-lg p-2 hover:bg-slate-100">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3">
                <template x-for="f in fields" :key="f">
                    <div class="rounded-lg border p-3">
                        <p class="text-xs text-slate-500" x-text="label(f)"></p>
                        <p class="mt-1 break-words font-semibold" x-text="selected&&selected[f]!==''&&selected[f]!=null?selected[f]:'—'"></p>
                    </div>
                </template>
                <div class="rounded-lg border p-3">
                    <p class="text-xs text-slate-500">Pregnancy Months</p>
                    <p class="mt-1 font-semibold"
                        x-text="selected&&selected.pd_result==='Positive'&&selected.pregnancy_months!==''&&selected.pregnancy_months!=null?selected.pregnancy_months+' months':'—'"></p>
                </div>
                <div class="rounded-lg border p-3">
                    <p class="text-xs text-slate-500">Pregnancy Duration</p>
                    <p class="mt-1 font-semibold" x-text="selected&&selected.pd_result==='Positive'?pregnancyDays(selected)+' days':'—'"></p>
                </div>
            </div>
            <div class="flex justify-end border-t p-4">
                <button type="button" @click="showView=false" class="action-primary">Close</button>
            </div>
        </div>
    </div>

    {{-- NOTIFICATION --}}
    <div x-show="notice" x-cloak class="fixed bottom-5 right-5 z-[70] max-w-sm rounded-xl px-5 py-3 text-sm font-medium text-white shadow-xl"
        :class="noticeType==='error'?'bg-rose-700':'bg-slate-900'" x-text="notice"></div>
</div>
@endsection

@push('styles')
<style>
[x-cloak]{display:none!important}
.form-input,.filter-input{width:100%;border:1px solid #cbd5e1;border-radius:.5rem;background:#fff;padding:.625rem .75rem;font-size:.875rem;color:#1e293b;outline:none}
.form-input:focus,.filter-input:focus{border-color:#1d4ed8;box-shadow:0 0 0 2px #dbeafe}
.form-input:disabled{background:#f1f5f9;color:#64748b}
.form-label,.filter-label{display:block;margin-bottom:.375rem;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
.help-text{margin-top:.25rem;font-size:.7rem;line-height:1rem;color:#64748b}
.action-primary,.action-secondary{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;border-radius:.5rem;padding:.625rem 1rem;font-size:.875rem;font-weight:600}
.action-primary{background:#0b1f3a;color:#fff}.action-primary:hover{background:#122b4d}
.action-secondary{border:1px solid #cbd5e1;background:#fff;color:#334155}.action-secondary:hover{background:#f1f5f9}
.th{padding:1rem}.td{padding:1rem;vertical-align:top}
.page-btn{border:1px solid #cbd5e1;border-radius:.5rem;padding:.5rem .75rem;font-size:.875rem;font-weight:600}
.page-btn:disabled{opacity:.4;cursor:not-allowed}
.pcc-mating-hero{position:relative;isolation:isolate;overflow:hidden;background:radial-gradient(circle at 90% 18%,rgba(255,196,56,.25),transparent 19%),linear-gradient(118deg,#082d50 0%,#075785 55%,#0786a5 100%);box-shadow:0 16px 32px rgba(7,62,99,.2)}
.pcc-mating-hero::after{content:"";position:absolute;z-index:-1;width:19rem;height:19rem;right:11%;top:-14rem;border:1px solid rgba(255,214,92,.45);border-radius:50%;box-shadow:0 0 0 26px rgba(255,214,92,.08),0 0 0 54px rgba(255,255,255,.045)}
.pcc-brand-mark{position:relative;flex:none;width:4.35rem;height:4.35rem;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.34);border-radius:1.35rem;background:linear-gradient(145deg,rgba(255,255,255,.22),rgba(255,255,255,.07));color:#ffd05b;font-size:2rem;box-shadow:0 10px 22px rgba(0,28,52,.2)}
.pcc-brand-mark span{position:absolute;right:-.45rem;bottom:-.4rem;display:grid;width:1.65rem;height:1.65rem;place-items:center;border:2px solid #075785;border-radius:999px;background:#ffd05b;color:#12345a;font-size:.75rem}
.pcc-program-tag{display:inline-flex;align-items:center;gap:.4rem;margin-top:.7rem;border:1px solid rgba(255,213,91,.35);border-radius:999px;background:rgba(4,37,68,.24);padding:.35rem .7rem;color:#ffe08a;font-size:.7rem;font-weight:700;letter-spacing:.035em}
.pcc-mating-hero .action-secondary{border-color:rgba(255,255,255,.28);background:rgba(255,255,255,.1);color:#fff;backdrop-filter:blur(8px)}
.pcc-mating-hero .action-secondary:hover{background:rgba(255,255,255,.2)}
.pcc-mating-hero .action-primary{background:#ffd05b;color:#12345a;box-shadow:0 7px 16px rgba(4,37,68,.2)}
.pcc-mating-hero .action-primary:hover{background:#ffdc7d}
.pcc-stat-card{position:relative;overflow:hidden;border:1px solid #dce6ed;border-top:4px solid #0b5e8b;border-radius:1rem;box-shadow:0 5px 18px rgba(15,45,67,.06);transition:transform .16s ease,box-shadow .16s ease}
.pcc-stat-card:hover{transform:translateY(-3px);box-shadow:0 14px 28px rgba(7,62,99,.13)}
.pcc-stat-card:nth-child(2){border-top-color:#e87523}.pcc-stat-card:nth-child(3){border-top-color:#25835b}.pcc-stat-card:nth-child(4){border-top-color:#0786a5}
.pcc-stat-icon{background:linear-gradient(145deg,#082d50,#0b6d9b)!important;color:#fff!important;border-radius:1rem;box-shadow:0 8px 18px rgba(7,62,99,.2)}
.pcc-stat-card:nth-child(2) .pcc-stat-icon{background:linear-gradient(145deg,#a94b0b,#f28a32)!important}.pcc-stat-card:nth-child(3) .pcc-stat-icon{background:linear-gradient(145deg,#176342,#38a36f)!important}.pcc-stat-card:nth-child(4) .pcc-stat-icon{background:linear-gradient(145deg,#075785,#0798b6)!important}
.pcc-stat-icon [class*="text-"]{color:#fff!important}
.pcc-records-panel{border-color:#dce6ed;box-shadow:0 7px 24px rgba(15,45,67,.06)}
.pcc-records-panel>div:first-child{border-bottom-color:#e3ebf0}
.pcc-records-panel thead{background:#103c60!important;color:#fff!important;border-bottom:3px solid #e8b22f}
.pcc-records-panel .th{color:#fff!important}
.pcc-records-panel .filter-input:focus{border-color:#0786a5;box-shadow:0 0 0 3px rgba(7,134,165,.14)}
.pcc-type-pill{display:inline-flex;align-items:center;white-space:nowrap;border-radius:999px;padding:.35rem .65rem;font-size:.7rem;font-weight:800}
.pcc-type-ai{background:#fff0e3;color:#a94b0b}.pcc-type-nm{background:#e6f5eb;color:#176342}
.pcc-records-panel tbody tr{transition:background-color .14s ease}.pcc-records-panel tbody tr:hover{background:#f0f8fb}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<script>
function breedingPage(){return {
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
    totalActiveBulls:0,lookupReady:false,apiReady:false,
    apiMessage:'Loading data from the server...',loading:false,saving:false,
    search:'',filterMatingType:'',filterPD:'',filterDate:'',filterYear:String(new Date().getFullYear()),
    selectedCooperative:'',farmerSearch:'',showBulk:false,showView:false,
    bulkRows:[],selected:null,editingId:null,notice:'',noticeType:'success',
    noticeTimer:null,rowKey:0,page:1,perPage:10,

    fields:[
        'cooperative','farmer','animal_id','breeding_date','mating_type',
        'bull_id','bull_name','straws','heat_status','mating_technician',
        'pd_date','pd_result','pregnancy_months','pd_technician',
        'expected_calving','actual_calving','remarks'
    ],

    async init(){
        try{
            await Promise.all([this.loadCooperatives(),this.loadSemenBulls()]);
            await this.loadRecords();
            this.apiReady=true;
            this.apiMessage='Cooperatives, semen bulls, and mating records are connected to MySQL.';
        }catch(e){
            this.apiReady=false;
            this.apiMessage=e.message||'Could not connect to the breeding API.';
            this.msg(this.apiMessage,'error');
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
        const data=Array.isArray(result.data)?result.data:(result.data?.data||[]);

        this.cooperatives=data.map(c=>({
            id:String(c.id),
            name:c.cooperative_name||c.name||'',
            code:c.cooperative_code||''
        }));
    },

    async loadSemenBulls(){
        const result=await this.request(this.api.bulls);
        const data=Array.isArray(result.data)?result.data:
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
            const data=Array.isArray(payload)?payload:
                (Array.isArray(payload?.data)?payload.data:[]);

            this.records=data.map(r=>this.normalizeRecord(r));
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
            breeding_date:this.dateOnly(r.breeding_date||r.mating_date),
            mating_type:this.normalizeMatingType(r.mating_type),
            semen_bull_id:r.semen_bull_id?String(r.semen_bull_id):'',
            bull_id:r.bull_id||b.bull_id||'',
            bull_name:r.bull_name||r.matched_bull_name||b.bull_name||'',
            straws:r.straws_used??'',
            heat_status:r.estrus_type||source['type of estrus_7']||'',
            mating_technician:r.mating_technician||r.technician||source['technician_8']||'',
            pd_date:this.dateOnly(r.pd_date||r.pregnancy_check_date),
            pd_result:this.normalizePDResult(r.pd_result??r.pregnancy_result),
            pregnancy_months:r.pregnancy_months??'',
            pd_technician:r.pd_technician||source['confirmed by_18']||'',
            expected_calving:this.dateOnly(r.expected_calving_date),
            actual_calving:this.dateOnly(r.actual_calving_date),
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
        return ['NM','NATURAL','NATURAL MATING','NATURAL SERVICE','NS'].includes(value)?'NM':'AI';
    },

    normalizePDResult(v){
        const value=String(v??'').trim().toLowerCase();
        if(['+','p','positive','pregnant','yes'].includes(value))return 'Positive';
        if(['-','n','negative','not pregnant','non-pregnant','no'].includes(value))return 'Negative';
        return value===''||['pending','null','n/a','na','?'].includes(value)?'Pending':String(v).trim();
    },

    async cooperativeChanged(){
        this.farmers=[];
        this.animals=[];
        this.farmerSearch='';
        this.lookupReady=false;

        for(const r of this.bulkRows){
            r.farmer_id='';
            r.farmer='';
            r.animal_id='';
            r.carabao_id='';
            r._animals=[];
        }

        if(!this.selectedCooperative)return;

        try{
            const u=new URL(this.api.farmers,window.location.origin);
            u.searchParams.set('cooperative_id',this.selectedCooperative);

            const res=await this.request(u.toString());
            const data=Array.isArray(res.data)?res.data:(res.data?.data||[]);

            this.farmers=data.map(f=>({
                id:String(f.id),
                name:[f.first_name,f.middle_name,f.last_name].filter(Boolean).join(' ')
                    ||f.farmer_name||f.name||'',
                cooperative_id:String(f.cooperative_id??'')
            }));

            this.lookupReady=true;
        }catch(e){
            this.msg(e.message,'error');
        }
    },

    filteredFarmers(){
        const q=this.farmerSearch.toLowerCase().trim();
        return this.farmers.filter(f=>!q||f.name.toLowerCase().includes(q));
    },

    async farmerChanged(row){
        const f=this.farmers.find(x=>x.id===String(row.farmer_id));
        row.farmer=f?f.name:'';
        row.carabao_id='';
        row.animal_id='';
        row._animals=[];

        if(!row.farmer_id||!this.selectedCooperative)return;

        try{
            const u=new URL(this.api.carabaos,window.location.origin);
            u.searchParams.set('cooperative_id',this.selectedCooperative);
            u.searchParams.set('farmer_id',row.farmer_id);

            const res=await this.request(u.toString());
            const data=Array.isArray(res.data)?res.data:(res.data?.data||[]);

            row._animals=data.filter(a=>{
                const sex=String(a.sex||a.gender||'').toLowerCase();
                return !sex||sex==='f'||sex==='female';
            }).map(a=>({
                id:String(a.id),
                animal_id:a.animal_id||a.bolus||'',
                label:(a.animal_id||a.bolus||('Animal '+a.id))+
                    (a.classification?' — '+a.classification:'')
            }));
        }catch(e){
            this.msg(e.message,'error');
        }
    },

    animalsForFarmer(r){return r._animals||[];},

    animalChanged(r){
        const a=this.animalsForFarmer(r).find(x=>String(x.id)===String(r.carabao_id));
        r.animal_id=a?a.animal_id:'';
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

    openBulk(){
        this.editingId=null;
        this.selectedCooperative='';
        this.farmerSearch='';
        this.farmers=[];
        this.bulkRows=[this.emptyRow()];
        this.showBulk=true;
        this.lookupReady=false;
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
        if(r.mating_type!=='AI')r.straws='';
        else if(!r.straws)r.straws='1';
    },

    dateChanged(r){
        if(r.pd_result==='Positive'&&!r.expected_calving){
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
        if(!v)return '';
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
            estrus_type:r.heat_status||null,
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
                !r.heat_status ||
                !String(r.mating_technician || '').trim()
            ) {
                this.msg(
                    'Row ' + row +
                    ': Farmer, female animal, mating date, mating type, estrus/heat, and technician are required.',
                    'error'
                );
                return false;
            }

            if (!['Pending', 'Positive', 'Negative'].includes(r.pd_result)) {
                this.msg('Row ' + row + ': Select a valid PD result.', 'error');
                return false;
            }

            // Require PD details for new records.
            // Existing records can be corrected even if old PD details are incomplete.
            if (!this.editingId &&
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

    async saveBulk(){
        if(this.saving||!this.validateRows())return;
        this.saving=true;

        try{
            if(this.editingId){
                await this.request(this.api.update+'/'+this.editingId,{
                    method:'PUT',
                    body:JSON.stringify(this.toPayload(this.bulkRows[0]))
                });
                this.msg('Mating record updated in MySQL.');
            }else{
                const records=this.bulkRows.map(r=>{
                    const x=this.toPayload(r);
                    delete x.cooperative_id;
                    return x;
                });

                await this.request(this.api.bulk,{
                    method:'POST',
                    body:JSON.stringify({
                        cooperative_id:Number(this.selectedCooperative),
                        records
                    })
                });

                this.msg(records.length+' mating record(s) saved to MySQL.');
            }

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
        this.farmerSearch='';
        this.farmers=[];
        this.bulkRows=[];
        this.showBulk=true;

        try{
            await this.cooperativeChanged();

            const row={
                ...this.emptyRow(),
                ...r,
                _key:++this.rowKey,
                farmer_id:String(r.farmer_id),
                carabao_id:String(r.carabao_id),
                semen_bull_id:r.semen_bull_id?String(r.semen_bull_id):'',
                pregnancy_months:r.pregnancy_months??'',
                bull_search:r.bull_id||''
            };

            await this.farmerChanged(row);
            row.carabao_id=String(r.carabao_id);
            row.animal_id=r.animal_id||'';
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

    async remove(id){
        if(!confirm('Delete this mating record from MySQL?'))return;

        try{
            await this.request(this.api.delete+'/'+id,{method:'DELETE'});
            await this.loadRecords();
            this.msg('Mating record deleted.');
        }catch(e){
            this.msg(e.message,'error');
        }
    },

    filteredRecords(){
        const q=this.search.toLowerCase().trim();

        return this.records.filter(r=>
            (!q||this.fields.some(f=>String(r[f]??'').toLowerCase().includes(q)))&&
            (!this.filterMatingType||r.mating_type===this.filterMatingType)&&
            (!this.filterYear||String(r.breeding_date||'').slice(0,4)===this.filterYear)&&
            (!this.filterPD||(r.pd_result||'Pending')===this.filterPD)&&
            (!this.filterDate||r.breeding_date===this.filterDate)
        );
    },

    summaryCards(){
        const a=this.filteredRecords();

        return [
            {title:'Total Mating Records',value:a.length,description:'Records matching filters',icon:'fa-solid fa-list-check',bg:'bg-slate-100',color:'text-slate-700'},
            {title:'Artificial Insemination',value:a.filter(r=>r.mating_type==='AI').length,description:'AI records in selected year',icon:'fa-solid fa-syringe',bg:'bg-orange-50',color:'text-orange-700'},
            {title:'Natural Mating (NM)',value:a.filter(r=>r.mating_type==='NM').length,description:'Natural service records in selected year',icon:'fa-solid fa-horse',bg:'bg-emerald-50',color:'text-emerald-700'},
            {title:'PD Positive',value:a.filter(r=>r.pd_result==='Positive').length,description:'Confirmed positive diagnoses',icon:'fa-solid fa-heart',bg:'bg-emerald-50',color:'text-emerald-700'}
        ];
    },

    availableYears(){
        const years=this.records.map(r=>Number(String(r.breeding_date||'').slice(0,4))).filter(Number.isInteger);
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
        this.filterPD='';
        this.filterDate='';
        this.filterYear=String(new Date().getFullYear());
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