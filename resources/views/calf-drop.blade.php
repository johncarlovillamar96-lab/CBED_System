{{-- resources/views/calf-drop.blade.php --}}
@extends('layouts.admin')
@section('inlineCalfDropNotifications', '1')
@section('title', 'Calf Records | CBED')
@section('page-heading', 'Calf Records')

@section('content')
<div x-data="calfDropPage()" x-cloak class="calf-records-page management-page min-w-0 bg-slate-50 text-slate-800">
    <x-admin-page-header title="Calf Records" description="Review births and manage calf records linked to Carabao Inventory." section="Animal Management">
        <button type="button" @click="exportCSV()" class="inline-flex items-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-semibold shadow-sm"><i class="fa-solid fa-file-export"></i> Export</button>
        <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-semibold shadow-sm"><i class="fa-solid fa-file-import"></i> Import<input type="file" accept=".csv,text/csv" class="hidden" @change="importCSV($event)"></label>
        <button type="button" @click="openAdd()" class="cbed-add-button inline-flex items-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-semibold shadow-sm"><i class="fa-solid fa-plus"></i> Add Calf Record</button>
        <button type="button" @click="loadInventoryCalves(true)" :disabled="inventoryLoading" class="action-secondary disabled:opacity-50">
            <i class="fa-solid fa-rotate" :class="inventoryLoading ? 'fa-spin' : ''"></i> Refresh
        </button>
    </x-admin-page-header>

    {{-- Summary cards --}}
    <div class="mb-7 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <template x-for="card in summaryCards" :key="card.title"><div class="cbed-card dashboard-stat-card management-summary-card p-5"><div class="flex items-center justify-between"><div><p class="text-sm font-medium text-slate-500" x-text="card.title"></p><h3 class="mt-3 text-3xl font-bold text-slate-800" x-text="card.value"></h3><p class="mt-2 text-xs text-slate-400" x-text="card.description"></p></div><div class="dashboard-stat-icon flex h-12 w-12 items-center justify-center rounded-xl text-xl"><i :class="card.icon"></i></div></div></div></template>
    </div>

    {{-- Distribution panels --}}
    <section class="mb-6 grid grid-cols-1 gap-4 xl:grid-cols-2">
        <article class="cbed-card dashboard-panel min-w-0 overflow-hidden rounded-xl border" aria-labelledby="calves-by-province-title">
            <header class="flex items-start gap-3 border-b border-slate-200 p-5">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-700" aria-hidden="true"><i class="fa-solid fa-map-location-dot"></i></span>
                <div>
                    <h2 id="calves-by-province-title" class="font-bold text-slate-900">Calf Records by Province</h2>
                    <p class="mt-1 text-xs text-slate-500" x-text="filteredRecords.length + ' matching ' + (filteredRecords.length === 1 ? 'record' : 'records')"></p>
                </div>
            </header>
            <div class="max-h-72 overflow-y-auto p-5">
                <ol class="space-y-4">
                    <template x-for="province in distribution('province')" :key="province.name">
                        <li>
                            <div class="mb-1.5 flex justify-between gap-3 text-sm">
                                <span class="min-w-0 break-words font-medium text-slate-700" x-text="province.name"></span>
                                <span class="shrink-0 font-semibold tabular-nums text-slate-900" x-text="province.count"></span>
                            </div>
                            <div class="h-2 rounded-full bg-slate-100" aria-hidden="true"><div class="h-full rounded-full bg-blue-600" :style="`width:${province.percent}%`"></div></div>
                        </li>
                    </template>
                </ol>
                <p x-show="!filteredRecords.length" class="rounded-lg bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">No records match the current filters.</p>
            </div>
        </article>
        <article class="cbed-card dashboard-panel min-w-0 overflow-hidden rounded-xl border" aria-labelledby="calves-by-year-title">
            <header class="flex items-start gap-3 border-b border-slate-200 p-5">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700" aria-hidden="true"><i class="fa-regular fa-calendar"></i></span>
                <div>
                    <h2 id="calves-by-year-title" class="font-bold text-slate-900">Calf Records by Birth Year</h2>
                    <p class="mt-1 text-xs text-slate-500">Grouped by birth or event date across all reported years.</p>
                </div>
            </header>
            <div class="max-h-72 overflow-y-auto p-5">
                <ol class="space-y-3">
                    <template x-for="item in birthYearSummary" :key="item.year">
                        <li class="grid grid-cols-[3rem_minmax(0,1fr)_2.5rem] items-center gap-3 text-sm">
                            <span class="font-medium tabular-nums text-slate-700" x-text="item.year"></span>
                            <div class="h-2.5 rounded-full bg-slate-100" aria-hidden="true"><div class="h-full rounded-full bg-emerald-600" :style="`width:${item.percent}%`"></div></div>
                            <span class="text-right font-semibold tabular-nums text-slate-900" x-text="item.count"></span>
                        </li>
                    </template>
                </ol>
                <p x-show="!birthYearSummary.length" class="rounded-lg bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">No matching records have a birth or event date.</p>
            </div>
        </article>
    </section>

    @include('partials.calf-drop-notifications')

    {{-- Directory and filters --}}
    <section class="cbed-card dashboard-panel overflow-hidden rounded-xl border">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-lg font-bold text-slate-900">Calf Records</h2><p class="mt-1 text-sm text-slate-500">Search, filter, view, import, export, or add calf records.</p></div><div class="flex flex-wrap items-center gap-2"><span class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700"><span x-text="filteredRecords.length"></span> records</span><button type="button" @click="resetFilters()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"><i class="fa-solid fa-rotate-left mr-1"></i> Reset Filters</button></div></div>
        <div class="grid grid-cols-1 gap-3 border-b border-slate-200 bg-slate-50 p-5 sm:grid-cols-2 xl:grid-cols-6">
            <div class="min-w-0 xl:col-span-2">
                <label for="calf-record-search" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search Records</label>
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                    <input id="calf-record-search" x-ref="recordSearch" type="search" x-model="search" @input="page=1" @keydown.escape="search='';page=1" placeholder="Calf ID, farmer, or location" class="filter-input calf-record-search">
                    <button x-show="search" type="button" @click="search='';page=1;$refs.recordSearch.focus()" aria-label="Clear search" class="absolute inset-y-1 right-1 flex w-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-800">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div><label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Province</label><select x-model="filterProvince" @change="page=1" class="filter-input"><option value="">All Provinces</option><template x-for="v in options('province')" :key="v"><option :value="v" x-text="v"></option></template></select></div>
            <div><label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Breed</label><select x-model="filterBreed" @change="page=1" class="filter-input"><option value="">All Breeds</option><template x-for="v in options('breed')" :key="v"><option :value="v" x-text="v"></option></template></select></div>
            <div><label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Sex</label><select x-model="filterSex" @change="page=1" class="filter-input"><option value="">All Calf Sexes</option><option value="Male">Male</option><option value="Female">Female</option></select></div>
            <div><label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Reported Year</label><select x-model="filterYear" @change="page=1" class="filter-input"><option value="">All Years</option><template x-for="v in reportedYearOptions()" :key="v"><option :value="String(v)" x-text="v"></option></template></select></div>
        </div>
        <div class="overflow-x-auto" role="region" aria-label="Calf records table" tabindex="0">
            <table class="calf-records-table w-full min-w-[1900px] text-left text-sm">
                <thead class="text-xs uppercase tracking-wide">
                    <tr>
                        <th scope="col">No.</th>
                        <th scope="col">Calf ID</th>
                        <th scope="col">Mother ID</th>
                        <th scope="col">Farmer</th>
                        <th scope="col">Province</th>
                        <th scope="col">Cooperative</th>
                        <th scope="col">Birth / Event Date</th>
                        <th scope="col">Sex</th>
                        <th scope="col">Breed</th>
                        <th scope="col">Sire / Bull ID</th>
                        <th scope="col">Reported Year</th>
                        <th scope="col">Outcome</th>
                        <th scope="col">Animal Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
            <template x-for="(r, i) in paginatedRecords()" :key="r.id"><tr class="hover:bg-blue-50/50"><td class="px-4 py-4 text-slate-500" x-text="(page-1)*perPage+i+1"></td><td class="px-4 py-4 font-bold text-slate-900"><span x-text="r.calf_id || '—'"></span></td><td class="px-4 py-4" x-text="r.mother_id || '—'"></td><td class="px-4 py-4" x-text="r.farmer || '—'"></td><td class="px-4 py-4" x-text="r.province || '—'"></td><td class="px-4 py-4" x-text="r.cooperative || '—'"></td><td class="px-4 py-4" x-text="formatBirthDate(r.date_of_birth || r.monitored_date)"></td><td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="r.sex==='Male'?'bg-blue-50 text-blue-700':r.sex==='Female'?'bg-pink-50 text-pink-700':'bg-slate-100 text-slate-600'" x-text="r.sex || '—'"></span></td><td class="px-4 py-4" x-text="r.breed || '—'"></td><td class="px-4 py-4" x-text="r.sire_id || '—'"></td><td class="px-4 py-4" x-text="r.reported_year || '—'"></td><td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="r.birth_outcome==='Stillbirth'?'bg-rose-50 text-rose-700':r.birth_outcome==='Abortion'?'bg-amber-50 text-amber-700':'bg-emerald-50 text-emerald-700'" x-text="r.birth_outcome || 'Live Birth'"></span></td><td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="r.animal_status==='Live'?'bg-emerald-50 text-emerald-700':r.animal_status==='Out'?'bg-slate-100 text-slate-700':r.animal_status==='Dead'?'bg-rose-50 text-rose-700':'bg-amber-50 text-amber-700'" x-text="r.animal_status || '—'"></span></td><td class="whitespace-nowrap px-4 py-4"><button type="button" @click="view(r)" class="mr-1 rounded-lg border border-slate-200 px-3 py-2 hover:bg-blue-50" title="View details"><i class="fa-solid fa-eye"></i></button><button type="button" @click="startEdit(r)" class="mr-1 rounded-lg border border-blue-200 px-3 py-2 text-blue-700 hover:bg-blue-50" title="Edit calf record"><i class="fa-solid fa-pen-to-square"></i></button><button type="button" @click="remove(r)" :disabled="deletingCalf" aria-label="Delete calf record" class="rounded-lg px-3 py-2 text-red-600 hover:bg-red-50" title="Delete record"><i class="fa-solid fa-trash"></i></button></td></tr></template>
            <tr x-show="filteredRecords.length===0"><td colspan="14" class="px-5 py-12 text-center text-slate-500"><i class="fa-solid fa-folder-open mb-2 block text-2xl text-slate-300"></i>No calf drop records found. Change your filters or add records.</td></tr>
                </tbody>
            </table>
        </div>
        @include('partials.table-pagination', ['count' => 'filteredRecords.length', 'page' => 'page', 'size' => 'perPage', 'pages' => 'totalPages()', 'unfilteredCount' => 'records.length'])
    </section>
    <footer class="py-5 text-center text-xs text-slate-400">CBED Data System · Calf Records</footer>

    <div x-cloak x-show="showAdd" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-5">
        <form @submit.prevent="saveCalf()" role="dialog" aria-modal="true" aria-labelledby="add-calf-title" class="flex max-h-[94vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <header class="flex shrink-0 items-center justify-between border-b border-slate-200 p-5">
                <h3 id="add-calf-title" class="text-xl font-bold text-slate-900">Add Calf Record</h3>
                <button type="button" @click="showAdd=false" :disabled="savingCalf" aria-label="Close add calf form" class="rounded-lg p-2 hover:bg-slate-100"><i class="fa-solid fa-xmark"></i></button>
            </header>
            <div class="min-h-0 overflow-y-auto p-4 sm:p-5">
                <div class="mb-4 flex flex-wrap gap-2" role="group" aria-label="Calf entry method">
                    <button type="button" @click="setEntryMode(true)" :aria-pressed="showMatingPicker" :disabled="savingCalf" class="rounded-lg border px-3 py-2 text-sm font-semibold" :class="showMatingPicker ? 'border-sky-700 bg-sky-50 text-sky-900' : 'border-slate-200 text-slate-600'">Use a mating record</button>
                    <button type="button" @click="setEntryMode(false)" :aria-pressed="!showMatingPicker" :disabled="savingCalf" class="rounded-lg border px-3 py-2 text-sm font-semibold" :class="!showMatingPicker ? 'border-sky-700 bg-sky-50 text-sky-900' : 'border-slate-200 text-slate-600'">Manual Entry</button>
                </div>
                <section x-show="showMatingPicker" class="mb-4 rounded-xl border border-slate-200 bg-white p-4" aria-labelledby="calf-drop-due-title">
                    <h4 id="calf-drop-due-title" class="font-semibold text-slate-900">Mating records at 280+ pregnancy days</h4>
                    <p class="mt-1 text-sm text-slate-500">Select a mother awaiting a calf-drop report to prefill the form.</p>
                    <p x-show="loadingDueMatings" role="status" class="mt-3 text-sm text-slate-500">Loading mating records…</p>
                    <div x-show="dueMatingsError" class="mt-3 flex items-center gap-3 text-sm text-rose-700">
                        <span role="alert" x-text="dueMatingsError"></span>
                        <button type="button" @click="loadDueMatings()" class="font-semibold underline">Retry</button>
                    </div>
                    <p x-show="!loadingDueMatings && !dueMatingsError && !dueMatings.length" class="mt-3 text-sm text-slate-500">No matching pregnancies are awaiting a calf-drop report.</p>
                    <ul x-show="dueMatings.length" class="mt-3 max-h-48 divide-y divide-slate-100 overflow-y-auto">
                        <template x-for="mating in dueMatings" :key="mating.id">
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                                <div>
                                    <span class="font-semibold text-slate-900" x-text="mating.animal_id || mating.carabao_id"></span>
                                    <span class="ml-2 text-slate-600" x-text="mating.cooperative_name || ''"></span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="mating.calf_drop_overdue ? 'bg-amber-100 text-amber-900' : 'bg-blue-50 text-blue-800'" x-text="mating.pregnancy_days + ' days'"></span>
                                    <button type="button" @click="addFromMating(mating)" :disabled="savingCalf" class="rounded-lg border border-slate-300 px-3 py-2 font-semibold text-slate-800 hover:bg-slate-50">Use mating record</button>
                                </div>
                            </li>
                        </template>
                    </ul>
                </section>
                <fieldset :disabled="savingCalf" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <template x-if="!showMatingPicker">
                        <div class="grid gap-4 sm:col-span-2 sm:grid-cols-2 lg:col-span-3">
                            <div class="relative text-sm" @click.outside="cooperativePickerOpen=false" @keydown.escape.stop="if(cooperativePickerOpen){cooperativePickerOpen=false;$refs.cooperativeTrigger.focus()}">
                                <span id="calf-cooperative-label" class="font-semibold">Cooperative <span class="text-rose-600">*</span></span>
                                <button type="button" x-ref="cooperativeTrigger" aria-labelledby="calf-cooperative-label calf-cooperative-value" aria-controls="calf-cooperative-picker" :aria-expanded="cooperativePickerOpen" @click="toggleCooperativePicker()" class="form-input mt-1 flex items-center justify-between gap-2 text-left">
                                    <span id="calf-cooperative-value" x-text="addForm.cooperative || (inventoryLoading ? 'Loading cooperatives…' : 'Select cooperative')"></span>
                                    <i class="fa-solid fa-chevron-down text-xs text-slate-500" aria-hidden="true"></i>
                                </button>
                                <div id="calf-cooperative-picker" x-show="cooperativePickerOpen" class="absolute inset-x-0 z-10 mt-1 rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                                    <input type="search" x-ref="cooperativeSearch" x-model="cooperativeQuery" @keydown.enter.prevent="if(matchingCooperatives.length===1)selectManualCooperative(matchingCooperatives[0])" aria-label="Search cooperatives" placeholder="Search cooperatives…" class="form-input">
                                    <ul class="mt-1 max-h-40 overflow-y-auto">
                                        <template x-for="cooperative in matchingCooperatives" :key="cooperative.id">
                                            <li><button type="button" @click="selectManualCooperative(cooperative)" class="w-full rounded-md px-2 py-2 text-left hover:bg-sky-50 focus:bg-sky-50" x-text="cooperative.cooperative_name"></button></li>
                                        </template>
                                    </ul>
                                    <p x-show="!matchingCooperatives.length" class="px-2 py-2 text-slate-500" x-text="inventoryLoading ? 'Loading cooperatives…' : 'No matching cooperatives.'"></p>
                                </div>
                            </div>
                            <div class="relative text-sm" @click.outside="farmerPickerOpen=false" @keydown.escape.stop="if(farmerPickerOpen){farmerPickerOpen=false;$refs.farmerTrigger.focus()}">
                                <span id="calf-farmer-label" class="font-semibold">Farmer <span class="text-rose-600">*</span></span>
                                <button type="button" x-ref="farmerTrigger" aria-labelledby="calf-farmer-label calf-farmer-value" aria-controls="calf-farmer-picker" :aria-expanded="farmerPickerOpen" :disabled="!manualFarmers.length" @click="toggleFarmerPicker()" class="form-input mt-1 flex items-center justify-between gap-2 text-left disabled:opacity-60">
                                    <span id="calf-farmer-value" x-text="addForm.farmer || (!addForm.cooperative_id ? 'Select a cooperative first' : (manualFarmers.length ? 'Select farmer' : 'No farmers in this cooperative'))"></span>
                                    <i class="fa-solid fa-chevron-down text-xs text-slate-500" aria-hidden="true"></i>
                                </button>
                                <div id="calf-farmer-picker" x-show="farmerPickerOpen" class="absolute inset-x-0 z-10 mt-1 rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                                    <input type="search" x-ref="farmerSearch" x-model="farmerQuery" @keydown.enter.prevent="if(matchingManualFarmers.length===1)selectManualFarmer(matchingManualFarmers[0])" aria-label="Search farmers" placeholder="Search farmers…" class="form-input">
                                    <ul class="mt-1 max-h-40 overflow-y-auto">
                                        <template x-for="farmer in matchingManualFarmers" :key="farmer.id">
                                            <li><button type="button" @click="selectManualFarmer(farmer)" class="w-full rounded-md px-2 py-2 text-left hover:bg-sky-50 focus:bg-sky-50" x-text="farmerLabel(farmer)"></button></li>
                                        </template>
                                    </ul>
                                    <p x-show="!matchingManualFarmers.length" class="px-2 py-2 text-slate-500">No matching farmers.</p>
                                </div>
                            </div>
                        </div>
                    </template>
                    <label class="text-sm font-semibold">Mother ID <span class="text-rose-600">*</span>
                        <input x-show="showMatingPicker" :value="addForm.mother_id" readonly class="form-input mt-1">
                        <template x-if="!showMatingPicker">
                            <select x-model="addForm.mother_id" @change="refreshGeneratedId(addForm)" required :disabled="loadingDueMatings || !!dueMatingsError || !manualMothers.length" class="form-input mt-1">
                                <option value="" x-text="loadingDueMatings ? 'Checking mating records…' : (dueMatingsError ? 'Mating records unavailable' : (!addForm.farmer_id ? 'Select a farmer first' : (manualMothers.length ? 'Select mother' : 'No live, breedable females available')))"></option>
                                <template x-for="mother in manualMothers" :key="mother.id"><option :value="mother.animal_id" x-text="mother.animal_name ? mother.animal_id + ' — ' + mother.animal_name : mother.animal_id"></option></template>
                            </select>
                        </template>
                        <button x-show="!showMatingPicker && dueMatingsError" type="button" @click="loadDueMatings()" class="mt-1 text-sm text-rose-700 underline">Retry loading mating records</button>
                    </label>
                    <label class="text-sm font-semibold">Birth or event date <span class="text-rose-600">*</span>
                        <input x-model="addForm.date_of_birth" type="date" required class="form-input mt-1">
                    </label>
                    <label class="text-sm font-semibold">Birth outcome
                        <select x-model="addForm.birth_outcome" @change="refreshGeneratedId(addForm)" class="form-input mt-1"><option>Live Birth</option><option>Stillbirth</option><option>Abortion</option></select>
                    </label>
                    <label class="text-sm font-semibold">Calf ID
                        <input x-model.trim="addForm.calf_id" placeholder="Generated if blank" :readonly="addForm.birth_outcome !== 'Live Birth'" class="form-input mt-1">
                    </label>
                    <label class="text-sm font-semibold">Sex <span x-show="addForm.birth_outcome === 'Live Birth'" class="text-rose-600">*</span>
                        <select x-model="addForm.sex" :required="addForm.birth_outcome === 'Live Birth'" class="form-input mt-1"><option value="">Select sex</option><option>Male</option><option>Female</option></select>
                    </label>
                    <label class="text-sm font-semibold">Breed
                        <select x-model="addForm.breed"  class="form-input mt-1"><option value="">Select breed</option><template x-for="option in breedOptions" :key="option"><option :value="option" x-text="option"></option></template></select>
                    </label>
                    <label class="text-sm font-semibold">Sire / Bull ID
                        <input x-model="addForm.sire_id"  class="form-input mt-1">
                    </label>
                    <label x-show="showMatingPicker" class="text-sm font-semibold">Farmer
                        <input :value="addForm.farmer" readonly class="form-input mt-1">
                    </label>
                    <label x-show="showMatingPicker" class="text-sm font-semibold">Cooperative
                        <input :value="addForm.cooperative" readonly class="form-input mt-1">
                    </label>
                    <label class="text-sm font-semibold">Province
                        <input x-model.trim="addForm.province" list="calf-provinces" class="form-input mt-1">
                        <datalist id="calf-provinces"><template x-for="province in provinceOptions" :key="province"><option :value="province"></option></template></datalist>
                    </label>
                    <label class="text-sm font-semibold">Municipality<input x-model.trim="addForm.municipality" class="form-input mt-1"></label>
                    <label class="text-sm font-semibold">Barangay<input x-model.trim="addForm.barangay" class="form-input mt-1"></label>
                    <label class="text-sm font-semibold">Monitored date
                        <input x-model="addForm.monitored_date" type="date" class="form-input mt-1">
                    </label>
                    <label class="text-sm font-semibold">Reported year
                        <input x-model="addForm.reported_year" type="number" min="2000" max="2100" @input="refreshGeneratedId(addForm)" class="form-input mt-1">
                    </label>
                    <label x-show="addForm.birth_outcome === 'Live Birth'" class="text-sm font-semibold">Animal status
                        <select x-model="addForm.animal_status"  class="form-input mt-1"><option>Live</option><option>Out</option><option>Dead</option><option>Not Applicable</option></select>
                    </label>
                    <label x-show="addForm.birth_outcome !== 'Live Birth'" class="text-sm font-semibold">Breed series
                        <input x-model="addForm.breed_series" @input="refreshGeneratedId(addForm)" class="form-input mt-1">
                    </label>
                    <label class="text-sm font-semibold sm:col-span-2 lg:col-span-3">Remarks<textarea x-model="addForm.remarks" rows="2" class="form-input mt-1"></textarea></label>
                </fieldset>
            </div>
            <footer class="flex shrink-0 justify-end gap-2 border-t border-slate-200 bg-slate-50 p-4">
                <button type="button" @click="showAdd=false" :disabled="savingCalf" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Cancel</button>
                <button type="submit" :disabled="savingCalf" class="rounded-lg bg-[#0b1f3a] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" x-text="savingCalf ? 'Saving...' : 'Save Calf Record'"></button>
            </footer>
        </form>
    </div>

    {{-- View modal --}}
    <div x-cloak x-show="showView" x-transition.opacity class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/60 p-4"><div @click.outside="showView = false" class="w-full max-w-3xl overflow-hidden rounded-xl bg-white shadow-xl"><div class="flex items-center justify-between border-b border-slate-200 p-5"><div><h3 class="text-lg font-bold">Calf Drop Record Details</h3><p class="mt-1 text-sm text-slate-500">Full calf birth record</p></div><button @click="showView = false" type="button" class="rounded-lg p-2 hover:bg-slate-100"><i class="fa-solid fa-xmark"></i></button></div><div class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3"><template x-for="f in fields" :key="f"><div class="rounded-lg border border-slate-200 p-3"><p class="text-xs text-slate-500" x-text="label(f)"></p><p class="mt-1 break-words font-semibold text-slate-800" x-text="selected && selected[f] != null && selected[f] !== '' ? selected[f] : '-' "></p></div></template></div><div class="flex justify-end border-t border-slate-200 p-4"><button @click="showView = false" type="button" class="rounded-lg bg-[#0b1f3a] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#122b4d]">Close</button></div></div></div>
    <div x-cloak x-show="showEdit" x-transition.opacity class="fixed inset-0 z-[65] flex items-center justify-center bg-slate-950/60 p-4"><div @click.outside="closeEdit()" class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-xl bg-white shadow-xl"><form @submit.prevent="saveEdit()"><div class="flex items-center justify-between border-b border-slate-200 p-5"><div><h3 class="text-lg font-bold">Edit Calf Record</h3><p class="mt-1 text-sm text-slate-500">Calf ID and mother ID are protected from changes.</p></div><button @click="closeEdit()" type="button" class="rounded-lg p-2 hover:bg-slate-100"><i class="fa-solid fa-xmark"></i></button></div><div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2"><div><label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Calf ID (locked)</label><input :value="editForm?.calf_id||''" readonly class="form-input bg-slate-100"></div><div><label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Mother ID (locked)</label><input :value="editForm?.mother_id||''" readonly class="form-input bg-slate-100"></div><div><label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Date of Birth</label><input type="date" x-model="editForm.date_of_birth" required class="form-input"></div><div><label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Sex</label><select x-model="editForm.sex" required class="form-input"><option value="Male">Male</option><option value="Female">Female</option></select></div><div x-show="editForm?.source!=='Calf Drop'"><label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Breed</label><input x-model="editForm.breed" class="form-input"></div><div><label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Sire / Bull ID</label><input x-model="editForm.sire_id" class="form-input"></div><div><label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Animal Status</label><select x-model="editForm.animal_status" class="form-input"><option value="Live">Live</option><option value="Out">Out</option><option value="Dead">Dead</option><option value="Not Applicable">Not Applicable</option></select></div><div x-show="editForm?.source!=='Inventory'"><label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Monitored Date</label><input type="date" x-model="editForm.monitored_date" class="form-input"></div><div x-show="editForm?.source!=='Inventory'"><label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Birth Outcome</label><select x-model="editForm.birth_outcome" class="form-input"><option value="Live Birth">Live Birth</option><option value="Stillbirth">Stillbirth</option><option value="Abortion">Abortion</option></select></div><div x-show="editForm?.source!=='Inventory'" class="sm:col-span-2"><label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Remarks</label><textarea x-model="editForm.remarks" rows="3" class="form-input"></textarea></div></div><div class="flex justify-end gap-2 border-t border-slate-200 p-4"><button @click="closeEdit()" type="button" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold">Cancel</button><button type="submit" :disabled="savingEdit" class="rounded-lg bg-[#0b1f3a] px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50"><i class="fa-solid fa-floppy-disk mr-2"></i><span x-text="savingEdit?'Saving…':'Save Changes'"></span></button></div></form></div></div>
    <dialog x-ref="deleteCalfDialog" aria-labelledby="delete-calf-title" aria-describedby="delete-calf-description"
        @cancel="if(deletingCalf)$event.preventDefault()" class="m-auto max-h-[90vh] w-11/12 max-w-2xl overflow-y-auto rounded-2xl bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-950/60">
        <div class="p-6">
            <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-rose-100 text-rose-700" aria-hidden="true"><i class="fa-solid fa-trash-can"></i></div>
            <h2 id="delete-calf-title" class="text-lg font-bold" x-text="finalDeleteConfirmation ? 'Are you sure you want to delete this calf record?' : 'Review calf record before deleting'"></h2>
            <p id="delete-calf-description" class="mt-2 text-sm text-slate-600" x-text="finalDeleteConfirmation ? (deleteTarget?.inventory_id ? 'The animal will be permanently removed from Carabao Inventory and Calf Records.' : 'This calf record will be permanently deleted.') : 'Check the details below, then select Delete record to continue.'"></p>
            <dl x-show="!finalDeleteConfirmation" class="mt-4 grid grid-cols-1 gap-3 rounded-lg bg-slate-50 p-4 text-sm sm:grid-cols-3">
                <template x-for="field in fields" :key="field">
                    <div :class="field === 'remarks' ? 'sm:col-span-3' : ''">
                        <dt class="capitalize text-slate-500" x-text="label(field)"></dt>
                        <dd class="whitespace-pre-wrap break-words font-semibold" x-text="['date_of_birth','monitored_date'].includes(field) ? formatBirthDate(deleteTarget?.[field]) : (deleteTarget?.[field] ?? '') || '—'"></dd>
                    </div>
                </template>
                <div x-show="deleteTarget?.birth_weight != null"><dt class="text-slate-500">Birth Weight</dt><dd class="font-semibold" x-text="deleteTarget?.birth_weight"></dd></div>
                <div x-show="deleteTarget?.source"><dt class="text-slate-500">Source</dt><dd class="font-semibold" x-text="deleteTarget?.source"></dd></div>
            </dl>
            <p x-show="finalDeleteConfirmation" class="mt-4 break-words rounded-lg bg-slate-50 p-3 text-sm font-semibold" x-text="'Calf ID: ' + (deleteTarget?.calf_id || '—') + ' · Mother ID: ' + (deleteTarget?.mother_id || '—')"></p>
            <p x-show="deleteCalfError" role="alert" class="mt-3 text-sm text-rose-700" x-text="deleteCalfError"></p>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" x-ref="cancelCalfDelete" autofocus @click="$refs.deleteCalfDialog.close()" :disabled="deletingCalf" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold disabled:opacity-50">Cancel</button>
                <button x-show="!finalDeleteConfirmation" type="button" @click="reviewCalfDelete()" class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800">Delete record</button>
                <button x-show="finalDeleteConfirmation" type="button" @click="confirmCalfDelete()" :disabled="deletingCalf" class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800 disabled:opacity-50" x-text="deletingCalf ? 'Deleting…' : 'Yes, delete permanently'"></button>
            </div>
        </div>
    </dialog>
    <div x-cloak x-show="notice" x-transition class="fixed bottom-5 right-5 z-[70] max-w-sm rounded-xl px-5 py-3 text-sm font-medium text-white shadow-xl" x-text="notice"></div>
</div>
@endsection

@push('styles')
<style>
    .calf-records-table { border-collapse: separate; border-spacing: 0; }
    .calf-records-table thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        padding: .75rem 1rem;
        background: #103c60;
        color: #fff;
        border-bottom: 3px solid #e8b22f;
        white-space: nowrap;
    }
    .calf-records-table tbody tr:nth-child(even) { background: #f8fafc; }
    .calf-records-table tbody tr:hover { background: #f0f8fb; }
    .calf-records-table tbody td { border-bottom: 1px solid #e8edf3; vertical-align: middle; }
    [x-cloak]{display:none!important}
    .calf-records-page { background:radial-gradient(circle at 92% 0%,rgba(11,31,58,.06),transparent 25%),#f5f8fc; }
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
    .filter-input.calf-record-search { padding-inline: 2.25rem; }
    .calf-record-search::-webkit-search-cancel-button { appearance: none; }
</style>
@endpush

@push('scripts')
<script>
function calfDropPage() {
    return {
        storageKey: 'cbed_calfdrop_records', records: [], inventoryCalves: [], inventoryLoading: false, search: '', filterProvince: '', filterBreed: '', filterSex: '', filterYear: '',
        showAdd: false, savingCalf: false, deletingCalf: false, addForm: {}, showView: false, showEdit: false, savingEdit: false, editForm: {}, selected: null, notice: '', rowKey: 0, noticeTimer: null, page: 1, perPage: 15,
        showMatingPicker: false, dueMatings: [], loadingDueMatings: false, dueMatingsError: '',
        calfCooperatives: [], calfFarmers: [], calfInventory: [],
        cooperativePickerOpen: false, cooperativeQuery: '',
        farmerPickerOpen: false, farmerQuery: '',
        deleteTarget: null, deleteCalfError: '', finalDeleteConfirmation: false,
        provinceOptions: ['Aurora','Bataan','Bulacan','Nueva Ecija','Pampanga','Tarlac','Zambales'],
        breedOptions: ['RV', 'SP', 'ItMB', 'BrMB', 'BMB', 'CB50', 'CB75', 'CB87', 'CB93'],
        fields: ['calf_id','mother_id','farmer','province','municipality','barangay','cooperative','date_of_birth','sex','breed','sire_id','monitored_date','reported_year','birth_outcome','animal_status','breed_series','remarks'],
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
            this.loadInventoryCalves(true);
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
        reportedYearFromCalfId(calfId, fallback='') { const match=String(calfId??'').trim().match(/^\d+[A-Za-z]+(\d{2})\d{3}$/); return match?String(2000+Number(match[1])):String(fallback??''); },
        calfIdSeries(calfId) { const match=String(calfId??'').trim().match(/^\d+[A-Za-z]+(\d{2})(\d{3})$/); return match?{year:2000+Number(match[1]),series:Number(match[2])}:null; },
        get allRecords() { const manual = new Set(this.records.map(r => String(r.calf_id || '').trim().toLowerCase()).filter(Boolean)); return [...this.records, ...this.inventoryCalves.filter(c => !manual.has(String(c.calf_id || '').trim().toLowerCase()))].map(record=>({...record,reported_year:this.reportedYearFromCalfId(record.calf_id,record.reported_year)})); },
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
                const seenCalfIds=new Set();
                const calfRows=(databaseCalves ?? []).map(c=>({...c,id:'db-calf-'+c.id,database_id:c.id,source:'Calf Drop'})).concat(animals.filter(a=>this.hasDam(a)).map(a=>this.inventoryToCalf(a,coops,farmers)))
                this.inventoryCalves=calfRows
                    .filter(calf=>{
                        const id=String(calf.calf_id||'').trim().toLowerCase();
                        if(!id||seenCalfIds.has(id))return false;
                        seenCalfIds.add(id);
                        return true;
                    })
                    .sort((a,b)=>String(b.date_of_birth||'').localeCompare(String(a.date_of_birth||'')));
                this.page = 1;
                if (databaseCalves === null) {
                    this.$dispatch('cbed-data-status', {
                        title: 'Calf drop history unavailable',
                        description: 'Inventory calves are shown. Use Refresh to retry the calf drop history.',
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
                    description: 'Use Refresh to retry loading the records from the database.',
                    type: 'error'
                });
            } finally { this.inventoryLoading = false; }
        },
        get matchingCalfRecords() {
            const normalize=value=>String(value ?? '').normalize('NFD').replace(/\p{M}/gu,'').toLowerCase();
            const terms=normalize(this.search).trim().split(/\s+/).filter(Boolean);
            return this.allRecords.filter(record=>{
                if((this.filterProvince && record.province!==this.filterProvince)
                    || (this.filterBreed && record.breed!==this.filterBreed)
                    || (this.filterSex && record.sex!==this.filterSex))return false;
                if(!terms.length)return true;
                const values=this.fields.map(field=>normalize(record[field]));
                return terms.every(term=>values.some(value=>value.includes(term)));
            });
        },
        get filteredRecords() { return this.matchingCalfRecords.filter(r=>!this.filterYear || String(r.reported_year)===this.filterYear).sort((a,b)=>{const as=this.calfIdSeries(a.calf_id),bs=this.calfIdSeries(b.calf_id);if(as&&bs)return bs.year-as.year||as.series-bs.series;if(as)return -1;if(bs)return 1;return String(a.calf_id??'').localeCompare(String(b.calf_id??''),undefined,{numeric:true,sensitivity:'base'});}); },
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
            const counts=new Map();
            this.matchingCalfRecords.forEach(record=>{
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
        reportedYearOptions() { const years=new Set(this.options('reported_year')); years.add('2026'); return [...years].sort((a,b)=>Number(b)-Number(a)); },
        distribution(field) { const counts={}; this.filteredRecords.forEach(r=>{const name=String(r[field]||'Not specified');counts[name]=(counts[name]||0)+1;}); const max=Math.max(1,...Object.values(counts)); return Object.entries(counts).map(([name,count])=>({name,count,percent:count/max*100})).sort((a,b)=>b.count-a.count||a.name.localeCompare(b.name)); },
        label(field) { const labels={calf_id:'Calf ID',mother_id:'Mother ID',farmer:'Farmer Name',province:'Province',cooperative:'Cooperative',date_of_birth:'Date of Birth / Event Date',sex:'Sex',breed:'Breed',sire_id:'Sire / Bull ID',monitored_date:'Monitored Date',reported_year:'Reported Year',birth_outcome:'Birth Outcome',animal_status:'Animal Status',breed_series:'Breed Series',remarks:'Remarks'}; return labels[field]||field; },
        generatedId(r) { const mother=String(r.mother_id||'').trim().replace(/\s+/g,'-'); const year=String(r.reported_year|| (r.date_of_birth ? r.date_of_birth.slice(0,4) : new Date().getFullYear())).slice(-2); const series=String(r.breed_series||'6').trim()||'6'; if (!mother || r.birth_outcome === 'Live Birth') return ''; const code=r.birth_outcome==='Stillbirth'?'SB':'AB'; return `${series}-${code}-${year}-${mother}`; },
        refreshGeneratedId(r) { const id=this.generatedId(r); if(id) r.calf_id=id; else if(r.birth_outcome==='Live Birth' && /-(SB|AB)-/.test(String(r.calf_id||''))) r.calf_id=''; },
        emptyRow() { return {_key:++this.rowKey,calf_id:'',mother_id:'',farmer:'',province:'',municipality:'',barangay:'',cooperative:'',date_of_birth:'',sex:'',breed:'',sire_id:'',monitored_date:new Date().toISOString().slice(0,10),reported_year:String(new Date().getFullYear()),birth_outcome:'Live Birth',animal_status:'Live',breed_series:'6',remarks:''}; },
        openAdd() {
            this.addForm={...this.emptyRow(),cooperative_id:'',farmer_id:''};
            this.showMatingPicker=true;
            this.cooperativePickerOpen=false;
            this.farmerPickerOpen=false;
            this.showAdd=true;
            this.loadDueMatings();
        },
        get manualFarmers() {
            if(!this.addForm.cooperative_id)return [];
            return this.calfFarmers.filter(farmer=>String(farmer.cooperative_id)===this.addForm.cooperative_id);
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
            if(!this.addForm.farmer_id || this.loadingDueMatings || this.dueMatingsError)return [];
            const mothersWithDueMatings=new Set(this.dueMatings.map(mating=>String(mating.carabao_id)));
            return this.calfInventory.filter(animal=>String(animal.farmer_id)===this.addForm.farmer_id
                && animal.animal_id && animal.live_breedable_female === true
                && !mothersWithDueMatings.has(String(animal.id)));
        },
        setEntryMode(useMating) {
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
            this.addForm.cooperative=cooperative?.cooperative_name || '';
            this.farmerPickerOpen=false;
            this.changeManualFarmer('');
        },
        changeManualFarmer(id) {
            const farmer=this.manualFarmers.find(item=>String(item.id)===id);
            this.addForm.farmer_id=farmer ? id : '';
            this.addForm.farmer=farmer ? this.farmerLabel(farmer) : '';
            this.addForm.mother_id='';
            this.addForm.province=this.normalizeProvince(farmer?.province || farmer?.cooperative_record?.province);
            this.addForm.municipality=farmer?.municipality || farmer?.cooperative_record?.municipality || '';
            this.addForm.barangay=farmer?.barangay || farmer?.cooperative_record?.barangay || '';
            if(this.addForm.birth_outcome!=='Live Birth')this.addForm.calf_id='';
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
                this.dueMatings=records.filter(record=>record.calf_drop_due)
                    .sort((a,b)=>b.pregnancy_days-a.pregnancy_days);
            } catch {
                this.dueMatingsError='Could not load mating records.';
            } finally {
                this.loadingDueMatings=false;
            }
        },
        addFromMating(mating) {
            Object.assign(this.addForm,{
                mother_id:String(mating.animal_id || mating.carabao_id),
                farmer:[mating.farmer_first_name,mating.farmer_middle_name,mating.farmer_last_name].filter(Boolean).join(' '),
                cooperative:mating.cooperative_name || '',
                province:this.normalizeProvince(mating.farmer_province),
                municipality:mating.farmer_municipality || '',
                barangay:mating.farmer_barangay || '',
                sire_id:mating.bull_id || ''
            });
            this.refreshGeneratedId(this.addForm);
        },
        persist() { try { localStorage.setItem(this.storageKey,JSON.stringify(this.records)); return true; } catch(e) { this.msg('Unable to save browser data. Check browser storage settings.'); return false; } },
        async saveCalf() {
            if(this.savingCalf || !this.addForm.date_of_birth)return;
            if(!this.showMatingPicker && !this.manualFarmers.some(farmer=>String(farmer.id)===this.addForm.farmer_id)){
                this.msg('Select a farmer from the cooperative.');
                return;
            }
            if(!this.showMatingPicker && !this.manualMothers.some(mother=>mother.animal_id===this.addForm.mother_id)){
                this.msg('Select a live, breedable female from the available mothers, or use her mating record.');
                return;
            }
            this.savingCalf=true;
            try {
                if(await this.saveRecords([{...this.addForm}]))this.showAdd=false;
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
                const generated=this.generatedId(r);
                if(generated)r.calf_id=generated;
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
                            cooperative_id:row.cooperative_id||null,
                            farmer_id:row.farmer_id||null,
                            date_of_birth:row.date_of_birth||row.monitored_date,
                            sex:row.sex,
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
                    console.error('Could not add live calf to inventory:',error);
                }
            }

            if(localRecords.length){
                const previous=this.records;
                this.records=[...localRecords,...this.records];
                if(!this.persist()){
                    this.records=previous;
                    if(savedLiveRows.size)await this.loadInventoryCalves();
                    this.msg(savedLiveRows.size ? 'Live births were saved to inventory. Other outcomes could not be saved in this browser.' : 'Could not save the calf record in this browser.');
                    return;
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
