{{-- resources/views/breeding.blade.php --}}
@extends('layouts.admin')
@section('inlineCalfDropNotifications', '1')



@section('content')
<div x-data="breedingPage()" x-cloak class="mating-dashboard min-w-0 bg-slate-50 text-slate-800">

    {{-- PAGE HEADER --}}
    <x-admin-page-header title="Mating & Pregnancy Monitoring" description="Breeding services, pregnancy checks, and yearly performance at a glance." section="Animal Management" breadcrumb="Mating">
        <button type="button" @click="exportCSV()" class="action-secondary">
            <i class="fa-solid fa-file-export"></i> Export
        </button>

        <label class="action-secondary cursor-pointer">
            <i class="fa-solid fa-file-excel text-emerald-700"></i>
            Import
            <input type="file"
                accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                class="hidden"
                @change="importXLSX($event)">
        </label>

        <button type="button" @click="downloadTemplate()" class="action-secondary">
            <i class="fa-solid fa-download"></i> Downloadable template
        </button>

        <button type="button" @click="openBulk()" class="cbed-add-button inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold">
            <i class="fa-solid fa-plus"></i> Add Mating Records
        </button>
    </x-admin-page-header>

    {{-- SUMMARY CARDS --}}
    <div class="mb-7 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <template x-for="c in summaryCards()" :key="c.title">
            <div class="pcc-stat-card rounded-xl bg-white p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm text-slate-500" x-text="c.title"></p>
                        <p class="mt-2 text-3xl font-bold" x-text="c.value"></p>
                        <p class="mt-1 text-xs text-slate-500" x-text="c.description"></p>
                        <button type="button" x-show="c.followup" @click="showUnreportedCalves()" class="mt-2 text-xs font-semibold text-blue-700 hover:underline">View animals</button>
                    </div>
                    <div class="pcc-stat-icon flex h-12 w-12 items-center justify-center rounded-xl" :class="c.bg">
                        <i :class="c.icon+' text-xl '+c.color"></i>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- RECORDS TABLE --}}
    <section class="pcc-records-panel rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-[#12345a]">Mating Records</h2>
                <p class="mt-1 text-sm text-slate-500">Positive records over 300 days leave the active list only when Calf Drop has a matching Mother ID and a calf born after that mating. Earlier calves do not close a later pregnancy.</p>
            </div>
            <div class="flex gap-2">
                <span class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold">
                    <span x-text="filteredRecords().length"></span> records
                </span>
                <x-reset-filters x-on:click="resetFilters()" />
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 border-b bg-slate-50 p-5 sm:grid-cols-2 xl:grid-cols-7">
            <div class="xl:col-span-2">
                <label class="filter-label">Search</label>
                <input x-model="search" @input="page=1" class="filter-input"
                    placeholder="Animal, farmer, bull ID/name, technician">
            </div>
            <div>
                <label class="filter-label">Cooperative</label>
                <select x-model="filterCooperative" @change="page=1" class="filter-input">
                    <option value="">All cooperatives</option>
                    <template x-for="cooperative in cooperatives" :key="cooperative.id"><option :value="String(cooperative.id)" x-text="cooperative.name"></option></template>
                </select>
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

        <div class="flex flex-wrap items-center gap-4 border-b px-5 py-3">
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" x-model="unreportedCalvesOnly" @change="if(unreportedCalvesOnly)showUnreportedCalves();else page=1" class="rounded border-slate-300">
                Positive PD with no calf-drop report
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" x-model="overdueCalvesOnly" @change="if(overdueCalvesOnly)showOverdueCalves(filterCooperative);else page=1" class="rounded border-slate-300">
                280+ days, calf drop not reported
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" x-model="includeReportedPregnancies" @change="page=1" class="rounded border-slate-300">
                Include reported pregnancies over 300 days (history)
            </label>
            @include('partials.calf-drop-notifications', ['inline' => true])
        </div>

        <div x-show="hiddenReportedCount()>0" x-cloak class="flex flex-col gap-2 border-b border-blue-100 bg-blue-50 px-5 py-3 text-sm text-blue-900 sm:flex-row sm:items-center sm:justify-between">
            <p><b x-text="hiddenReportedCount()"></b> imported or saved mating record(s) match your filters but are hidden because their calf drop is already reported.</p>
            <button type="button" @click="showReportedPregnancies()" class="shrink-0 rounded-lg border border-blue-200 bg-white px-3 py-2 font-semibold text-blue-800 hover:bg-blue-100">Show reported records</button>
        </div>

        <div class="mating-table-scroll overflow-x-auto">
            <table class="mating-records-table w-full min-w-[2500px] table-fixed text-left text-sm">
                <colgroup>
                    <col class="mating-width-no"><col class="mating-width-cooperative"><col class="mating-width-farmer">
                    <col class="mating-width-animal"><col class="mating-width-date"><col class="mating-width-type">
                    <col span="16">
                </colgroup>
                <thead class="bg-slate-100 text-xs uppercase text-slate-600">
                    <tr>
                        <th scope="col" class="th mating-frozen mating-frozen-no">No.</th>
                        <th scope="col" class="th mating-frozen mating-frozen-cooperative">Cooperative</th>
                        <th scope="col" class="th mating-frozen mating-frozen-farmer">Farmer</th>
                        <th scope="col" class="th mating-frozen mating-frozen-animal">Animal ID</th>
                        <th scope="col" class="th mating-frozen mating-frozen-date">Mating Date</th>
                        <th scope="col" class="th mating-frozen mating-frozen-type">Mating Type</th>
                        <th class="th">Bull ID</th>
                        <th class="th">Bull Name</th>
                        <th class="th">Straws</th>
                        <th class="th">Estrus / Heat</th>
                        <th class="th">2nd Cycle (18–21 Days)</th>
                        <th class="th">Expected PD (60 Days)</th>
                        <th class="th">Expected PD (90 Days)</th>
                        <th class="th">Mating Technician</th>
                        <th class="th">PD Date</th>
                        <th class="th">Months of Pregnancy</th>
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
                            <td class="td mating-frozen mating-frozen-no text-slate-500" x-text="(page-1)*perPage+i+1"></td>
                            <td class="td mating-frozen mating-frozen-cooperative" x-text="r.cooperative||'—'"></td>
                            <td class="td mating-frozen mating-frozen-farmer" x-text="r.farmer||'—'"></td>
                            <td class="td mating-frozen mating-frozen-animal font-bold" x-text="r.animal_id||'—'"></td>
                            <td class="td mating-frozen mating-frozen-date" x-text="displayDate(r.breeding_date)"></td>
                            <td class="td mating-frozen mating-frozen-type"><span class="pcc-type-pill" :class="r.mating_type==='NM'?'pcc-type-nm':'pcc-type-ai'" x-text="r.mating_type==='NM'?'NM · Natural':'AI · Artificial Insemination'"></span></td>
                            <td class="td" x-text="r.bull_id||'—'"></td>
                            <td class="td" x-text="r.bull_name||'—'"></td>
                            <td class="td" x-text="r.mating_type==='AI'?(r.straws||'—'):'—'"></td>
                            <td class="td" x-text="r.mating_type==='AI'?(r.heat_status||'—'):'—'"></td>
                            <td class="td" x-text="secondCycleRange(r.breeding_date)"></td>
                            <td class="td" x-text="expectedPDDate(r.breeding_date,60)"></td>
                            <td class="td" x-text="expectedPDDate(r.breeding_date,90)"></td>
                            <td class="td" x-text="r.mating_technician||'—'"></td>
                            <td class="td" x-text="displayDate(r.pd_date)"></td>
                            <td class="td" x-text="r.pregnancy_months!==''&&r.pregnancy_months!==null&&r.pregnancy_months!==undefined?r.pregnancy_months:'—'"></td>
                            <td class="td">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold"
                                    :class="pdClass(r.pd_result)" x-text="r.pd_result||'Pending'"></span>
                            </td>
                            <td class="td" x-text="r.pd_technician||'—'"></td>
                            <td class="td" x-text="r.pd_result==='Positive'?pregnancyDays(r)+' days':'—'"></td>
                            <td class="td" x-text="r.pd_result==='Positive'?displayDate(r.expected_calving||calculateExpectedCalving(r.breeding_date)):'—'"></td>
                            <td class="td"><div x-text="displayDate(r.actual_calving)"></div><span x-show="isUnreportedCalf(r)" class="mt-1 inline-block rounded bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-800">Calf drop not reported</span><span x-show="r.pregnancy_followup_complete" class="mt-1 inline-block rounded bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-800">Calf drop reported</span></td>
                            <td class="td whitespace-nowrap">
                                <button type="button" @click="view(r)" class="mr-1 rounded-lg border px-3 py-2" title="View">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                <button type="button" @click="edit(r)" class="mr-1 rounded-lg border px-3 py-2" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button type="button" @click="confirmDelete(r)" class="rounded-lg px-3 py-2 text-red-600" title="Delete">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="filteredRecords().length===0">
                        <td colspan="22" class="px-5 py-12 text-center text-slate-500" x-text="hiddenReportedCount()>0?'No active mating records match your filters. Use Show reported records to view the imported history.':(filterYear?'No mating records for '+filterYear+' yet.':'No mating records found.')"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        @include('partials.table-pagination', ['count' => 'filteredRecords().length', 'page' => 'page', 'size' => 'perPage', 'pages' => 'totalPages()', 'unfilteredCount' => 'records.length'])
    </section>

    <footer class="py-5 text-center text-xs text-slate-400">CBED Data System · Mating & Pregnancy Monitoring</footer>

    {{-- BULK ADD / EDIT MODAL --}}
    <div x-show="showBulk" x-cloak @keydown.escape.window="if(showBulk&&!saving&&!editingId&&!$refs.pendingPdDialog.open)closeModal()" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-5">
        <div @click.outside="if(!editingId)closeModal()" role="dialog" aria-modal="true" aria-labelledby="mating-modal-title" class="max-h-[95vh] w-full overflow-hidden rounded-2xl bg-white shadow-2xl" :class="editingId?'max-w-6xl':'max-w-[98vw]'">
            <div class="flex items-start justify-between border-b p-5">
                <div>
                    <h3 id="mating-modal-title" class="text-xl font-bold" x-text="editingId?'Edit Mating Record':'Bulk Add Mating Records'"></h3>
                    <p class="mt-1 text-sm text-slate-500" x-text="editingId?'Update mating and pregnancy details below.':'Choose the cooperative once. Farmer options are filtered to that cooperative.'"></p>
                </div>
                <div class="flex items-center gap-3">
                    <b x-show="!editingId" class="text-sm" x-text="bulkRows.length+' row(s)'"></b>
                    <button type="button" @click="closeModal()" aria-label="Close mating form" class="cbed-close-button">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>

            <div class="max-h-[calc(95vh-90px)] overflow-auto p-4 sm:p-5">
                <div class="mb-5 grid grid-cols-1 items-start gap-3 rounded-xl border bg-slate-50 p-4" :class="editingId?'':'md:grid-cols-2'">
                    <div class="min-w-0">
                        <label x-show="editingId" class="form-label" for="mating-edit-cooperative">Cooperative</label>
                        <input id="mating-edit-cooperative" x-show="editingId" readonly :value="bulkRows[0]?.cooperative || cooperatives.find(c=>String(c.id)===selectedCooperative)?.name || ''" class="form-input h-11 bg-slate-100">
                        <div x-show="!editingId">
                            <span class="form-label">Select Cooperative *</span>
                            <x-mating-search-picker label="Cooperative" placeholder="Select cooperative" items="cooperatives"
                                selected="selectedCooperative" on-select="value => { selectedCooperative = value; cooperativeChanged(); }" />
                        </div>
                    </div>
                    <div x-show="!editingId" class="min-w-0">
                        <span id="mating-lookup-label" class="form-label">Database Lookup Status</span>
                        <div role="status" aria-labelledby="mating-lookup-label" class="flex h-11 items-center rounded-lg border border-slate-300 bg-white px-3">
                            <p class="text-sm" :class="lookupReady?'text-emerald-700':'text-amber-700'"
                                x-text="lookupReady?'Database lookup lists loaded':'Select a cooperative to load farmers'"></p>
                        </div>
                    </div>
                    <div x-show="!editingId" class="flex flex-wrap items-center gap-x-2 gap-y-1 border-t pt-2 text-xs text-slate-600 md:col-span-2">
                        <span>Active bulls</span>
                        <span class="rounded-full bg-blue-50 px-2 py-0.5 font-bold text-blue-700" x-text="totalActiveBulls"></span>
                        <span><span x-text="semenBulls.length"></span> loaded into the selector</span>
                    </div>
                </div>

                @include('partials.mating-edit-form')

                <div x-show="!editingId" class="mb-4 flex flex-wrap justify-end gap-2">
                    <button type="button" x-show="!editingId" @click="addRow()" class="cbed-add-button inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold">
                        <i class="fa-solid fa-plus"></i> Add Row
                    </button>
                </div>

                <div x-show="!editingId" class="overflow-x-auto rounded-xl border">
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
                                <th class="p-3">Mating Technician</th>
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
                                        <x-mating-search-picker label="Farmer" placeholder="Select farmer" items="farmers"
                                            selected="r.farmer_id" on-select="value => { r.farmer_id = value; farmerChanged(r); }" />
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
                                            <option value="NM">Natural Mating (NM)</option>
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
                                        <select x-model="r.heat_status" :disabled="r.mating_type!=='AI'" class="form-input">
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
                                        <div class="flex items-center gap-2">
                                            <input x-model="r.remarks" class="form-input min-w-0" placeholder="Remarks">
                                            <button x-show="pendingPdRecord(r)" type="button" @click="openPendingPd(r)"
                                                class="shrink-0 whitespace-nowrap rounded-lg border border-amber-400 bg-amber-100 px-2 py-2 text-xs font-bold text-amber-950 shadow-[0_0_10px_#f59e0b] hover:bg-amber-200 motion-safe:animate-pulse">
                                                <i class="fa-solid fa-bell" aria-hidden="true"></i> Pending PD
                                            </button>
                                            <span x-show="!pendingPdRecord(r) && activePositivePdRecord(r)" class="shrink-0 whitespace-nowrap rounded-lg bg-rose-50 px-2 py-2 text-xs font-semibold text-rose-700">Positive PD</span>
                                        </div>
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

                <div class="mt-4 flex flex-col gap-3 rounded-xl bg-slate-50 p-4 sm:flex-row sm:items-center" :class="editingId?'sm:justify-end':'sm:justify-between'">
                    <div x-show="!editingId">
                        <b class="text-sm">Batch total: <span x-text="bulkRows.length"></span> row(s)</b>
                        <p class="help-text">New records start Pending. Positive/Negative PD requires a PD date and confirming technician. Positive PD also requires pregnancy months.</p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="closeModal()" class="action-secondary" x-text="editingId?'Close':'Cancel'"></button>
                        <button type="button" @click.stop="editingId ? updateRecord() : saveBulk()" :disabled="saving||(editingId&&!bulkRows.length)" class="cbed-save-button">
                            <span x-text="saving?'Saving&hellip;':(editingId?'Update Record':'Save All Records')"></span>
                        </button>
                    </div>
                </div>
            </div>
            <dialog x-ref="pendingPdDialog" aria-labelledby="pending-pd-title"
                @keydown.escape.stop @cancel="if (pdSaving) $event.preventDefault()"
                class="m-auto max-h-[90vh] w-11/12 max-w-xl overflow-auto rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-950/60">
                <form @submit.prevent="savePendingPd()">
                    <header class="flex items-center justify-between gap-4 border-b p-5">
                        <div>
                            <h3 id="pending-pd-title" class="text-lg font-bold text-slate-900">Update Pending PD</h3>
                            <p class="mt-1 text-sm text-slate-600" x-text="pdDraft ? pdDraft.animal_id + ' · Mating on ' + displayDate(pdDraft.breeding_date) : ''"></p>
                        </div>
                        <button type="button" @click="$refs.pendingPdDialog.close()" :disabled="pdSaving" aria-label="Close PD update" class="cbed-close-button">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </header>
                    <template x-if="pdDraft">
                        <div class="grid gap-4 p-5 sm:grid-cols-2">
                            <label class="text-sm font-semibold">PD Result
                                <select x-model="pdDraft.pd_result" @change="pdChanged(pdDraft)" required class="form-input mt-1">
                                    <option value="">Select result</option><option>Negative</option><option>Positive</option>
                                </select>
                            </label>
                            <label class="text-sm font-semibold">PD Date
                                <input type="date" x-model="pdDraft.pd_date" :min="pdDraft.breeding_date || null" required class="form-input mt-1">
                            </label>
                            <label class="text-sm font-semibold sm:col-span-2">PD Technician
                                <input type="text" x-model.trim="pdDraft.pd_technician" maxlength="255" required class="form-input mt-1">
                            </label>
                            <label x-show="pdDraft.pd_result === 'Positive'" class="text-sm font-semibold">Months of Pregnancy
                                <input type="number" x-model="pdDraft.pregnancy_months" min="1" max="11" step="1" :required="pdDraft.pd_result === 'Positive'" class="form-input mt-1">
                            </label>
                            <label x-show="pdDraft.pd_result === 'Positive'" class="text-sm font-semibold">Expected Calving
                                <input type="date" x-model="pdDraft.expected_calving" class="form-input mt-1">
                            </label>
                            <p x-show="pdError" role="alert" class="text-sm text-rose-700 sm:col-span-2" x-text="pdError"></p>
                        </div>
                    </template>
                    <footer class="flex justify-end gap-2 border-t bg-slate-50 p-4">
                        <button type="button" @click="$refs.pendingPdDialog.close()" :disabled="pdSaving" class="action-secondary">Cancel</button>
                        <button type="submit" :disabled="pdSaving" class="cbed-save-button" x-text="pdSaving ? 'Saving&hellip;' : 'Update Record'"></button>
                    </footer>
                </form>
            </dialog>
        </div>
    </div>

    {{-- VIEW MODAL --}}
    <div x-show="showView" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/60 p-4">
        <div @click.outside="showView=false" class="w-full max-w-5xl overflow-hidden rounded-xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b p-5">
                <h3 class="text-lg font-bold">Mating Record Details</h3>
                <button type="button" @click="showView=false" class="cbed-close-button" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
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
    <dialog x-ref="deleteDialog" aria-labelledby="delete-mating-title" aria-describedby="delete-mating-description"
        @cancel="if(deleting)$event.preventDefault()" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-950/60">
        <div class="p-6">
            <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-rose-100 text-rose-700" aria-hidden="true">
                <i class="fa-solid fa-trash-can"></i>
            </div>
            <h2 id="delete-mating-title" class="text-lg font-bold">Delete mating record?</h2>
            <p id="delete-mating-description" class="mt-2 text-sm text-slate-600">This permanently deletes the selected mating record.</p>
            <dl class="mt-4 grid grid-cols-2 gap-3 rounded-lg bg-slate-50 p-3 text-sm">
                <div><dt class="text-slate-500">Animal ID</dt><dd class="break-words font-semibold" x-text="deleteRecord?.animal_id || '—'"></dd></div>
                <div><dt class="text-slate-500">Mating date</dt><dd class="font-semibold" x-text="displayDate(deleteRecord?.breeding_date)"></dd></div>
            </dl>
            <p x-show="deleteError" role="alert" class="mt-3 text-sm text-rose-700" x-text="deleteError"></p>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" autofocus @click="$refs.deleteDialog.close()" :disabled="deleting" class="action-secondary disabled:opacity-50">Cancel</button>
                <button type="button" @click="remove()" :disabled="deleting" class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800 disabled:opacity-50" x-text="deleting?'Deleting…':'Delete record'"></button>
            </div>
        </div>
    </dialog>

    @include('partials.action-toast')
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
.mating-table-scroll{container-type:inline-size}
.mating-records-table{
    --mating-no:3.5rem;--mating-cooperative:12rem;--mating-farmer:10rem;
    --mating-animal:8rem;--mating-date:9rem;--mating-type:11rem;
    border-collapse:separate;border-spacing:0;
}
.mating-width-no{width:var(--mating-no)}
.mating-width-cooperative{width:var(--mating-cooperative)}
.mating-width-farmer{width:var(--mating-farmer)}
.mating-width-animal{width:var(--mating-animal)}
.mating-width-date{width:var(--mating-date)}
.mating-width-type{width:var(--mating-type)}
.mating-records-table :is(th,td){overflow-wrap:anywhere}
.mating-records-table .mating-frozen{position:sticky;padding-inline:.625rem;box-shadow:1px 0 0 #e2e8f0}
.mating-records-table tbody td{border-bottom:1px solid #f1f5f9}
.mating-records-table tbody .mating-frozen{z-index:2;background-color:#fff}
.pcc-records-panel .mating-records-table tbody tr:hover .mating-frozen{background-color:#f0f8fb}
.mating-records-table thead .mating-frozen{z-index:3;background:#103c60;box-shadow:none}
.mating-records-table thead th{border-bottom:3px solid #e8b22f}
.mating-frozen-no{left:0}
.mating-frozen-cooperative{left:var(--mating-no)}
.mating-frozen-farmer{left:calc(var(--mating-no) + var(--mating-cooperative))}
.mating-frozen-animal{left:calc(var(--mating-no) + var(--mating-cooperative) + var(--mating-farmer))}
.mating-frozen-date{left:calc(var(--mating-no) + var(--mating-cooperative) + var(--mating-farmer) + var(--mating-animal))}
.mating-frozen-type{left:calc(var(--mating-no) + var(--mating-cooperative) + var(--mating-farmer) + var(--mating-animal) + var(--mating-date))}
@container (max-width:760px){
    .mating-records-table :is(.mating-frozen-date,.mating-frozen-type){position:static;box-shadow:none}
}
@container (max-width:540px){
    .mating-records-table :is(.mating-frozen-farmer,.mating-frozen-animal){position:static;box-shadow:none}
}
.pcc-records-panel .filter-input:focus{border-color:#0786a5;box-shadow:0 0 0 3px rgba(7,134,165,.14)}
.pcc-type-pill{display:inline-flex;align-items:center;white-space:nowrap;border-radius:999px;padding:.35rem .65rem;font-size:.7rem;font-weight:800}
.mating-records-table .pcc-type-pill{white-space:normal}
.pcc-type-ai{background:#fff0e3;color:#a94b0b}.pcc-type-nm{background:#e6f5eb;color:#176342}
.pcc-records-panel tbody tr{transition:background-color .14s ease}.pcc-records-panel tbody tr:hover{background:#f0f8fb}
</style>
@endpush

@include('partials.mating-entry-script')
