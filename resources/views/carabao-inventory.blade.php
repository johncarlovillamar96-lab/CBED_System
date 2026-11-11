{{-- resources/views/carabao-inventory.blade.php --}}
@extends('layouts.admin')

@section('title', 'CBED Carabao Inventory')
@section('page-heading', 'Carabao Inventory')

@section('content')
<div x-data="carabaoInventoryPage()" x-init="init()" x-cloak class="inventory-page mating-dashboard min-w-0 bg-slate-50 text-slate-800">

    {{-- PAGE HEADER --}}
    <div class="pcc-mating-hero mb-7 flex flex-col gap-5 rounded-2xl p-5 text-white shadow-xl sm:p-6 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-center gap-4">
            <div class="pcc-brand-mark hidden sm:flex" aria-hidden="true">
                <i class="fa-solid fa-cow"></i><span><i class="fa-solid fa-dna"></i></span>
            </div>
            <div>
                <div class="mb-2 flex flex-wrap items-center gap-2 text-sm text-blue-100">
                    <i class="fa-solid fa-house"></i><span>/</span>
                    <span>CBED Data System</span><span>/</span>
                    <span>Animal Management</span><span>/</span>
                    <b class="text-white">Inventory</b>
                </div>
                <h1 class="text-2xl font-bold text-white sm:text-3xl">CBED Carabao Inventory</h1>
                <p class="mt-1 text-sm text-blue-100">Encode animal records, review ownership, and track births and breeding status.</p>
                <span class="pcc-program-tag"><i class="fa-solid fa-seedling"></i> Animal Management · Inventory</span>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="button" @click="exportXLSX()" class="action-secondary">
                <i class="fa-solid fa-file-export"></i> Export Excel
            </button>
            <button type="button" @click="downloadTemplate()" class="action-secondary">
                <i class="fa-solid fa-download"></i> Download Template
            </button>
            <a href="{{ route('imports.cooperative.create') }}" class="action-secondary">
                <i class="fa-solid fa-file-import"></i> Import Workbook by Cooperative
            </a>
            <button type="button" @click="openBulk()" class="action-secondary">
                <i class="fa-solid fa-layer-group"></i> Bulk Add
            </button>
            <button type="button" @click="openAdd()" class="action-primary">
                <i class="fa-solid fa-plus"></i> Add Carabao
            </button>
        </div>
    </div>

    {{-- CONNECTION STATUS --}}
    <div class="mb-6 flex flex-col justify-between gap-3 rounded-xl border p-4 text-sm sm:flex-row sm:items-center"
         :class="apiConnected ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900'">
        <div class="flex gap-3">
            <i class="fa-solid mt-0.5" :class="apiConnected ? 'fa-circle-check' : 'fa-circle-info'"></i>
            <div>
                <b x-text="apiConnected ? 'Connected to inventory API' : 'Inventory API not connected'"></b>
                <p class="mt-1" x-text="apiConnected ? 'Records are loaded from the Laravel database.' : 'No sample or browser-only records are displayed. Check the API connection.'"></p>
            </div>
        </div>
        <button type="button" @click="loadRecords()" class="action-secondary">
            <i class="fa-solid fa-rotate"></i> Refresh
        </button>
    </div>

    {{-- SUMMARY CARDS --}}
    <div class="mb-7 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="pcc-stat-card rounded-xl bg-white p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-slate-500">Total Animals</p>
                    <p class="mt-2 text-3xl font-bold" x-text="records.length"></p>
                    <p class="mt-1 text-xs text-slate-500">Animals in the inventory</p>
                </div>
                <div class="pcc-stat-icon flex h-12 w-12 items-center justify-center rounded-xl"><i class="fa-solid fa-cow text-xl"></i></div>
            </div>
        </div>
        <div class="pcc-stat-card rounded-xl bg-white p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-slate-500">Breedable</p>
                    <p class="mt-2 text-3xl font-bold" x-text="summaryCounts.breedable"></p>
                    <p class="mt-1 text-xs text-slate-500">Heifer, Cow Dry, or Cow Lactating at 25–45 days</p>
                </div>
                <div class="pcc-stat-icon flex h-12 w-12 items-center justify-center rounded-xl"><i class="fa-solid fa-dna text-xl"></i></div>
            </div>
        </div>
        <div class="pcc-stat-card rounded-xl bg-white p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-slate-500">Pregnant</p>
                    <p class="mt-2 text-3xl font-bold" x-text="summaryCounts.pregnant"></p>
                    <p class="mt-1 text-xs text-slate-500">Heifer, Cow Lactating, and Cow Dry pregnant</p>
                </div>
                <div class="pcc-stat-icon flex h-12 w-12 items-center justify-center rounded-xl"><i class="fa-solid fa-heart text-xl"></i></div>
            </div>
        </div>
        <div class="pcc-stat-card rounded-xl bg-white p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-slate-500">Lactating</p>
                    <p class="mt-2 text-3xl font-bold" x-text="summaryCounts.lactating"></p>
                    <p class="mt-1 text-xs text-slate-500">Cow Lactating, including pregnant lactating cows</p>
                </div>
                <div class="pcc-stat-icon flex h-12 w-12 items-center justify-center rounded-xl"><i class="fa-solid fa-droplet text-xl"></i></div>
            </div>
        </div>
    </div>

    <section class="inventory-panel mb-7 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-4">
            <h2 class="text-lg font-bold text-[#12345a]">Breedability, Pregnancy & Lactation</h2>
            <p class="mt-1 text-sm text-slate-500">Counts use currently live animals and their FHPR classification.</p>
        </div>
        <div class="mt-5 border-t border-slate-100 pt-4">
            <h3 class="mb-3 text-sm font-bold text-slate-800">Breedable, Pregnant & Lactating by Province</h3>
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase"><tr><th class="px-4 py-3">Province</th><th class="px-4 py-3">Live Animals</th><th class="px-4 py-3">Breedable</th><th class="px-4 py-3">Pregnant</th><th class="px-4 py-3">Lactating</th><th class="px-4 py-3">Action</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="item in provincialSummary" :key="item.province">
                            <tr class="hover:bg-slate-50"><td class="px-4 py-3 font-medium" x-text="item.province"></td><td class="px-4 py-3" x-text="item.live"></td><td class="px-4 py-3" x-text="item.breedable"></td><td class="px-4 py-3" x-text="item.pregnant"></td><td class="px-4 py-3" x-text="item.lactating"></td><td class="px-4 py-3"><button type="button" @click="provinceFilter=item.province==='Unknown Province'?'all':item.province;page=1;document.querySelector('#inventory-table')?.scrollIntoView({behavior:'smooth'})" class="font-semibold text-blue-700 hover:underline">View animals</button></td></tr>
                        </template>
                        <tr x-show="provincialSummary.length===0"><td colspan="6" class="px-4 py-6 text-center text-slate-500">No live animals to summarize.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-5 border-t border-slate-100 pt-4">
            <h3 class="text-sm font-bold text-slate-800">Classification summary by sex, age, mating, and milk production</h3>
            <p class="mt-1 text-xs text-slate-500">Blank live-animal classifications are inferred from age, sex, calf records, pregnancy checks, FHPR days in milk, and milk records stored in this browser, then saved. Existing classifications are included in these counts.</p>
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7">
                <template x-for="item in ageSexSummary" :key="item.key">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3"><p class="text-xs text-slate-500" x-text="item.label"></p><p class="mt-1 text-2xl font-bold text-slate-800" x-text="item.count"></p></div>
                </template>
            </div>
        </div>
    </section>

    <section class="inventory-panel mb-7 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div><h2 class="text-lg font-bold text-slate-900">Calves Born by Year</h2><p class="mt-1 text-sm text-slate-500">Counts include animals with a Dam ID and birth date. Select a year to filter the inventory, or compare annual birth counts below.</p></div>
            <div class="flex items-center gap-3"><label for="birth-year-filter" class="text-sm font-medium text-slate-600">Filter year</label><select id="birth-year-filter" x-model="birthYearFilter" @change="page=1" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="all">All years</option><template x-for="year in birthYears" :key="year"><option :value="String(year)" x-text="year"></option></template></select></div>
        </div>
        <p class="mb-3 text-sm text-slate-700">Unique animals born <strong x-text="birthYearFilter==='all'?'in all years':'in '+birthYearFilter"></strong>: <strong class="text-lg" x-text="selectedBirthCount"></strong></p>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8">
            <template x-for="item in birthYearSummary" :key="item.year">
                <button type="button" @click="birthYearFilter=String(item.year);page=1" class="rounded-lg border p-3 text-left hover:border-blue-400 hover:bg-blue-50" :class="birthYearFilter===String(item.year)?'border-blue-500 bg-blue-50':''"><span class="block text-xs text-slate-500" x-text="item.year"></span><span class="mt-1 block text-2xl font-bold text-slate-800" x-text="item.count"></span></button>
            </template>
            <p x-show="birthYearSummary.length===0" class="col-span-full py-4 text-sm text-slate-500">No birth dates are available.</p>
        </div>
    </section>

    {{-- Inventory table --}}
    {{-- RECORDS TABLE --}}
    <section id="inventory-table" class="pcc-records-panel inventory-panel overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-[#12345a]">Carabao Records</h2>
                <p class="mt-1 text-sm text-slate-500">Search and manage animal inventory records.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold">
                    <span x-text="filteredRecords.length"></span> records
                </span>
                <button type="button" @click="exportXLSX(filteredRecords,'cbed_carabao_inventory_filtered.xlsx')" class="action-secondary">
                    <i class="fa-solid fa-file-excel"></i> Export Filtered
                </button>
                <button type="button" @click="clearFilters()" class="action-secondary">Reset Filters</button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 border-b bg-slate-50 p-5 sm:grid-cols-2 xl:grid-cols-6">
            <div class="xl:col-span-2">
                <label class="filter-label">Search</label>
                <input x-model="search" @input="page=1" class="filter-input" placeholder="Cooperative, farmer, Bolus ID...">
            </div>
            <div>
                <label class="filter-label">Nature of Exit</label>
                <select x-model="exitFilter" @change="page=1" class="filter-input">
                    <option value="all">All Nature of Exit</option>
                    <option value="none">Currently in CBED</option>
                    <option value="Transferred">Transferred</option>
                    <option value="Dead">Dead</option>
                    <option value="Culled">Culled</option>
                </select>
            </div>
            <div>
                <label class="filter-label">Sex</label>
                <select x-model="sexFilter" @change="page=1" class="filter-input">
                    <option value="all">All Sex</option>
                    <option value="Female">Female</option>
                    <option value="Male">Male</option>
                </select>
            </div>
            <div>
                <label class="filter-label">Province</label>
                <select x-model="provinceFilter" @change="page=1" class="filter-input">
                    <option value="all">All Provinces</option>
                    <template x-for="province in provinces" :key="province">
                        <option :value="province" x-text="province"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="filter-label">Birth Year</label>
                <select x-model="birthYearFilter" @change="page=1" class="filter-input">
                    <option value="all">All Birth Years</option>
                    <template x-for="year in birthYears" :key="year"><option :value="String(year)" x-text="year"></option></template>
                </select>
            </div>
        </div>

        <div class="inventory-table-scroll overflow-x-auto">
            <table class="inventory-data-table min-w-[2800px] w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-100 text-left text-xs uppercase tracking-wide text-slate-600">
                    <tr>
                        <th class="inventory-sticky-1 whitespace-nowrap px-4 py-3">Cooperative</th>
                        <th class="inventory-sticky-2 whitespace-nowrap px-4 py-3">Farmer</th>
                        <th class="inventory-sticky-3 whitespace-nowrap px-4 py-3">Bolus ID</th>
                        <th class="whitespace-nowrap px-4 py-3">Registry Number</th>
                        <th class="whitespace-nowrap px-4 py-3">Tag No.</th>
                        <th class="whitespace-nowrap px-4 py-3">Sex</th>
                        <th class="whitespace-nowrap px-4 py-3">Birthday</th>
                        <th class="whitespace-nowrap px-4 py-3">Age</th>
                        <th class="whitespace-nowrap px-4 py-3">Breed</th>
                        <th class="whitespace-nowrap px-4 py-3">Classification</th>
                        <th class="whitespace-nowrap px-4 py-3">Sire ID</th>
                        <th class="whitespace-nowrap px-4 py-3">Sire Name</th>
                        <th class="whitespace-nowrap px-4 py-3">Dam ID</th>
                        <th class="whitespace-nowrap px-4 py-3">Dam Name</th>
                        <th class="whitespace-nowrap px-4 py-3">Birthday Place</th>
                        <th class="whitespace-nowrap px-4 py-3">Breeder</th>
                        <th class="whitespace-nowrap px-4 py-3">Association / Coop / Institution</th>
                        <th class="whitespace-nowrap px-4 py-3">Barangay</th>
                        <th class="whitespace-nowrap px-4 py-3">Municipality</th>
                        <th class="whitespace-nowrap px-4 py-3">Province</th>
                        <th class="whitespace-nowrap px-4 py-3">District</th>
                        <th class="whitespace-nowrap px-4 py-3">Region</th>
                        <th class="whitespace-nowrap px-4 py-3">Date of Acquisition</th>
                        <th class="whitespace-nowrap px-4 py-3">Acquisition Cost</th>
                        <th class="whitespace-nowrap px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-for="animal in paginatedRecords" :key="animal.id">
                        <tr class="hover:bg-slate-50">
                            <td class="inventory-sticky-1 px-4 py-3 font-medium text-slate-700" x-text="cooperativeNameForAnimal(animal) || '—'"></td>
                            <td class="inventory-sticky-2 px-4 py-3 font-medium text-slate-700" x-text="farmerNameForAnimal(animal) || '—'"></td>
                            <td class="inventory-sticky-3 px-4 py-3 font-mono text-xs font-semibold text-blue-800" x-text="animal.bolus_id || '—'"></td>
                            <td class="px-4 py-3" x-text="animal.registry_number || '—'"></td>
                            <td class="px-4 py-3" x-text="animal.tag_no || '—'"></td>
                            <td class="px-4 py-3" x-text="animal.sex || '—'"></td>
                            <td class="px-4 py-3" x-text="formatBirthday(animal)"></td>
                            <td class="whitespace-nowrap px-4 py-3" x-text="ageText(animal)"></td>
                            <td class="px-4 py-3" x-text="animal.breed || '—'"></td>
                            <td class="px-2 py-2"><input type="text" :value="animal.classification || ''" @change="saveClassification(animal, $event.target.value)" class="w-40 rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none" placeholder="Set classification" :aria-label="'Classification for ' + (animal.registry_number || animal.animal_id || 'animal')"></td>
                            <td class="px-4 py-3" x-text="animal.sire_id || '—'"></td>
                            <td class="px-4 py-3" x-text="animal.sire_name || '—'"></td>
                            <td class="px-4 py-3" x-text="animal.dam_id || '—'"></td>
                            <td class="px-4 py-3" x-text="animal.dam_name || '—'"></td>
                            <td class="px-4 py-3" x-text="animal.birth_place || animal.birthday_place || '—'"></td>
                            <td class="px-4 py-3" x-text="animal.breeder || '—'"></td>
                            <td class="px-4 py-3" x-text="animal.association_coop_institution || '—'"></td>
                            <td class="px-4 py-3" x-text="animal.barangay || animal.brgy || '—'"></td>
                            <td class="px-4 py-3" x-text="animal.municipality || '—'"></td>
                            <td class="px-4 py-3" x-text="animal.province || '—'"></td>
                            <td class="px-4 py-3" x-text="animal.district || '—'"></td>
                            <td class="px-4 py-3" x-text="formatRegion(animal.region)"></td>
                            <td class="px-4 py-3" x-text="formatDateValue(animal.acquisition_date || animal.date_of_acquisition)"></td>
                            <td class="whitespace-nowrap px-4 py-3 text-right" x-text="animal.acquisition_cost !== null && animal.acquisition_cost !== '' && animal.acquisition_cost !== undefined ? Number(animal.acquisition_cost).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) : '—'"></td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <button type="button" @click="viewAnimal(animal)" title="View" class="rounded p-2 text-slate-600 hover:bg-slate-100"><i class="fa-solid fa-eye"></i></button>
                                    <button type="button" @click="editAnimal(animal)" title="Edit" class="rounded p-2 text-blue-700 hover:bg-blue-50"><i class="fa-solid fa-pen-to-square"></i></button>
                                    <button type="button" @click="deleteAnimal(animal)" title="Delete" class="rounded p-2 text-rose-600 hover:bg-rose-50"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="filteredRecords.length===0">
                        <td colspan="25" class="px-4 py-14 text-center text-slate-500">No carabao records found. Import your Excel inventory or add records to begin.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- PAGINATION --}}
        <div class="flex flex-col justify-between gap-3 border-t px-5 py-4 sm:flex-row sm:items-center">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <p class="text-sm text-slate-500">
                    Showing <b x-text="filteredRecords.length?((page-1)*perPage+1):0"></b>–<b x-text="Math.min(page*perPage,filteredRecords.length)"></b>
                    of <b x-text="filteredRecords.length"></b>
                    (<b x-text="records.length"></b> total)
                </p>
                <label class="flex items-center gap-2 text-sm text-slate-500">
                    Rows per page
                    <select x-model.number="perPage" @change="page=1" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm">
                        <option :value="10">10</option><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option>
                    </select>
                </label>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="page=Math.max(1,page-1)" :disabled="page<=1" class="page-btn">Previous</button>
                <span class="text-sm">Page <b x-text="page"></b> of <b x-text="totalPages"></b></span>
                <button type="button" @click="page=Math.min(totalPages,page+1)" :disabled="page>=totalPages" class="page-btn">Next</button>
            </div>
        </div>
    </section>

    <footer class="py-5 text-center text-xs text-slate-400">CBED Data System · Carabao Inventory</footer>

    {{-- Add / Edit form --}}
    <div x-cloak x-show="showForm" x-transition.opacity class="inventory-modal-backdrop fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 backdrop-blur-sm sm:p-6" @keydown.escape.window="showForm=false">
        <div @click.outside="showForm=false" class="inventory-modal-panel max-h-[94vh] w-full max-w-6xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
            <div class="inventory-modal-header flex items-center justify-between border-b p-5">
                <div>
                    <h3 class="text-lg font-bold" x-text="formMode==='edit'?'Edit Carabao Record':'Add Carabao Record'"></h3>
                    <p class="text-sm text-slate-500">Complete the available animal information.</p>
                </div>
                <button type="button" @click="showForm=false"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form @submit.prevent="saveAnimal()" class="p-5 sm:p-6">
                <h4 class="mb-3 font-bold text-slate-800">Animal Identification and Location</h4>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="text-sm font-semibold">Farmer / Recipient
                        <select x-model="form.farmer_recipient" class="field-input">
                            <option value="">Select farmer / recipient</option>
                            <template x-for="farmer in farmers" :key="farmer.id ?? farmer.farmer_id ?? farmer.farmer_code">
                                <option :value="String(farmer.id ?? farmer.farmer_id ?? farmer.farmer_code ?? farmerName(farmer))"
                                    x-text="farmerName(farmer)"></option>
                            </template>
                        </select>
                    </label>
                    <label class="text-sm font-semibold">Bolus ID
                        <input x-model.trim="form.bolus_id" class="field-input">
                    </label>
                    <label class="text-sm font-semibold">Registry Number
                        <input x-model.trim="form.registry_number" class="field-input">
                    </label>
                    <label class="text-sm font-semibold">Tag No.
                        <input x-model.trim="form.tag_no" class="field-input">
                    </label>
                    <label class="text-sm font-semibold">Sex
                        <select x-model="form.sex" class="field-input"><option value="">Select sex</option><option>Female</option><option>Male</option></select>
                    </label>
                    <label class="text-sm font-semibold">Birthday
                        <input type="date" x-model="form.birthday" class="field-input">
                    </label>
                    <label class="text-sm font-semibold">Breed
                        <input x-model.trim="form.breed" class="field-input">
                    </label>
                    <label class="text-sm font-semibold">Sire ID
                        <input x-model.trim="form.sire_id" class="field-input">
                    </label>
                    <label class="text-sm font-semibold">Sire Name
                        <input x-model.trim="form.sire_name" class="field-input">
                    </label>
                    <label class="text-sm font-semibold">Dam ID
                        <input x-model.trim="form.dam_id" class="field-input">
                    </label>
                    <label class="text-sm font-semibold">Dam Name
                        <input x-model.trim="form.dam_name" class="field-input">
                    </label>
                    <label class="text-sm font-semibold">Birthday Place
                        <input x-model.trim="form.birthday_place" class="field-input">
                    </label>
                    <label class="text-sm font-semibold">Breeder
                        <input x-model.trim="form.breeder" class="field-input">
                    </label>
                    <label class="text-sm font-semibold">Name of Association / Coop / Institution
                        <input x-model.trim="form.association_coop_institution" class="field-input">
                    </label>
                    <label class="text-sm font-semibold">Barangay
                        <input x-model.trim="form.brgy" class="field-input">
                    </label>
                    <label class="text-sm font-semibold">Municipality
                        <input x-model.trim="form.municipality" class="field-input">
                    </label>
                    <label class="text-sm font-semibold">Province
                        <input x-model.trim="form.province" class="field-input">
                    </label>
                    <label class="text-sm font-semibold">District
                        <input x-model.trim="form.district" class="field-input">
                    </label>
                    <label class="text-sm font-semibold">Region
                        <input x-model.trim="form.region" class="field-input">
                    </label>
                </div>

                <h4 class="mb-3 mt-7 border-t pt-5 font-bold text-slate-800">Acquisition Information</h4>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="text-sm font-semibold">Date of Acquisition
                        <input type="date" x-model="form.date_of_acquisition" class="field-input">
                    </label>
                    <label class="text-sm font-semibold">Acquisition Cost
                        <input type="number" min="0" step="0.01" x-model="form.acquisition_cost" @input="if (['Transferred','Sold','Disposed'].includes(form.nature_of_exit)) form.value_of_disposed_animals=form.acquisition_cost" class="field-input" placeholder="0.00">
                    </label>
                    <label class="text-sm font-semibold">
                        Type of Entrustment
                        <select x-model="form.type_of_entrustment" class="field-input">
                            <option value="">Select Type of Entrustment</option>
                            <option value="Pamamahagi">Pamamahagi</option>
                            <option value="CBED">CBED</option>
                            <option value="CBIN">CBIN</option>
                            <option value="CHBU">CHBU</option>
                        </select>
                    </label>
                    <label class="text-sm font-semibold">
                        Ownership Status
                        <select x-model="form.ownership_status" class="field-input">
                            <option value="">Select Ownership Status</option>
                            <option value="PCC Owned">PCC Owned</option>
                            <option value="50:50">50:50</option>
                            <option value="Coop Owned">Coop Owned</option>
                            <option value="Farmer's Owned">Farmer's Owned</option>
                        </select>
                    </label>
                </div>

                <h4 class="mb-3 mt-7 border-t pt-5 font-bold text-slate-800">Nature of Exit / Disposal</h4>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="text-sm font-semibold">Nature of Exit
                        <select x-model="form.nature_of_exit" @change="onNatureOfExitChange()" class="field-input">
                            <option value="">Currently in CBED / No Exit</option>
                            <option>Transferred</option>
                            <option>Sold</option>
                            <option>Disposed</option>
                            <option>Dead</option>
                            <option>Culled</option>
                        </select>
                    </label>
                    <label class="text-sm font-semibold">Reason of Exit
                        <input x-model.trim="form.reason_of_exit" class="field-input">
                    </label>
                    <label class="text-sm font-semibold">Cooperative of Recipient
                        <select x-model="form.transfer_cooperative" @change="onTransferCooperativeChange()" class="field-input" :disabled="!['Transferred','Sold','Disposed'].includes(form.nature_of_exit)">
                            <option value="">Select cooperative</option>
                            <template x-for="coop in availableCooperatives" :key="coop.value">
                                <option :value="coop.value"
                                    x-text="coop.code ? `${coop.code} — ${coop.value}` : coop.value"></option>
                            </template>
                        </select>
                        <span class="mt-1 block text-xs font-normal text-slate-500">Select the cooperative first to filter its registered farmers.</span>
                    </label>
                    <label class="text-sm font-semibold">
                        Animal Sold / Transferred / Disposed To

                        <select
                            x-model="form.disposed_to"
                            @change="onTransferFarmerChange()"
                            class="field-input"
                            :disabled="!['Transferred','Sold','Disposed'].includes(form.nature_of_exit) || !form.transfer_cooperative"
                        >
                            <option value="">Select farmer / recipient</option>

                            <template x-for="farmer in farmersForTransfer" :key="farmer.id">
                                <option
                                    :value="String(farmer.id)"
                                    x-text="farmer.full_name || [farmer.last_name, farmer.first_name, farmer.middle_name].filter(Boolean).join(', ')"
                                ></option>
                            </template>
                        </select>

                        <span
                            x-show="form.transfer_cooperative && farmersForTransfer.length === 0"
                            class="mt-1 block text-xs font-normal text-amber-700"
                        >
                            No farmers found for this cooperative. Check the farmer API and cooperative assignment.
                        </span>
                    </label>
                    <label class="text-sm font-semibold">Place of Disposal / Transfer
                        <input x-model.trim="form.place_of_disposal" class="field-input" readonly :placeholder="form.disposed_to ? 'Recipient address is filled automatically' : 'Select a recipient first'">
                    </label>
                    <label class="text-sm font-semibold">Date of Disposal / Death
                        <input type="date" x-model="form.date_of_disposal_death" class="field-input">
                    </label>
                    <label class="text-sm font-semibold">Value of Disposed Animals
                        <input type="number" min="0" step="0.01" x-model="form.value_of_disposed_animals" class="field-input" placeholder="0.00" :readonly="['Transferred','Sold','Disposed'].includes(form.nature_of_exit)">
                        <span x-show="['Transferred','Sold','Disposed'].includes(form.nature_of_exit)" class="mt-1 block text-xs font-normal text-slate-500">Automatically matches Acquisition Cost for transfer / sale / disposal.</span>
                    </label>
                    <label class="text-sm font-semibold sm:col-span-2 lg:col-span-3">Remarks
                        <textarea x-model.trim="form.remarks" rows="3" class="field-input"></textarea>
                    </label>
                </div>

                <div class="mt-6 flex justify-end gap-2 border-t pt-4">
                    <button type="button" @click="showForm=false" class="rounded-lg border px-4 py-2.5">Cancel</button>
                    <button type="submit" :disabled="saving" class="rounded-lg bg-blue-700 px-5 py-2.5 font-semibold text-white disabled:opacity-50" x-text="saving?'Saving...':(formMode==='edit'?'Update Record':'Save Record')"></button>
                </div>
            </form>
        </div>
    </div>

    {{-- Bulk Add --}}
    <div x-cloak x-show="showBulk" x-transition.opacity class="inventory-modal-backdrop fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 backdrop-blur-sm sm:p-6" @keydown.escape.window="showBulk=false">
        <div @click.outside="showBulk=false" class="inventory-modal-panel max-h-[94vh] w-full max-w-6xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
            <div class="inventory-modal-header flex items-center justify-between border-b p-5">
                <div><h3 class="text-lg font-bold">Bulk Add Carabaos</h3><p class="text-sm text-slate-500">Enter multiple records below, or use Import Workbook by Cooperative to match farmers and animals.</p></div>
                <button type="button" @click="showBulk=false"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <span class="text-sm font-semibold"><span x-text="bulkRows.length"></span> animal(s)</span>
                    <button type="button" @click="addBulkRow()" class="rounded-lg border px-3 py-2 text-sm font-semibold"><i class="fa-solid fa-plus mr-1"></i> Add Row</button>
                </div>
                <div class="overflow-x-auto rounded-lg border">
                    <table class="min-w-[1100px] w-full text-sm">
                        <thead class="text-left text-xs uppercase">
                            <tr><th class="p-2">Farmer / Recipient</th><th class="p-2">Bolus ID</th><th class="p-2">Registry Number</th><th class="p-2">Tag No.</th><th class="p-2">Sex</th><th class="p-2">Breed</th><th class="p-2">Sire ID</th><th class="p-2">Sire Name</th><th class="p-2">Dam ID</th><th class="p-2">Dam Name</th><th class="p-2">Association / Coop</th><th class="p-2">Municipality</th><th class="p-2">Province</th><th></th></tr>
                        </thead>
                        <tbody>
                            <template x-for="(row,index) in bulkRows" :key="row.key">
                                <tr class="border-t">
                                    <td class="p-2"><input x-model.trim="row.farmer_recipient" class="w-36 rounded border p-2"></td>
                                    <td class="p-2"><input x-model.trim="row.bolus_id" class="w-32 rounded border p-2"></td>
                                    <td class="p-2"><input x-model.trim="row.registry_number" class="w-32 rounded border p-2"></td>
                                    <td class="p-2"><input x-model.trim="row.tag_no" class="w-28 rounded border p-2"></td>
                                    <td class="p-2"><select x-model="row.sex" class="rounded border p-2"><option value="">—</option><option>Female</option><option>Male</option></select></td>
                                    <td class="p-2"><input x-model.trim="row.breed" class="w-28 rounded border p-2"></td>
                                    <td class="p-2"><input x-model.trim="row.sire_id" class="w-28 rounded border p-2"></td>
                                    <td class="p-2"><input x-model.trim="row.sire_name" class="w-32 rounded border p-2"></td>
                                    <td class="p-2"><input x-model.trim="row.dam_id" class="w-28 rounded border p-2"></td>
                                    <td class="p-2"><input x-model.trim="row.dam_name" class="w-32 rounded border p-2"></td>
                                    <td class="p-2"><input x-model.trim="row.association_coop_institution" class="w-40 rounded border p-2"></td>
                                    <td class="p-2"><input x-model.trim="row.municipality" class="w-32 rounded border p-2"></td>
                                    <td class="p-2"><input x-model.trim="row.province" class="w-32 rounded border p-2"></td>
                                    <td class="p-2"><button type="button" @click="bulkRows.splice(index,1)" class="p-2 text-rose-600"><i class="fa-solid fa-trash"></i></button></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div class="mt-5 flex justify-end gap-2 border-t pt-4">
                    <button type="button" @click="showBulk=false" class="rounded-lg border px-4 py-2.5">Cancel</button>
                    <button type="button" @click="saveBulk()" :disabled="saving" class="rounded-lg bg-blue-700 px-5 py-2.5 font-semibold text-white disabled:opacity-50" x-text="saving?'Saving...':'Save All Records'"></button>
                </div>
            </div>
        </div>
    </div>

    {{-- View details --}}
    <div x-cloak x-show="showView" x-transition.opacity class="inventory-modal-backdrop fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 backdrop-blur-sm" @keydown.escape.window="showView=false">
        <div @click.outside="showView=false" class="inventory-modal-panel max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
            <div class="inventory-modal-header flex items-center justify-between border-b p-5">
                <h3 class="text-lg font-bold">Animal Record Details</h3>
                <button type="button" @click="showView=false"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3">
                <template x-for="field in fields" :key="field.key">
                    <div class="inventory-detail-card rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs text-slate-500" x-text="field.label"></p>
                        <p class="mt-1 break-words text-sm font-semibold" x-text="detailValue(field, selectedAnimal)"></p>
                    </div>
                </template>
                <div class="flex items-center justify-end sm:col-span-2 lg:col-span-3">
                    <button type="button" @click="editAnimal(selectedAnimal)" class="rounded-lg bg-blue-700 px-4 py-2.5 font-semibold text-white">Edit Record</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Toast --}}
    <div x-cloak x-show="toast.show" x-transition class="fixed bottom-5 right-5 z-[70] max-w-sm rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-xl" :class="toast.type==='error'?'bg-rose-700':'bg-slate-900'" x-text="toast.message"></div>
</div>


<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
function carabaoInventoryPage() {
    const apiUrl = '/admin/carabao-inventory-api';

    const fields = [
        {key:'farmer_recipient',label:'Farmer / Recipient'},
        {key:'bolus_id',label:'Bolus ID'},
        {key:'registry_number',label:'Registry Number'},
        {key:'tag_no',label:'Tag No.'},
        {key:'sex',label:'Sex'},
        {key:'birthday',label:'Birthday'},
        {key:'breed',label:'Breed'},
        {key:'classification',label:'Classification'},
        {key:'sire_id',label:'Sire ID'},
        {key:'sire_name',label:'Sire Name'},
        {key:'dam_id',label:'Dam ID'},
        {key:'dam_name',label:'Dam Name'},
        {key:'birthday_place',label:'Birthday Place'},
        {key:'breeder',label:'Breeder'},
        {key:'association_coop_institution',label:'Name of Association / Coop / Institution'},
        {key:'brgy',label:'Barangay'},
        {key:'municipality',label:'Municipality'},
        {key:'province',label:'Province'},
        {key:'district',label:'District'},
        {key:'region',label:'Region'},
        {key:'date_of_acquisition',label:'Date of Acquisition'},
        {key:'acquisition_cost',label:'Acquisition Cost'},
        {key:'nature_of_exit',label:'Nature of Exit'},
        {key:'reason_of_exit',label:'Reason of Exit'},
        {key:'type_of_entrustment',label:'Type of Entrustment'},
        {key:'ownership_status',label:'Ownership Status'},
        {key:'disposed_to',label:'Animal Sold / Transferred / Disposed To'},
        {key:'place_of_disposal',label:'Place of Disposal / Transferred'},
        {key:'date_of_disposal_death',label:'Date of Disposal / Death'},
        {key:'value_of_disposed_animals',label:'Value of Disposed Animals'},
        {key:'remarks',label:'Remarks'}
    ];

    const emptyRecord = () => Object.fromEntries(fields.map(f=>[f.key,'']));

    // Province spelling fixes. Keys are lower-case with spaces/punctuation removed.
    // Add more misspellings here if you find them: 'misspelling': 'Correct Name'.
    const PROVINCE_ALIASES = {
        'nuevaecija': 'Nueva Ecija',
        'nuevaecjia': 'Nueva Ecija',
        'nuevaecijaprovince': 'Nueva Ecija'
    };
    const normalizeProvince = value => {
        const text = String(value ?? '').replace(/\s+/g,' ').trim();
        if (!text) return '';
        return PROVINCE_ALIASES[text.toLowerCase().replace(/[^a-z]/g,'')] || text;
    };

    return {
        fields,
        records: [],
        matingRecords: [],
        milkProductionRecords: [],
        classificationSignalsLoaded: false,
        cooperatives: [],
        farmers: [],
        apiConnected: false,
        saving: false,
        search: '',
        exitFilter: 'all',
        sexFilter: 'all',
        provinceFilter: 'all',
        birthYearFilter: 'all',
        page: 1,
        perPage: 10,
        showForm: false,
        showBulk: false,
        showView: false,
        formMode: 'add',
        form: emptyRecord(),
        selectedAnimal: null,
        bulkRows: [],
        nextKey: 1,
        toast: {show:false,message:'',type:'success'},

        async init() {
            // Inventory animals come from the API; browser storage is only used for milk signals.
            try { localStorage.removeItem('cbed_carabao_inventory_fields_v2'); } catch (e) {}
            await Promise.all([this.loadRecords(), this.loadTransferLookups()]);
            if (!this.apiConnected) return;
            try {
                await this.loadMatingRecords();
                this.loadMilkProductionRecords();
                this.classificationSignalsLoaded=true;
                await this.inferAndSaveMissingClassifications();
            } catch (e) {
                console.error('Could not infer blank animal classifications:', e);
                this.notify(`Could not infer blank classifications: ${e.message}`, 'error');
            }
        },

        get filteredRecords() {
            const q=this.search.trim().toLowerCase();
            return this.records.filter(a => {
                const matchesSearch=!q || this.fields.some(f=>String(f.key==='birthday'?(a.birth_date||a.birthday):a[f.key]??'').toLowerCase().includes(q))
                    || this.farmerNameForAnimal(a).toLowerCase().includes(q)
                    || this.cooperativeNameForAnimal(a).toLowerCase().includes(q);
                const matchesExit=this.exitFilter==='all'
                    || (this.exitFilter==='none' && !a.nature_of_exit)
                    || a.nature_of_exit===this.exitFilter;
                const sex=String(a.sex??'').trim().toLowerCase();
                const matchesSex=this.sexFilter==='all' || (this.sexFilter==='Female'?['f','female'].includes(sex):['m','male'].includes(sex));
                const matchesProvince=this.provinceFilter==='all' || normalizeProvince(a.province)===this.provinceFilter;
                const birthDate=a.birth_date||a.birthday||'';
                const matchesBirthYear=this.birthYearFilter==='all'||(String(a.dam_id??'').trim()!==''&&String(birthDate).slice(0,4)===String(this.birthYearFilter));
                return matchesSearch && matchesExit && matchesSex && matchesProvince && matchesBirthYear;
            });
        },

        get birthYearSummary() {
            const byYear=new Map();
            this.records.forEach(a=>{
                if(!String(a.dam_id??'').trim()) return;
                const date=a.birth_date||a.birthday||'';
                const year=String(date).slice(0,4);
                if(!/^\d{4}$/.test(year)) return;
                if(!byYear.has(year)) byYear.set(year,new Set());
                byYear.get(year).add(String(a.registry_number||a.animal_id||a.id));
            });
            return [...byYear.entries()].map(([year,ids])=>({year:Number(year),count:ids.size})).sort((a,b)=>b.year-a.year);
        },

        get birthYears() { return this.birthYearSummary.map(item=>item.year); },
        get selectedBirthCount() {
            if(this.birthYearFilter==='all') return this.birthYearSummary.reduce((sum,item)=>sum+item.count,0);
            return this.birthYearSummary.find(item=>String(item.year)===String(this.birthYearFilter))?.count||0;
        },

        get summaryCounts() {
            const live=this.records.filter(a=>{
                const status=String(a.status??'').trim().toLowerCase();
                return status ? status==='live' : !a.nature_of_exit;
            });
            const code=a=>String(a.classification??'').toLowerCase().replace(/[\s_-]+/g,'');
            const heifer=new Set(['heifer']);
            const pregnant=new Set(['heiferpregnant','cowlactatingpregnant','cowdrypregnant']);
            const lactating=new Set(['cowlactating','cowlactatingpregnant']);
            return {
                breedable:live.filter(a=>{
                    const c=code(a);
                    return heifer.has(c)||c==='cowdry'||(c==='cowlactating'&&Number(a.days_in_milk)>=25&&Number(a.days_in_milk)<=45);
                }).length,
                pregnant:live.filter(a=>pregnant.has(code(a))).length,
                lactating:live.filter(a=>lactating.has(code(a))).length
            };
        },

        get provincialSummary() {
            const groups=new Map();
            const active=this.records.filter(a=>{
                const status=String(a.status??'').trim().toLowerCase();
                return status?status==='live':!a.nature_of_exit;
            });
            active.forEach(animal=>{
                const province=normalizeProvince(animal.province)||'Unknown Province';
                if(!groups.has(province))groups.set(province,{province,live:0,breedable:0,pregnant:0,lactating:0});
                const item=groups.get(province);item.live++;
                const c=String(animal.classification??'').toLowerCase().replace(/[\s_-]+/g,'');
                if(c==='heifer'||c==='cowdry'||(c==='cowlactating'&&Number(animal.days_in_milk)>=25&&Number(animal.days_in_milk)<=45))item.breedable++;
                if(['heiferpregnant','cowlactatingpregnant','cowdrypregnant'].includes(c))item.pregnant++;
                if(['cowlactating','cowlactatingpregnant'].includes(c))item.lactating++;
            });
            return [...groups.values()].sort((a,b)=>a.province.localeCompare(b.province));
        },

        get ageSexSummary() {
            const counts={femaleCalf:0,yearlingHeifer:0,heifer:0,heiferPregnant:0,cowDry:0,cowDryPregnant:0,cowLactating:0,cowLactatingPregnant:0,maleCalf:0,yearlingBull:0,juniorBull:0,seniorBull:0};
            const keys={
                femalecalf:'femaleCalf',yearlingheifer:'yearlingHeifer',heifer:'heifer',heiferpregnant:'heiferPregnant',
                cowdry:'cowDry',cowdrypregnant:'cowDryPregnant',cowlactating:'cowLactating',
                cowlactatingpregnant:'cowLactatingPregnant',malecalf:'maleCalf',yearlingbull:'yearlingBull',
                juniorbull:'juniorBull',seniorbull:'seniorBull'
            };
            this.records.forEach(a=>{
                if(!this.isLiveAnimal(a)) return;
                const classification=String(a.classification??'').trim()
                    ||(this.classificationSignalsLoaded?this.inferredClassification(a):'');
                const key=keys[String(classification).toLowerCase().replace(/[\s_-]+/g,'')];
                if(key) counts[key]++;
            });
            return [
                {key:'femaleCalf',label:'Female Calf',count:counts.femaleCalf},
                {key:'yearlingHeifer',label:'Yearling Heifer',count:counts.yearlingHeifer},
                {key:'heifer',label:'Heifer',count:counts.heifer},
                {key:'heiferPregnant',label:'Heifer Pregnant',count:counts.heiferPregnant},
                {key:'cowDry',label:'Cow Dry',count:counts.cowDry},
                {key:'cowDryPregnant',label:'Cow Dry Pregnant',count:counts.cowDryPregnant},
                {key:'cowLactating',label:'Cow Lactating',count:counts.cowLactating},
                {key:'cowLactatingPregnant',label:'Cow Lactating Pregnant',count:counts.cowLactatingPregnant},
                {key:'maleCalf',label:'Male Calf',count:counts.maleCalf},
                {key:'yearlingBull',label:'Yearling Bull',count:counts.yearlingBull},
                {key:'juniorBull',label:'Junior Bull',count:counts.juniorBull},
                {key:'seniorBull',label:'Senior Bull',count:counts.seniorBull}
            ];
        },

        isLiveAnimal(animal) {
            const status=String(animal.status??'').trim().toLowerCase();
            return status ? status==='live' : !animal.nature_of_exit;
        },

        animalIdentifiers(animal) {
            return new Set([
                animal.id,animal.animal_id,animal.registry_number,animal.bolus_id,animal.tag_no,animal.tag_number
            ].map(value=>String(value??'').trim().toLowerCase()).filter(Boolean));
        },

        animalAge(animal) {
            const birthday=animal.birth_date||animal.birthday;
            if(!birthday) return null;
            const dob=new Date(`${String(birthday).slice(0,10)}T00:00:00`);
            const today=new Date();
            if(Number.isNaN(dob.getTime())||dob>today) return null;
            let age=today.getFullYear()-dob.getFullYear();
            const month=today.getMonth()-dob.getMonth();
            if(month<0||(month===0&&today.getDate()<dob.getDate())) age--;
            return age;
        },

        hasCalf(animal, identifiers=this.animalIdentifiers(animal)) {
            return this.records.some(calf=>identifiers.has(String(calf.dam_id??'').trim().toLowerCase()));
        },

        hasMilkProduction(animal, identifiers=this.animalIdentifiers(animal)) {
            if(Number(animal.days_in_milk)>0) return true;
            return this.milkProductionRecords.some(record=>
                identifiers.has(String(record.animal_id??'').trim().toLowerCase())
                && Number(record.milk_volume)>0
            );
        },

        hasPositivePregnancyCheck(animal, identifiers=this.animalIdentifiers(animal)) {
            const checks=this.matingRecords.filter(record=>
                (animal.id!=null&&record.carabao_id!=null&&String(record.carabao_id)===String(animal.id))
                || identifiers.has(String(record.animal_id??'').trim().toLowerCase())
            ).map(record=>{
                const result=String(record.pd_result??record.pregnancy_result??'').trim().toLowerCase();
                const status=['+','p','positive','pregnant','yes'].includes(result)?'positive'
                    :['-','n','negative','not pregnant','non-pregnant','no'].includes(result)?'negative':'';
                return {
                    status,
                    date:String(record.pd_date||record.pregnancy_check_date||record.breeding_date||record.mating_date||'').slice(0,10)
                };
            }).filter(record=>record.status);
            checks.sort((a,b)=>b.date.localeCompare(a.date));
            return checks[0]?.status==='positive';
        },

        inferredClassification(animal) {
            const age=this.animalAge(animal);
            if(age===null) return '';
            const sex=String(animal.sex??'').trim().toLowerCase();
            if(['m','male'].includes(sex)){
                if(age<1) return 'Male Calf';
                if(age<2) return 'Yearling Bull';
                if(age<4) return 'Junior Bull';
                return 'Senior Bull';
            }
            if(!['f','female'].includes(sex)) return '';

            const identifiers=this.animalIdentifiers(animal);
            const hasCalf=this.hasCalf(animal,identifiers);
            const lactating=this.hasMilkProduction(animal,identifiers);
            const pregnant=this.hasPositivePregnancyCheck(animal,identifiers);
            if(lactating) return pregnant?'Cow Lactating Pregnant':'Cow Lactating';
            if(hasCalf) return pregnant?'Cow Dry Pregnant':'Cow Dry';
            if(pregnant) return 'Heifer Pregnant';
            if(age<1) return 'Female Calf';
            if(age<2) return 'Yearling Heifer';
            return 'Heifer';
        },

        async loadMatingRecords() {
            const response=await fetch('/admin/breeding-api/records',{headers:{Accept:'application/json'}});
            if(!response.ok) throw new Error(await this.errorMessage(response));
            const payload=await response.json();
            const rows=Array.isArray(payload)?payload:(payload?.data??payload?.records??[]);
            if(!Array.isArray(rows)) throw new Error('The mating API did not return a record list.');
            this.matingRecords=rows;
        },

        loadMilkProductionRecords() {
            const saved=localStorage.getItem('cbed_milk_production_records');
            if(saved===null){
                this.milkProductionRecords=[];
                return;
            }
            const rows=JSON.parse(saved);
            if(!Array.isArray(rows)) throw new Error('Saved milk production data is not a record list.');
            this.milkProductionRecords=rows;
        },

        async inferAndSaveMissingClassifications() {
            const candidates=this.records.filter(animal=>
                this.isLiveAnimal(animal)&&!String(animal.classification??'').trim()
            ).map(animal=>({animal,classification:this.inferredClassification(animal)}))
                .filter(item=>item.classification);
            if(!candidates.length) return;

            const results=await Promise.all(candidates.map(async item=>{
                try {
                    const response=await fetch(`${apiUrl}/${encodeURIComponent(item.animal.id)}/classification`,{
                        method:'PATCH',
                        headers:this.requestHeaders(),
                        body:JSON.stringify({classification:item.classification})
                    });
                    if(!response.ok) throw new Error(await this.errorMessage(response));
                    return {...item,error:null};
                } catch(error) {
                    console.error(`Could not save inferred classification for animal ${item.animal.id}:`,error);
                    return {...item,error};
                }
            }));
            const saved=results.filter(item=>!item.error);
            saved.forEach(item=>{item.animal.classification=item.classification;});
            const failed=results.length-saved.length;
            if(failed){
                this.notify(`Saved ${saved.length} inferred classification(s); ${failed} could not be saved. Reload the page to retry.`, 'error');
            } else {
                this.notify(`Inferred and saved classifications for ${saved.length} animal(s).`);
            }
        },

        get provinces() {
            return [...new Set(this.records.map(a=>normalizeProvince(a.province)).filter(Boolean))].sort();
        },

        // Cooperative dropdown uses the real cooperative name and keeps its ID
        // available so farmer records linked by cooperative_id can also match.
        get availableCooperatives() {
            return this.cooperatives
                .map(c => ({
                    id: c?.id ?? c?.db_id ?? c?.cooperative_id ?? null,
                    value: String(c?.cooperative_name ?? c?.name ?? c?.coop_name ?? '').trim(),
                    code: String(c?.cooperative_code ?? '').trim(),
                    cda: String(c?.cda_registration_no ?? '').trim()
                }))
                .filter(c => c.value !== '')
                .filter((c, index, list) =>
                    list.findIndex(x => x.value.toLowerCase() === c.value.toLowerCase()) === index
                )
                .sort((a, b) => a.value.localeCompare(b.value));
        },

        get farmersForTransfer() {
            const selectedName = this.normalizeLookup(this.form.transfer_cooperative);
            if (!selectedName) return [];

            const selectedCoop = this.availableCooperatives.find(
                c => this.normalizeLookup(c.value) === selectedName
            );
            const selectedId = selectedCoop?.id == null ? '' : String(selectedCoop.id).trim();

            return this.farmers.map((f, index) => {
                const name = this.farmerName(f);
                const farmerCoopName = this.normalizeLookup(this.farmerCooperative(f));
                const farmerCoopId = this.farmerCooperativeId(f);

                // Support both common database designs: farmer cooperative name
                // or a numeric cooperative_id foreign key. Name matching remains
                // available for existing records and requires no migration.
                const matchesByName = farmerCoopName !== '' && farmerCoopName === selectedName;
                const matchesById = selectedId !== '' && farmerCoopId !== '' && farmerCoopId === selectedId;

                if (!name || (!matchesByName && !matchesById)) return null;
                return { ...f, _key: f.id ?? f.farmer_id ?? `${name}-${index}`, _name: name };
            }).filter(Boolean).sort((a, b) => a._name.localeCompare(b._name));
        },

        get totalPages() {
            return Math.max(1,Math.ceil(this.filteredRecords.length/this.perPage));
        },

        get paginatedRecords() {
            if (this.page>this.totalPages) this.page=this.totalPages;
            return this.filteredRecords.slice((this.page-1)*this.perPage,this.page*this.perPage);
        },

        countExit(value) {
            return this.records.filter(a=>a.nature_of_exit===value).length;
        },

        ageText(animal) {
            const value=animal.birth_date||animal.birthday;
            if(!value) return '—';
            const birth=new Date(`${String(value).slice(0,10)}T00:00:00`);
            const today=new Date();
            if(Number.isNaN(birth.getTime())||birth>today) return '—';
            let months=(today.getFullYear()-birth.getFullYear())*12+today.getMonth()-birth.getMonth();
            if(today.getDate()<birth.getDate()) months--;
            const years=Math.floor(months/12), remainingMonths=months%12;
            return years>0?`${years} yr${years===1?'':'s'} ${remainingMonths} mo`:`${remainingMonths} mo`;
        },

        formatBirthday(animal) {
            return this.formatDateValue(animal.birth_date||animal.birthday);
        },

        formatDateValue(value) {
            if(!value) return '—';
            const match=String(value).trim().match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
            if(match) return `${new Date(Number(match[1]),Number(match[2])-1,Number(match[3])).toLocaleString('en-US',{month:'long'})} ${Number(match[3])},${match[1]}`;
            const parsed=new Date(value);
            if(Number.isNaN(parsed.getTime())) return '—';
            return `${parsed.toLocaleString('en-US',{month:'long'})} ${parsed.getDate()},${parsed.getFullYear()}`;
        },

        detailValue(field, animal) {
            if(!animal) return '—';
            const dateValues={birthday:animal.birth_date||animal.birthday,date_of_acquisition:animal.acquisition_date||animal.date_of_acquisition,date_of_disposal_death:animal.death_date||animal.date_of_disposal_death};
            if(Object.prototype.hasOwnProperty.call(dateValues,field.key)) return this.formatDateValue(dateValues[field.key]);
            return animal[field.key]||'—';
        },

        formatRegion(value) {
            const region=String(value??'').trim();
            if(!region||/^region\s*(iii|3)$/i.test(region)||/^iii$/i.test(region)||region==='3') return 'Region III';
            return region;
        },

        notify(message,type='success') {
            this.toast={show:true,message,type};
            setTimeout(()=>this.toast.show=false,3500);
        },

        async loadRecords() {
            try {
                const response=await fetch(apiUrl,{headers:{'Accept':'application/json'}});
                if (!response.ok) throw new Error(await this.errorMessage(response));
                const payload=await response.json();
                const rows=Array.isArray(payload)?payload:(payload.data||payload.records||[]);
                if (!Array.isArray(rows)) throw new Error('The inventory API did not return a record list.');
                this.records=rows.map(r=>({...emptyRecord(),...r,province:normalizeProvince(r.province)}));
                this.apiConnected=true;
                this.page=1;
            } catch (e) {
                this.apiConnected=false;
                this.records=[];
                console.error('Carabao inventory API load failed:',e);
                this.notify('Cannot connect to the inventory API. Check Laravel routes/controller. No sample data is shown.','error');
            }
        },

        async saveClassification(animal, value) {
            const previous=animal.classification||'';
            animal.classification=value.trim();
            try {
                const response=await fetch(`${apiUrl}/${encodeURIComponent(animal.id)}/classification`,{
                    method:'PATCH',headers:this.requestHeaders(),body:JSON.stringify({classification:animal.classification})
                });
                if(!response.ok) throw new Error(await this.errorMessage(response));
                this.notify('Classification updated.');
            } catch(e) {
                animal.classification=previous;
                this.notify(e.message||'Unable to update classification.','error');
            }
        },

        async loadTransferLookups() {
            const readList = async (url) => {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) {
                    const detail = await response.text().catch(() => '');
                    throw new Error(`${url} returned HTTP ${response.status}${detail ? ': ' + detail.slice(0, 180) : ''}`);
                }
                const payload = await response.json();
                // Supports a plain JSON array, Laravel {data: [...]}, and common named wrappers.
                const list = Array.isArray(payload)
                    ? payload
                    : (payload?.data ?? payload?.records ?? payload?.farmers ?? payload?.cooperatives ?? []);
                if (!Array.isArray(list)) throw new Error(`${url} did not return a list of records.`);
                return list;
            };

            try {
                const [coops, farmers] = await Promise.all([
                    readList('/admin/cooperatives/data'),
                    readList('/admin/farmers/data')
                ]);
                this.cooperatives = coops;
                this.farmers = farmers;
                console.info(`Transfer lookups loaded: ${coops.length} cooperatives, ${farmers.length} farmers.`);
            } catch (e) {
                this.cooperatives = [];
                this.farmers = [];
                console.error('Could not load transfer cooperative/farmer lists:', e);
                this.notify('Could not load cooperatives or farmers. Check /admin/cooperatives/data and /admin/farmers/data in the browser.','error');
            }
        },

        normalizeLookup(value) {
            return String(value ?? '').trim().toLocaleLowerCase().replace(/\s+/g, ' ');
        },

        cooperativeName(c) {
            if (typeof c === 'string') return c.trim();
            return String(c?.cooperative_name ?? c?.name ?? c?.coop_name ?? c?.association_name ?? '').trim();
        },

        farmerName(f) {
            if (typeof f === 'string') return f.trim();
            const direct = String(f?.full_name ?? f?.farmer_name ?? f?.name ?? f?.recipient_name ?? '').trim();
            if (direct) return direct;
            const first = String(f?.first_name ?? f?.firstname ?? '').trim();
            const middle = String(f?.middle_name ?? f?.middlename ?? '').trim();
            const last = String(f?.last_name ?? f?.lastname ?? '').trim();
            return [last, first, middle].filter(Boolean).join(', ');
        },

        farmerRecipientName(value) {
            const raw = String(value ?? '').trim();
            if (!raw) return '';
            const farmer = this.farmers.find(f => {
                const ids = [f?.id, f?.farmer_id, f?.farmer_code, f?.code]
                    .filter(v => v !== null && v !== undefined && String(v).trim() !== '')
                    .map(v => String(v).trim());
                return ids.includes(raw) || this.normalizeLookup(this.farmerName(f)) === this.normalizeLookup(raw);
            });
            return farmer ? this.farmerName(farmer) : raw;
        },

        cooperativeNameForAnimal(animal) {
            const id=String(animal?.cooperative_id??'').trim();
            const coop=this.cooperatives.find(c=>String(c?.id??c?.db_id??c?.cooperative_id??'').trim()===id);
            return coop?this.cooperativeName(coop):String(animal?.cooperative_name??animal?.association_coop_institution??'').trim();
        },

        farmerNameForAnimal(animal) {
            const id=String(animal?.farmer_id??'').trim();
            const farmer=this.farmers.find(f=>String(f?.id??f?.farmer_id??'').trim()===id);
            return farmer?this.farmerName(farmer):this.farmerRecipientName(animal?.farmer_recipient);
        },

        farmerCooperative(f) {
            const value = f?.cooperative_name
                ?? f?.cooperative?.cooperative_name
                ?? f?.cooperative?.name
                ?? (typeof f?.cooperative === 'string' ? f.cooperative : null)
                ?? f?.coop_name
                ?? f?.association_name
                ?? f?.association_coop_institution
                ?? f?.cooperativeName
                ?? '';
            return String(value).trim();
        },

        farmerCooperativeId(f) {
            const value = f?.cooperative_id
                ?? f?.coop_id
                ?? f?.cooperative?.id
                ?? f?.cooperative?.cooperative_id
                ?? (typeof f?.cooperative === 'number' ? f.cooperative : '')
                ?? '';
            return value === null || value === undefined ? '' : String(value).trim();
        },

        farmerAddress(f) {
            return [f?.brgy ?? f?.barangay ?? f?.barangay_name, f?.municipality ?? f?.city ?? f?.town, f?.province, f?.region]
                .map(v=>String(v??'').trim()).filter(Boolean).join(', ');
        },

        onNatureOfExitChange() {
            if (['Transferred','Sold','Disposed'].includes(this.form.nature_of_exit)) {
                this.form.value_of_disposed_animals = this.form.acquisition_cost ?? '';
            } else {
                this.form.transfer_cooperative = '';
                this.form.disposed_to = '';
                this.form.place_of_disposal = '';
                if (['Dead','Culled',''].includes(this.form.nature_of_exit)) this.form.value_of_disposed_animals = '';
            }
        },

        onTransferCooperativeChange() {
            this.form.disposed_to = '';
            this.form.place_of_disposal = '';
        },

        onTransferFarmerChange() {
            const selectedId = String(this.form.disposed_to || '');

            const farmer = this.farmersForTransfer.find(
                f => String(f.id) === selectedId
            );

            if (!farmer) {
                this.form.place_of_disposal = '';
                return;
            }

            const addressParts = [
                farmer.barangay,
                farmer.municipality,
                farmer.province
            ].filter(Boolean);

            this.form.place_of_disposal = addressParts.join(', ');
        },

        clearFilters() {
            this.search='';this.exitFilter='all';this.sexFilter='all';this.provinceFilter='all';this.birthYearFilter='all';this.page=1;
        },

        openAdd() {
            this.formMode='add';this.form={...emptyRecord(),transfer_cooperative:''};this.showForm=true;
        },

        editAnimal(animal) {
            if (!animal) return;
            const recipient = this.farmers.find(f => this.normalizeLookup(this.farmerName(f)) === this.normalizeLookup(animal.disposed_to));
            let recipientCooperative = '';
            if (recipient) {
                const farmerCoopName = this.farmerCooperative(recipient);
                const farmerCoopId = this.farmerCooperativeId(recipient);
                const matchedCoop = this.availableCooperatives.find(c =>
                    (farmerCoopName && this.normalizeLookup(c.value) === this.normalizeLookup(farmerCoopName)) ||
                    (farmerCoopId && c.id != null && String(c.id) === farmerCoopId)
                );
                recipientCooperative = matchedCoop?.value || farmerCoopName;
            }
            this.formMode='edit';
            this.form={...emptyRecord(),...animal,transfer_cooperative:animal.transfer_cooperative || recipientCooperative};
            this.showView=false;
            this.showForm=true;
            if (['Transferred','Sold','Disposed'].includes(this.form.nature_of_exit)) {
                this.form.value_of_disposed_animals=this.form.acquisition_cost ?? this.form.value_of_disposed_animals ?? '';
            }
            if (recipient && !this.form.place_of_disposal) this.form.place_of_disposal=this.farmerAddress(recipient);
        },

        viewAnimal(animal) {
            this.selectedAnimal={...animal};this.showView=true;
        },

        requestHeaders() {
            const csrf=document.querySelector('meta[name="csrf-token"]')?.content;
            return {'Accept':'application/json','Content-Type':'application/json',...(csrf?{'X-CSRF-TOKEN':csrf}:{})};
        },

        async errorMessage(response) {
            const raw=await response.text();
            try {
                const data=JSON.parse(raw);
                const validation=data.errors
                    ? Object.entries(data.errors).map(([field,messages]) =>
                        `${field}: ${Array.isArray(messages)?messages.join(', '):messages}`
                      ).join(' | ')
                    : '';
                return [data.message,validation].filter(Boolean).join(' — ')
                    || `Request failed (${response.status}).`;
            } catch(e) {
                return raw || `Request failed (${response.status}).`;
            }
        },

        async saveAnimal() {
            this.saving=true;
            try {
                if (!this.apiConnected) throw new Error('Inventory API is not connected. Refresh the page and check Laravel routes.');
                const data={...this.form};
                data.province=normalizeProvince(data.province);
                if (['Transferred','Sold','Disposed'].includes(data.nature_of_exit)) {
                    data.value_of_disposed_animals = data.acquisition_cost ?? '';
                    if (!data.disposed_to) throw new Error('Select the cooperative and recipient farmer for a transfer, sale, or disposal.');
                    if (!data.place_of_disposal) throw new Error('The selected recipient has no address available. Check the farmer address fields.');
                }
                // transfer_cooperative is a UI lookup field, not a database column.
                delete data.transfer_cooperative;
                const editing=this.formMode==='edit';
                const url=editing?`${apiUrl}/${encodeURIComponent(data.id)}`:apiUrl;
                const response=await fetch(url,{
                    method:editing?'PUT':'POST',
                    headers:this.requestHeaders(),
                    body:JSON.stringify(data)
                });
                if(!response.ok) throw new Error(await this.errorMessage(response));
                await this.loadRecords();
                if (!this.apiConnected) throw new Error('Record saved, but the inventory list could not be reloaded.');
                this.showForm=false;
                this.notify(editing?'Animal record updated.':'Animal record added.');
            } catch(e) {
                this.notify(e.message||'Unable to save record.','error');
            } finally {
                this.saving=false;
            }
        },

        openBulk() {
            this.bulkRows=[this.newBulkRow(),this.newBulkRow()];
            this.showBulk=true;
        },

        newBulkRow() {
            return {...emptyRecord(),key:this.nextKey++};
        },

        addBulkRow() {
            this.bulkRows.push(this.newBulkRow());
        },

        async saveBulk() {
            const rows=this.bulkRows
                .filter(r=>Object.keys(r).some(k=>k!=='key'&&String(r[k]??'').trim()!==''))
                .map(r=>{const x={...r};delete x.key;x.province=normalizeProvince(x.province);return x;});

            if (!rows.length) return this.notify('Enter at least one animal record.','error');
            if (!this.apiConnected) return this.notify('Inventory API is not connected. Refresh the page and check Laravel routes.','error');

            const seenBolus=new Set();
            const seenRegistry=new Set();
            const existingBolus=new Set(this.records.map(r=>String(r.bolus_id||'').trim().toLowerCase()).filter(Boolean));
            const existingRegistry=new Set(this.records.map(r=>String(r.registry_number||'').trim().toLowerCase()).filter(Boolean));
            const unique=[];
            let skipped=0;

            for(const row of rows) {
                const bolus=String(row.bolus_id||'').trim().toLowerCase();
                const registry=String(row.registry_number||'').trim().toLowerCase();
                if ((bolus&&(existingBolus.has(bolus)||seenBolus.has(bolus))) ||
                    (registry&&(existingRegistry.has(registry)||seenRegistry.has(registry)))) {
                    skipped++;
                    continue;
                }
                if(bolus)seenBolus.add(bolus);
                if(registry)seenRegistry.add(registry);
                unique.push(row);
            }

            if(!unique.length)return this.notify('No new records. Duplicate Bolus IDs or Registry Numbers were skipped.','error');

            this.saving=true;
            try {
                const response=await fetch(`${apiUrl}/bulk`,{
                    method:'POST',
                    headers:this.requestHeaders(),
                    body:JSON.stringify({records:unique})
                });
                if(!response.ok)throw new Error(await this.errorMessage(response));
                await this.loadRecords();
                if (!this.apiConnected) throw new Error('Import submitted, but the inventory list could not be reloaded.');
                this.showBulk=false;
                this.notify(`${unique.length} record(s) added. ${skipped} duplicate record(s) skipped.`);
            } catch(e) {
                this.notify(e.message||'Bulk add failed.','error');
            } finally {
                this.saving=false;
            }
        },

        async deleteAnimal(animal) {
            if(!confirm(`Delete the record for Bolus ID "${animal.bolus_id||'(blank)'}"?`))return;
            if(!this.apiConnected)return this.notify('Inventory API is not connected. Refresh the page and check Laravel routes.','error');
            try {
                const response=await fetch(`${apiUrl}/${encodeURIComponent(animal.id)}`,{
                    method:'DELETE',headers:this.requestHeaders()
                });
                if(!response.ok)throw new Error(await this.errorMessage(response));
                await this.loadRecords();
                this.notify('Animal record deleted.');
            } catch(e) {
                this.notify(e.message||'Delete failed.','error');
            }
        },

        downloadTemplate() {
            if(typeof XLSX==='undefined')return this.notify('Excel library did not load. Refresh the page.','error');
            const headers=this.fields.map(f=>f.label);
            const ws=XLSX.utils.aoa_to_sheet([headers]);
            ws['!cols']=headers.map(h=>({wch:Math.max(18,Math.min(34,h.length+3))}));
            ws['!autofilter']={ref:`A1:${XLSX.utils.encode_col(headers.length-1)}1`};
            const wb=XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb,ws,'Carabao Inventory');
            XLSX.writeFile(wb,'cbed_carabao_inventory_template.xlsx');
            this.notify('Blank Excel template downloaded.');
        },

        exportXLSX(rows=this.records,filename='cbed_carabao_inventory.xlsx') {
            if(typeof XLSX==='undefined')return this.notify('Excel library did not load. Refresh the page.','error');
            const data=rows.map(r=>{
                const item={};
                this.fields.forEach(f=>item[f.label]=r[f.key]??'');
                return item;
            });
            const headers=this.fields.map(f=>f.label);
            const ws=XLSX.utils.json_to_sheet(data,{header:headers});
            ws['!cols']=headers.map(h=>({wch:Math.max(18,Math.min(34,h.length+3))}));
            if(ws['!ref'])ws['!autofilter']={ref:ws['!ref']};
            const wb=XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb,ws,'Carabao Inventory');
            XLSX.writeFile(wb,filename);
            this.notify(`${rows.length} record(s) exported.`);
        },

        async importExcel(event) {
            const file=event.target.files?.[0];
            event.target.value='';
            if(!file)return;
            if(typeof XLSX==='undefined')return this.notify('Excel library did not load. Refresh the page.','error');
            if(!/\.(xlsx|xls)$/i.test(file.name))return this.notify('Choose an Excel .xlsx or .xls file.','error');
            if(!this.apiConnected) {
                await this.loadRecords();
                if(!this.apiConnected) return this.notify('Cannot import because the inventory API is not connected. Check Laravel routes/controller first.','error');
            }

            this.saving=true;
            try {
                const buffer=await file.arrayBuffer();
                const workbook=XLSX.read(buffer,{type:'array',cellDates:false});
                const sheet=workbook.Sheets[workbook.SheetNames[0]];
                if(!sheet)throw new Error('No worksheet found in the workbook.');

                // raw:true preserves Excel date serials so they can be converted safely below.
                const matrix=XLSX.utils.sheet_to_json(sheet,{header:1,defval:'',raw:true,blankrows:false});
                if(matrix.length<2)throw new Error('No data rows found. Fill in the template and try again.');

                const norm=s=>String(s??'').trim().toLowerCase()
                    .replace(/[\/\-]+/g,' ')
                    .replace(/[_]+/g,' ')
                    .replace(/\s+/g,' ');
                const aliases={
                    farmer_recipient:['farmer / recipient','farmer/recipient','farmer recipient'],
                    bolus_id:['bolus id','bolus_id','bolus number'],
                    registry_number:['registry number','registry_number','registry no','registry no.'],
                    tag_no:['tag no','tag no.','tag number','tag_no','tag_number'],
                    sex:['sex','gender'],
                    birthday:['birthday','birth date','date of birth'],
                    breed:['breed'],
                    sire_id:['sire id','sire_id'],
                    sire_name:['sire name','sire_name'],
                    dam_id:['dam id','dam_id'],
                    dam_name:['dam name','dam_name'],
                    birthday_place:['birthday place','birthday_place','birth place'],
                    breeder:['breeder'],
                    association_coop_institution:['name of association / coop / institution','name of association/coop/institution','association / coop / institution','association coop institution'],
                    brgy:['barangay','brgy'],
                    municipality:['municipality'],
                    province:['province'],
                    district:['district'],
                    region:['region'],
                    date_of_acquisition:['date of acquisition','date_of_acquisition','acquisition date'],
                    acquisition_cost:['acquisition cost','acquisition_cost','aquisition cost','aquisition_cost'],
                    nature_of_exit:['nature of exit','nature_of_exit'],
                    reason_of_exit:['reason of exit','reason_of_exit'],
                    type_of_entrustment:['type of entrustment','type_of_entrustment'],
                    ownership_status:['ownership status','ownership_status','ownership tatus'],
                    disposed_to:['animal sold / transferred / disposed to','animal sold or transferred to / disposed to','disposed_to','sold transferred disposed to'],
                    place_of_disposal:['place of disposal / transferred','place of disposal/transferred','place_of_disposal','place of disposal transferred'],
                    date_of_disposal_death:['date of disposal / death','date of disposal/death','date_of_disposal_death'],
                    value_of_disposed_animals:['value of disposed animals','value_of_disposed_animals'],
                    remarks:['remarks']
                };

                const headers=matrix[0].map(norm);
                const indexes={};
                Object.entries(aliases).forEach(([key,names])=>{
                    indexes[key]=headers.findIndex(h=>names.map(norm).includes(h));
                });

                const missingHeaders=Object.entries(aliases)
                    .filter(([key])=>indexes[key]===-1)
                    .map(([key])=>fields.find(f=>f.key===key)?.label||key);
                if(missingHeaders.length) {
                    throw new Error('Excel header(s) missing or not recognized: '+missingHeaders.join(', ')+'. Download a fresh template and keep its header row unchanged.');
                }

                const dateFields=new Set(['birthday','date_of_acquisition','date_of_disposal_death']);
                const normalizeDateCell=value=>{
                    if(value===null||value===undefined||String(value).trim()==='')return '';
                    if(typeof value==='number' && Number.isFinite(value)) {
                        const d=XLSX.SSF.parse_date_code(value);
                        if(!d)return '';
                        return `${String(d.y).padStart(4,'0')}-${String(d.m).padStart(2,'0')}-${String(d.d).padStart(2,'0')}`;
                    }
                    const valueText=String(value).trim();
                    if(/^\d{4}-\d{1,2}-\d{1,2}/.test(valueText)) {
                        const parts=valueText.slice(0,10).split('-');
                        return `${parts[0]}-${String(parts[1]).padStart(2,'0')}-${String(parts[2]).padStart(2,'0')}`;
                    }
                    const us=valueText.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
                    if(us) return `${us[3]}-${String(us[1]).padStart(2,'0')}-${String(us[2]).padStart(2,'0')}`;
                    return valueText;
                };

                const imported=matrix.slice(1)
                    .filter(row=>row.some(v=>String(v??'').trim()!==''))
                    .map((row,rowIndex)=>{
                        const record=emptyRecord();
                        Object.keys(aliases).forEach(key=>{
                            const i=indexes[key];
                            const value=row[i]??'';
                            record[key]=dateFields.has(key)
                                ? normalizeDateCell(value)
                                : String(value).trim();
                        });
                        record.sex=String(record.sex||'').trim();
                        if(['F','FEMALE'].includes(record.sex.toUpperCase()))record.sex='Female';
                        if(['M','MALE'].includes(record.sex.toUpperCase()))record.sex='Male';
                        record.acquisition_cost=String(record.acquisition_cost??'').replace(/,/g,'').trim();
                        record.value_of_disposed_animals=String(record.value_of_disposed_animals??'').replace(/,/g,'').trim();
                        record._excelRow=rowIndex+2;
                        return record;
                    });

                if(!imported.length)throw new Error('No inventory records found.');

                const existingBolus=new Set(this.records.map(r=>String(r.bolus_id||'').trim().toLowerCase()).filter(Boolean));
                const existingRegistry=new Set(this.records.map(r=>String(r.registry_number||'').trim().toLowerCase()).filter(Boolean));
                const seenBolus=new Set();
                const seenRegistry=new Set();
                const unique=[];
                let skipped=0;

                imported.forEach(record=>{
                    const bolus=String(record.bolus_id||'').trim().toLowerCase();
                    const registry=String(record.registry_number||'').trim().toLowerCase();
                    if ((bolus&&(existingBolus.has(bolus)||seenBolus.has(bolus))) ||
                        (registry&&(existingRegistry.has(registry)||seenRegistry.has(registry)))) {
                        skipped++;
                        return;
                    }
                    delete record._excelRow;
                    if(bolus)seenBolus.add(bolus);
                    if(registry)seenRegistry.add(registry);
                    unique.push(record);
                });

                if(!unique.length)throw new Error('No new records. All imported Bolus IDs or Registry Numbers already exist.');

                const response=await fetch(`${apiUrl}/bulk`,{
                    method:'POST',
                    headers:this.requestHeaders(),
                    body:JSON.stringify({records:unique})
                });
                if(!response.ok)throw new Error(await this.errorMessage(response));
                await this.loadRecords();
                if(!this.apiConnected)throw new Error('Import request completed, but the inventory list could not be reloaded.');

                this.page=1;
                this.notify(`${unique.length} record(s) imported. ${skipped} duplicate record(s) skipped.`);
            } catch(e) {
                console.error('Carabao Excel import failed:',e);
                this.notify(e.message||'Excel import failed.','error');
            } finally {
                this.saving=false;
            }
        }
    };
}
</script>
@endsection

@push('styles')
<style>
[x-cloak]{display:none!important}
.filter-input,.form-input{width:100%;border:1px solid #cbd5e1;border-radius:.5rem;background:#fff;padding:.625rem .75rem;font-size:.875rem;color:#1e293b;outline:none}
.filter-input:focus,.form-input:focus{border-color:#0786a5;box-shadow:0 0 0 3px rgba(7,134,165,.14)}
.filter-label{display:block;margin-bottom:.375rem;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
.action-primary,.action-secondary{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;border-radius:.5rem;padding:.625rem 1rem;font-size:.875rem;font-weight:600}
.action-primary{background:#0b1f3a;color:#fff}.action-primary:hover{background:#122b4d}
.action-secondary{border:1px solid #cbd5e1;background:#fff;color:#334155}.action-secondary:hover{background:#f1f5f9}
.page-btn{border:1px solid #cbd5e1;border-radius:.5rem;background:#fff;padding:.5rem .75rem;font-size:.875rem;font-weight:600;color:#334155}
.page-btn:hover:not(:disabled){background:#f1f5f9}
.page-btn:disabled{opacity:.4;cursor:not-allowed}

/* Hero */
.pcc-mating-hero{position:relative;isolation:isolate;overflow:hidden;background:radial-gradient(circle at 90% 18%,rgba(255,196,56,.25),transparent 19%),linear-gradient(118deg,#082d50 0%,#075785 55%,#0786a5 100%);box-shadow:0 16px 32px rgba(7,62,99,.2)}
.pcc-mating-hero::after{content:"";position:absolute;z-index:-1;width:19rem;height:19rem;right:11%;top:-14rem;border:1px solid rgba(255,214,92,.45);border-radius:50%;box-shadow:0 0 0 26px rgba(255,214,92,.08),0 0 0 54px rgba(255,255,255,.045)}
.pcc-brand-mark{position:relative;flex:none;width:4.35rem;height:4.35rem;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.34);border-radius:1.35rem;background:linear-gradient(145deg,rgba(255,255,255,.22),rgba(255,255,255,.07));color:#ffd05b;font-size:2rem;box-shadow:0 10px 22px rgba(0,28,52,.2)}
.pcc-brand-mark span{position:absolute;right:-.45rem;bottom:-.4rem;display:grid;width:1.65rem;height:1.65rem;place-items:center;border:2px solid #075785;border-radius:999px;background:#ffd05b;color:#12345a;font-size:.75rem}
.pcc-program-tag{display:inline-flex;align-items:center;gap:.4rem;margin-top:.7rem;border:1px solid rgba(255,213,91,.35);border-radius:999px;background:rgba(4,37,68,.24);padding:.35rem .7rem;color:#ffe08a;font-size:.7rem;font-weight:700;letter-spacing:.035em}
.pcc-mating-hero .action-secondary{border-color:rgba(255,255,255,.28);background:rgba(255,255,255,.1);color:#fff;backdrop-filter:blur(8px)}
.pcc-mating-hero .action-secondary:hover{background:rgba(255,255,255,.2)}
.pcc-mating-hero .action-primary{background:#ffd05b;color:#12345a;box-shadow:0 7px 16px rgba(4,37,68,.2)}
.pcc-mating-hero .action-primary:hover{background:#ffdc7d}

/* Stat cards */
.pcc-stat-card{position:relative;overflow:hidden;border:1px solid #dce6ed;border-top:4px solid #0b5e8b;border-radius:1rem;box-shadow:0 5px 18px rgba(15,45,67,.06);transition:transform .16s ease,box-shadow .16s ease}
.pcc-stat-card:hover{transform:translateY(-3px);box-shadow:0 14px 28px rgba(7,62,99,.13)}
.pcc-stat-card:nth-child(2){border-top-color:#e87523}.pcc-stat-card:nth-child(3){border-top-color:#25835b}.pcc-stat-card:nth-child(4){border-top-color:#0786a5}
.pcc-stat-icon{background:linear-gradient(145deg,#082d50,#0b6d9b);color:#fff;border-radius:1rem;box-shadow:0 8px 18px rgba(7,62,99,.2)}
.pcc-stat-card:nth-child(2) .pcc-stat-icon{background:linear-gradient(145deg,#a94b0b,#f28a32)}
.pcc-stat-card:nth-child(3) .pcc-stat-icon{background:linear-gradient(145deg,#176342,#38a36f)}
.pcc-stat-card:nth-child(4) .pcc-stat-icon{background:linear-gradient(145deg,#075785,#0798b6)}

/* Panels and tables */
.inventory-panel{border-color:#dce6ed;box-shadow:0 7px 24px rgba(15,45,67,.06)}
.inventory-panel thead,.inventory-panel thead th{background:#103c60;color:#fff;font-weight:700}
.inventory-panel thead th{border-bottom:3px solid #e8b22f}
.inventory-panel tbody tr{transition:background-color .14s ease}
.inventory-panel tbody tr:hover{background:#f0f8fb}
.inventory-table-scroll{max-height:72vh}
.inventory-data-table{border-collapse:separate;border-spacing:0}
.inventory-data-table thead th{position:sticky;top:0;z-index:20}
.inventory-data-table tbody tr:nth-child(even){background:#f8fafc}
.inventory-data-table tbody tr:hover{background:#f0f8fb}
.inventory-data-table tbody td{border-bottom:1px solid #e8edf3;vertical-align:middle}
.inventory-data-table .inventory-sticky-1,.inventory-data-table .inventory-sticky-2,.inventory-data-table .inventory-sticky-3{position:sticky;background:#fff;box-shadow:1px 0 0 #e2e8f0}
.inventory-data-table .inventory-sticky-1{left:0;width:220px;min-width:220px;max-width:220px}
.inventory-data-table .inventory-sticky-2{left:220px;width:220px;min-width:220px;max-width:220px}
.inventory-data-table .inventory-sticky-3{left:440px;width:160px;min-width:160px;max-width:160px}
.inventory-data-table tbody .inventory-sticky-1,.inventory-data-table tbody .inventory-sticky-2,.inventory-data-table tbody .inventory-sticky-3{z-index:5}
.inventory-data-table thead .inventory-sticky-1,.inventory-data-table thead .inventory-sticky-2,.inventory-data-table thead .inventory-sticky-3{z-index:30;background:#103c60;color:#fff;box-shadow:none}
.inventory-data-table input{min-height:36px}
.inventory-data-table tbody td:last-child button{min-width:36px;min-height:36px}

/* Modals */
.inventory-modal-backdrop{animation:inventory-fade-in .14s ease-out}
.inventory-modal-panel{border:1px solid #dbe4ee}
.inventory-modal-header{position:sticky;top:0;z-index:50;background:linear-gradient(105deg,#082d50,#075785);border-color:#075785}
.inventory-modal-header h3,.inventory-modal-header p{color:#fff!important}
.inventory-modal-header button{display:grid;width:2.5rem;height:2.5rem;place-items:center;border:1px solid rgba(255,255,255,.3);border-radius:.75rem;color:#fff}
.inventory-modal-header button:hover{background:rgba(255,255,255,.14)}
.inventory-modal-panel form>h4{border-left:4px solid #e8b22f;padding:.65rem 0 .65rem .8rem;color:#12345a;background:linear-gradient(90deg,#eaf0f6,transparent 78%);border-radius:0 .5rem .5rem 0}
.inventory-modal-panel label{color:#334155;line-height:1.4}
.inventory-modal-panel .field-input{min-height:44px}
.inventory-modal-panel textarea.field-input{min-height:88px}
.inventory-modal-panel button.bg-blue-700{background:#0b1f3a}
.inventory-modal-panel button.bg-blue-700:hover{background:#122b4d}
.inventory-modal-panel table thead{position:sticky;top:0;z-index:10}
.inventory-modal-panel table input,.inventory-modal-panel table select{min-height:40px;border:1px solid #cbd5e1;border-radius:.5rem;background:#fff;padding:.5rem .6rem}
.inventory-modal-panel table tbody tr:nth-child(even){background:#f8fafc}
.inventory-detail-card{transition:border-color .15s ease,transform .15s ease}
.inventory-detail-card:hover{border-color:#93c5fd;transform:translateY(-1px)}
.field-input{display:block;width:100%;margin-top:.35rem;border:1px solid #cbd5e1;border-radius:.5rem;padding:.65rem .75rem;font-weight:400;background:#fff}
.field-input:focus{outline:2px solid #dbe4ee;border-color:#0b1f3a}
@keyframes inventory-fade-in{from{opacity:0}to{opacity:1}}
@media (prefers-reduced-motion:reduce){.inventory-page *,.inventory-page *::before,.inventory-page *::after{animation-duration:.01ms!important;transition-duration:.01ms!important}}
</style>
@endpush