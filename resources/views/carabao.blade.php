{{-- resources/views/carabao.blade.php --}}
@extends('layouts.admin')

@section('title', 'Carabao Management | CBED')
@section('page-heading', 'Carabao Management')

@section('content')
<div
    x-data="carabaoPage()"
    x-init="init()"
    x-cloak
    class="min-w-0 bg-slate-50 text-slate-800"
>
    {{-- HEADER --}}
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <div class="mb-2 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <i class="fa-solid fa-house"></i>
                <span>/</span>
                <span>CBED Data System</span>
                <span>/</span>
                <span class="font-semibold text-slate-700">Carabaos</span>
            </div>

            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                Carabao Management
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Manage animal identification, farmer ownership, cooperative,
                province, classification, and inventory.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">

            <button
                type="button"
                @click="downloadImportTemplate()"
                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                <i class="fa-solid fa-file-excel"></i>
                Excel Template
            </button>
            <button type="button" @click="downloadPDFReport()"
                class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-800 hover:bg-rose-100">
                <i class="fa-solid fa-file-pdf"></i> PDF Report
            </button>

            <label
                class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                <i class="fa-solid fa-file-import"></i> Import CSV
                <input type="file" accept=".csv,text/csv" class="hidden"
                    @change="importCSV($event)">
            </label>

            <button type="button" @click="exportCSV()"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                <i class="fa-solid fa-file-export"></i> Export CSV
            </button>

            <button type="button" @click="openAddModal()"
                class="inline-flex items-center gap-2 rounded-lg bg-[#0b1f3a] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#122b4d]">
                <i class="fa-solid fa-plus"></i> Add Carabaos
            </button>
        </div>
    </div>

    {{-- LOADING / ERROR --}}
    <div x-show="loading"
        class="mb-5 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
        <i class="fa-solid fa-spinner fa-spin mr-2"></i>
        Loading carabao, farmer, and cooperative records...
    </div>

    <div x-show="loadError"
        class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <p class="font-semibold">Unable to load the records.</p>
        <p class="mt-1" x-text="loadError"></p>
        <button type="button" @click="init()"
            class="mt-3 rounded-lg bg-amber-800 px-4 py-2 text-sm font-semibold text-white">
            Retry
        </button>
    </div>

    {{-- SUMMARY CARDS --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <template x-for="card in summaryCards()" :key="card.label">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-slate-500" x-text="card.label"></p>
                        <p class="mt-2 text-3xl font-bold text-slate-900" x-text="card.value"></p>
                        <p class="mt-1 text-xs text-slate-500" x-text="card.description"></p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl"
                        :class="card.iconBg">
                        <i :class="card.icon + ' text-xl ' + card.iconColor"></i>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- DISTRIBUTION --}}
    <div class="mb-6 grid grid-cols-1 gap-4 xl:grid-cols-2">
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <div>
                    <h2 class="font-bold text-slate-900">Animals per Province</h2>
                    <p class="mt-1 text-sm text-slate-500">Inventory grouped by province.</p>
                </div>
                <span class="rounded-lg bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-800"
                    x-text="carabaos.length + ' animals'"></span>
            </div>

            <div x-show="provinceBreakdown().length" class="space-y-4 p-5">
                <template x-for="item in provinceBreakdown()" :key="item.name">
                    <div>
                        <div class="mb-1.5 flex justify-between gap-3 text-sm">
                            <span class="font-medium" x-text="item.name"></span>
                            <span class="font-bold" x-text="item.count"></span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-blue-700"
                                :style="'width:' + (item.count / Math.max(1, provinceBreakdown()[0].count) * 100) + '%'"></div>
                        </div>
                    </div>
                </template>
            </div>

            <div x-show="!provinceBreakdown().length" class="p-8 text-center text-sm text-slate-500">
                No province information available.
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <div>
                    <h2 class="font-bold text-slate-900">Animals per Breed</h2>
                    <p class="mt-1 text-sm text-slate-500">Inventory grouped by breed.</p>
                </div>
                <span class="rounded-lg bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-800"
                    x-text="carabaos.length + ' animals'"></span>
            </div>

            <div x-show="breedBreakdown().length" class="space-y-4 p-5">
                <template x-for="item in breedBreakdown()" :key="item.name">
                    <div>
                        <div class="mb-1.5 flex justify-between gap-3 text-sm">
                            <span class="font-medium" x-text="item.name"></span>
                            <span class="font-bold" x-text="item.count"></span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-indigo-600"
                                :style="'width:' + (item.count / Math.max(1, breedBreakdown()[0].count) * 100) + '%'"></div>
                        </div>
                    </div>
                </template>
            </div>

            <div x-show="!breedBreakdown().length" class="p-8 text-center text-sm text-slate-500">
                No breed information available.
            </div>
        </section>
    </div>

    {{-- DIRECTORY --}}
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Carabao Directory</h2>
                <p class="mt-1 text-sm text-slate-500">Search, filter, and maintain animal records.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold">
                    <span x-text="filteredCarabaos().length"></span> records
                </span>
                <button type="button" @click="resetFilters()"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">
                    <i class="fa-solid fa-rotate-left mr-1"></i> Reset Filters
                </button>
            </div>
        </div>

        {{-- FILTERS --}}
        <div class="grid grid-cols-1 gap-3 border-b border-slate-200 bg-slate-50 p-5 sm:grid-cols-2 xl:grid-cols-6">
            <div class="xl:col-span-2">
                <label class="field-label">Search Animal</label>
                <input type="search" x-model="filters.search" @input="page=1"
                    placeholder="Animal ID, bolus, farmer, cooperative..."
                    class="field-input">
            </div>

            <div>
                <label class="field-label">Province</label>
                <select x-model="filters.province" @change="page=1" class="field-input">
                    <option value="">All Provinces</option>
                    <template x-for="item in provinceOptions()" :key="item">
                        <option :value="item" x-text="item"></option>
                    </template>
                </select>
            </div>

            <div>
                <label class="field-label">Breed</label>
                <select x-model="filters.breed" @change="page=1" class="field-input">
                    <option value="">All Breeds</option>
                    <template x-for="item in breedOptions()" :key="item">
                        <option :value="item" x-text="item"></option>
                    </template>
                </select>
            </div>

            <div>
                <label class="field-label">Sex</label>
                <select x-model="filters.sex" @change="page=1" class="field-input">
                    <option value="">All Sexes</option>
                    <option>Female</option>
                    <option>Male</option>
                </select>
            </div>

            <div>
                <label class="field-label">Status</label>
                <select x-model="filters.status" @change="page=1" class="field-input">
                    <option value="">All Statuses</option>
                    <option>Live</option>
                    <option>Out</option>
                </select>
            </div>

            <div>
                <label class="field-label">Classification</label>
                <select x-model="filters.classification" @change="page=1" class="field-input">
                    <option value="">All Classifications</option>
                    <template x-for="item in classificationOptions" :key="item">
                        <option :value="item" x-text="item"></option>
                    </template>
                </select>
            </div>

            <div>
                <label class="field-label">Entrustment</label>
                <select x-model="filters.entrustment" @change="page=1" class="field-input">
                    <option value="">All Types</option>
                    <template x-for="item in entrustmentOptions" :key="item">
                        <option :value="item" x-text="item"></option>
                    </template>
                </select>
            </div>
        </div>

                {{-- TABLE --}}
        <div class="overflow-x-auto">
            <table class="w-full min-w-[2200px] text-left text-sm">
                <thead class="bg-slate-100 text-xs uppercase tracking-wider text-slate-600">
                    <tr>
                        <th class="px-4 py-4">Farmer / Owner</th>
                        <th class="px-4 py-4">Cooperative</th>
                        <th class="px-4 py-4">Animal ID</th>
                        <th class="px-4 py-4">Bolus</th>
                        <th class="px-4 py-4">Tag No.</th>
                        <th class="px-4 py-4">Sire ID</th>
                        <th class="px-4 py-4">Sire Name</th>
                        <th class="px-4 py-4">Dam ID</th>
                        <th class="px-4 py-4">Dam Name</th>
                        <th class="px-4 py-4">Parity</th>
                        <th class="px-4 py-4">Province</th>
                        <th class="px-4 py-4">Sex</th>
                        <th class="px-4 py-4">Birthday</th>
                        <th class="px-4 py-4">Age</th>
                        <th class="px-4 py-4">Breed</th>
                        <th class="px-4 py-4">Classification</th>
                        <th class="px-4 py-4">Entrustment</th>
                        <th class="px-4 py-4">Acquisition</th>
                        <th class="px-4 py-4">Status</th>
                        <th class="px-4 py-4 text-center">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    <template x-for="animal in paginatedCarabaos()" :key="animal._key">
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-4" x-text="farmerName(animal)"></td>
                            <td class="px-4 py-4" x-text="cooperativeName(animal)"></td>
                            <td class="whitespace-nowrap px-4 py-4 font-semibold text-[#0b1f3a]" x-text="animal.animal_id"></td>
                            <td class="whitespace-nowrap px-4 py-4" x-text="animal.bolus || '—'"></td>
                            <td class="whitespace-nowrap px-4 py-4" x-text="animal.tag_no || '—'"></td>
                            <td class="whitespace-nowrap px-4 py-4" x-text="animal.sire_id || '—'"></td>
                            <td class="whitespace-nowrap px-4 py-4" x-text="animal.sire_name || '—'"></td>
                            <td class="whitespace-nowrap px-4 py-4" x-text="animal.dam_id || '—'"></td>
                            <td class="whitespace-nowrap px-4 py-4" x-text="animal.dam_name || '—'"></td>
                            <td class="whitespace-nowrap px-4 py-4 text-center" x-text="animal.parity ?? '—'"></td>
                            <td class="px-4 py-4" x-text="provinceName(animal)"></td>
                            <td class="px-4 py-4" x-text="animal.sex || '—'"></td>
                            <td class="whitespace-nowrap px-4 py-4" x-text="formatDate(animal.birthday)"></td>
                            <td class="whitespace-nowrap px-4 py-4" x-text="calculateAge(animal.birthday)"></td>
                            <td class="px-4 py-4" x-text="animal.breed || '—'"></td>
                            <td class="px-4 py-4" x-text="animal.classification || '—'"></td>
                            <td class="px-4 py-4" x-text="animal.type_of_entrustment || '—'"></td>
                            <td class="whitespace-nowrap px-4 py-4" x-text="formatDate(animal.date_of_acquisition)"></td>
                            <td class="px-4 py-4">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="animal.status === 'Live' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                                    x-text="animal.status || 'Live'"></span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <div class="flex justify-center gap-2">
                                    <button type="button" @click="viewCarabao(animal)"
                                        title="View" class="icon-button hover:bg-blue-50">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                    <button type="button" @click="openEditModal(animal)"
                                        title="Edit" class="icon-button hover:bg-amber-50">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button type="button" @click="deleteCarabao(animal)"
                                        title="Delete" class="icon-button hover:bg-red-50">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <tr x-show="!loading && filteredCarabaos().length === 0">
                        <td colspan="20" class="px-5 py-16 text-center">
                            <i class="fa-solid fa-cow mb-3 text-3xl text-slate-300"></i>
                            <p class="font-semibold">No carabao records found</p>
                            <p class="mt-1 text-sm text-slate-500">Add an animal or adjust your filters.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- PAGINATION --}}
        <div class="flex flex-col gap-3 border-t border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-slate-500">
                Showing
                <span x-text="filteredCarabaos().length ? ((page-1)*perPage)+1 : 0"></span>
                to
                <span x-text="Math.min(page*perPage, filteredCarabaos().length)"></span>
                of <span x-text="filteredCarabaos().length"></span> carabaos
            </p>
            <div class="flex items-center gap-2">
                <button type="button" @click="previousPage()" :disabled="page <= 1"
                    class="rounded-lg border px-3 py-2 text-sm disabled:opacity-40">Previous</button>
                <span class="px-2 text-sm">Page <span x-text="page"></span> of <span x-text="totalPages()"></span></span>
                <button type="button" @click="nextPage()" :disabled="page >= totalPages()"
                    class="rounded-lg border px-3 py-2 text-sm disabled:opacity-40">Next</button>
            </div>
        </div>
    </section>

    <div class="mt-6 flex flex-col gap-2 border-t border-slate-200 pt-4 text-xs text-slate-500 sm:flex-row sm:justify-between">
        <p>CBED Data System · Carabao Management</p>
        <p>Connected to Laravel API</p>
    </div>

    {{-- ADD / EDIT MODAL --}}
    <div x-show="showAddModal" x-cloak
        class="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-slate-950/50 p-3 sm:p-5"
        @keydown.escape.window="showAddModal=false"
        @click.self="showAddModal=false">
        <div class="my-auto flex max-h-[94vh] w-full max-w-[1500px] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
            @click.stop>

            <div class="flex shrink-0 items-center justify-between gap-4 border-b px-6 py-5">
                <div>
                    <h2 class="text-xl font-bold" x-text="editing ? 'Edit Carabao' : 'Add Carabaos' "></h2>
                    <p class="mt-1 text-sm text-slate-500"
                        x-text="editing ? 'Update the animal record.' : 'Select a cooperative and farmer, then choose animals from Carabao Inventory. Animal details are filled automatically.'"></p>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <span x-show="!editing" class="whitespace-nowrap rounded-lg bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-800">
                        <span x-text="bulkRows.length"></span> animal(s)
                    </span>
                    <button type="button" @click="showAddModal=false" class="flex h-9 w-9 items-center justify-center rounded-lg hover:bg-slate-100">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>
            </div>

            <form @submit.prevent="saveCarabaos()" class="flex min-h-0 flex-1 flex-col">
                <div class="min-h-0 flex-1 overflow-auto p-5 sm:p-6">
                    {{-- Disable Add controls while editing or while the modal is closed. --}}
                    <fieldset :disabled="!showAddModal || editing" class="contents">
                    {{-- SHARED COOPERATIVE / FARMER --}}
                    <div x-show="!editing" class="mb-6 rounded-xl border border-blue-100 bg-blue-50/50 p-4">
                        <h3 class="mb-4 font-bold text-slate-900">Cooperative and Farmer</h3>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <label class="field-label">Cooperative / Association *</label>
                                <select class="field-input" x-model="addSelection.cooperative_id" @change="onAddCooperativeChange()" required>
                                    <option value="">Select cooperative</option>
                                    <template x-for="coop in cooperatives" :key="coop.id">
                                        <option :value="String(coop.id)" x-text="coop.cooperative_name"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="field-label">Farmer / Owner *</label>
                                <select class="field-input" x-model="addSelection.farmer_id" @change="onAddFarmerChange()" :disabled="!addSelection.cooperative_id" required>
                                    <option value="">Select farmer</option>
                                    <template x-for="farmer in farmersForCooperative(addSelection.cooperative_id)" :key="farmer.id">
                                        <option :value="String(farmer.id)" x-text="farmerNameOption(farmer)"></option>
                                    </template>
                                </select>
                                <p x-show="addSelection.cooperative_id && !farmersForCooperative(addSelection.cooperative_id).length" class="mt-1 text-xs text-amber-700">No farmers are linked to this cooperative.</p>
                            </div>
                            <div>
                                <label class="field-label">Province</label>
                                <input class="field-input bg-slate-100" x-model="addSelection.province" readonly placeholder="Auto-filled from farmer">
                            </div>
                            <div>
                                <label class="field-label">Municipality</label>
                                <input class="field-input bg-slate-100" x-model="addSelection.municipality" readonly placeholder="Auto-filled from farmer">
                            </div>
                            <div>
                                <label class="field-label">Barangay</label>
                                <input class="field-input bg-slate-100" x-model="addSelection.barangay" readonly placeholder="Auto-filled from farmer">
                            </div>
                        </div>
                    </div>

                    {{-- MULTIPLE ANIMALS --}}
                    <div x-show="!editing">
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="font-bold">Animal Records</h3>
                                <p class="mt-1 text-sm text-slate-500">Choose an Animal ID from Carabao Inventory. Available inventory details will fill automatically. Enter ownership and entrustment separately for every animal.</p>
                            </div>
                            <button type="button" @click="addRow()" class="shrink-0 rounded-lg bg-[#0b1f3a] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#122b4d]">
                                <i class="fa-solid fa-plus mr-1"></i> Add Animal
                            </button>
                        </div>

                        <div x-show="inventoryLoading" class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">
                            <i class="fa-solid fa-spinner fa-spin mr-2"></i>Loading Carabao Inventory...
                        </div>
                        <div x-show="!inventoryLoading && !inventoryError && availableInventoryAnimals().length === 0" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                            No unused inventory animals are available. Check the Carabao Inventory page and refresh this page.
                        </div>
                        <div x-show="inventoryError" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800" x-text="inventoryError"></div>

                        <div class="space-y-5">
                            <template x-for="(row, index) in bulkRows" :key="row.rowId">
                                <div class="overflow-hidden rounded-xl border border-slate-200">
                                    <div class="flex items-center justify-between border-b bg-slate-50 px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#0b1f3a] text-sm font-bold text-white" x-text="index + 1"></span>
                                            <div>
                                                <p class="font-bold" x-text="row.animal_id || 'Select an animal' "></p>
                                                <p class="text-xs text-slate-500">Animal details from inventory</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="removeRow(index)" :disabled="bulkRows.length <= 1" class="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-600 disabled:opacity-40">
                                            <i class="fa-solid fa-trash mr-1"></i> Remove
                                        </button>
                                    </div>
                                    <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
                                        <div class="sm:col-span-2">
                                            <label class="field-label">Animal ID / Registry Number *</label>
                                            <input
                                                type="search"
                                                class="field-input mb-2"
                                                x-model="row.inventory_search"
                                                placeholder="Search registry number or bolus..."
                                                autocomplete="off"
                                                aria-label="Search Animal ID or Registry Number"
                                            >
                                            <select class="field-input" x-model="row.inventory_id" @change="onInventoryAnimalChange(row)" required>
                                                <option value="">Select from Carabao Inventory</option>
                                                <template x-for="animal in filteredInventoryAnimals(row)" :key="animal.id || animal.registry_number">
                                                    <option :value="String(animal.id || animal.registry_number || animal.animal_id)" x-text="(animal.registry_number || animal.animal_id || 'No registry number') + (animal.bolus_id ? ' — Bolus: ' + animal.bolus_id : '')"></option>
                                                </template>
                                            </select>
                                            <p class="mt-1 text-xs text-slate-500" x-text="filteredInventoryAnimals(row).length + ' matching inventory animal(s)' "></p>
                                        </div>
                                        <div>
                                            <label class="field-label">Bolus</label>
                                            <input class="field-input bg-slate-100" x-model="row.bolus" readonly>
                                        </div>
                                        <div>
                                            <label class="field-label">Tag No.</label>
                                            <input class="field-input" x-model.trim="row.tag_no" maxlength="100" placeholder="Enter tag number">
                                        </div>
                                        <div>
                                            <label class="field-label">Sire ID</label>
                                            <input class="field-input" x-model.trim="row.sire_id" maxlength="100" placeholder="Enter sire ID">
                                        </div>
                                        <div>
                                            <label class="field-label">Sire Name</label>
                                            <input class="field-input" x-model.trim="row.sire_name" maxlength="150" placeholder="Enter sire name">
                                        </div>
                                        <div>
                                            <label class="field-label">Dam ID</label>
                                            <input class="field-input" x-model.trim="row.dam_id" maxlength="100" placeholder="Enter dam ID">
                                        </div>
                                        <div>
                                            <label class="field-label">Dam Name</label>
                                            <input class="field-input" x-model.trim="row.dam_name" maxlength="150" placeholder="Enter dam name">
                                        </div>
                                        <div>
                                            <label class="field-label">Parity</label>
                                            <input class="field-input" type="number" min="0" step="1" x-model="row.parity" placeholder="Enter parity">
                                        </div>
                                        <div>
                                            <label class="field-label">Sex</label>
                                            <input class="field-input bg-slate-100" x-model="row.sex" readonly>
                                        </div>
                                        <div>
                                            <label class="field-label">Birthday</label>
                                            <input class="field-input bg-slate-100" x-model="row.birthday" readonly>
                                        </div>
                                        <div>
                                            <label class="field-label">Breed</label>
                                            <input class="field-input bg-slate-100" x-model="row.breed" readonly>
                                        </div>
                                        <div>
                                            <label class="field-label">Birth Place</label>
                                            <input class="field-input bg-slate-100" x-model="row.birth_place" readonly>
                                        </div>
                                        <div>
                                            <label class="field-label">Breeder</label>
                                            <input class="field-input bg-slate-100" x-model="row.breeder" readonly>
                                        </div>
                                        <div>
                                            <label class="field-label">Ownership Type *</label>
                                            <select class="field-input" x-model="row.ownership_type" required>
                                                <option value="">Select ownership</option>
                                                <option value="Farmer">Farmer</option>
                                                <option value="PCC">PCC</option>
                                                <option value="Coop-Owned">Coop-Owned</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="field-label">Type of Entrustment *</label>
                                            <select class="field-input" x-model="row.type_of_entrustment" required>
                                                <option value="">Select type</option>
                                                <template x-for="item in entrustmentOptions" :key="item"><option :value="item" x-text="item"></option></template>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="field-label">Date of Acquisition</label>
                                            <input class="field-input" type="date" x-model="row.date_of_acquisition">
                                        </div>
                                        <div>
                                            <label class="field-label">Beginning Year</label>
                                            <input class="field-input" type="number" min="1901" max="2155" x-model="row.beginning_year">
                                        </div>
                                        <div>
                                            <label class="field-label">Status *</label>
                                            <select class="field-input" x-model="row.status" required>
                                                <option value="Live">Live</option><option value="Out">Out</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    </fieldset>

                    {{-- Disable Edit controls when Add mode is active or the modal is closed. --}}
                    <fieldset :disabled="!showAddModal || !editing" class="contents">
                    {{-- EDIT EXISTING CARABAO --}}
                    <div x-show="editing" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div><label class="field-label">Animal ID *</label><input class="field-input" x-model.trim="form.animal_id" required maxlength="100"></div>
                        <div><label class="field-label">Bolus</label><input class="field-input" x-model.trim="form.bolus"></div>
                        <div><label class="field-label">Tag No.</label><input class="field-input" x-model.trim="form.tag_no" maxlength="100" placeholder="Enter tag number"></div>
                        <div><label class="field-label">Sire ID</label><input class="field-input" x-model.trim="form.sire_id" maxlength="100" placeholder="Enter sire ID"></div>
                        <div><label class="field-label">Sire Name</label><input class="field-input" x-model.trim="form.sire_name" maxlength="150" placeholder="Enter sire name"></div>
                        <div><label class="field-label">Dam ID</label><input class="field-input" x-model.trim="form.dam_id" maxlength="100" placeholder="Enter dam ID"></div>
                        <div><label class="field-label">Dam Name</label><input class="field-input" x-model.trim="form.dam_name" maxlength="150" placeholder="Enter dam name"></div>
                        <div><label class="field-label">Parity</label><input class="field-input" type="number" min="0" step="1" x-model="form.parity" placeholder="Enter parity"></div>
                        <div>
                            <label class="field-label">Cooperative / Association *</label>
                            <select class="field-input" x-model="form.cooperative_id" @change="onCooperativeChange(form)" required>
                                <option value="">Select cooperative</option><template x-for="coop in cooperatives" :key="coop.id"><option :value="String(coop.id)" x-text="coop.cooperative_name"></option></template>
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Farmer / Owner *</label>
                            <select class="field-input" x-model="form.farmer_id" @change="onFarmerChange(form)" :disabled="!form.cooperative_id" required>
                                <option value="">Select farmer</option><template x-for="farmer in farmersForCooperative(form.cooperative_id)" :key="farmer.id"><option :value="String(farmer.id)" x-text="farmerNameOption(farmer)"></option></template>
                            </select>
                        </div>
                        <div><label class="field-label">Province</label><input class="field-input bg-slate-100" x-model="form.province" readonly></div>
                        <div><label class="field-label">Municipality</label><input class="field-input bg-slate-100" x-model="form.municipality" readonly></div>
                        <div><label class="field-label">Barangay</label><input class="field-input bg-slate-100" x-model="form.barangay" readonly></div>
                        <div><label class="field-label">Sex *</label><select class="field-input" x-model="form.sex" required><option value="Female">Female</option><option value="Male">Male</option></select></div>
                        <div><label class="field-label">Birthday</label><input class="field-input" type="date" x-model="form.birthday"></div>
                        <div><label class="field-label">Breed</label><input class="field-input" x-model.trim="form.breed"></div>
                        <div><label class="field-label">Classification</label><input class="field-input bg-slate-100" :value="getClassification(form.sex, form.birthday)" readonly></div>
                        <div><label class="field-label">Birth Place</label><input class="field-input" x-model.trim="form.birth_place"></div>
                        <div><label class="field-label">Breeder</label><input class="field-input" x-model.trim="form.breeder"></div>
                        <div><label class="field-label">Ownership Type</label><select class="field-input" x-model="form.ownership_type"><option value="">Select ownership</option><option value="Farmer">Farmer</option><option value="PCC">PCC</option><option value="Coop-Owned">Coop-Owned</option></select></div>
                        <div><label class="field-label">Type of Entrustment</label><select class="field-input" x-model="form.type_of_entrustment"><option value="">Select type</option><template x-for="item in entrustmentOptions" :key="item"><option :value="item" x-text="item"></option></template></select></div>
                        <div><label class="field-label">Date of Acquisition</label><input class="field-input" type="date" x-model="form.date_of_acquisition"></div>
                        <div><label class="field-label">Beginning Year</label><input class="field-input" type="number" min="1901" max="2155" x-model="form.beginning_year"></div>
                        <div><label class="field-label">Status</label><select class="field-input" x-model="form.status"><option value="Live">Live</option><option value="Out">Out</option></select></div>
                    </div>
                    </fieldset>
                </div>

                <div class="flex shrink-0 flex-col-reverse gap-3 border-t bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end">
                    <button type="button" @click="showAddModal=false" class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold">Cancel</button>
                    <button type="submit" :disabled="saving" class="rounded-lg bg-[#0b1f3a] px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">
                        <i class="fa-solid fa-floppy-disk mr-2"></i><span x-text="saving ? 'Saving...' : (editing ? 'Update Carabao' : 'Save Carabaos')"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- DETAILS MODAL --}}
    <div x-show="showDetailModal" x-cloak
        class="fixed inset-0 z-[110] flex items-center justify-center overflow-y-auto bg-slate-950/50 p-4"
        @keydown.escape.window="showDetailModal=false"
        @click.self="showDetailModal=false">
        <div class="my-6 w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl" @click.stop>
            <div class="flex items-center justify-between border-b px-6 py-5">
                <div>
                    <h2 class="text-xl font-bold">Carabao Details</h2>
                    <p class="mt-1 text-sm text-slate-500">Animal identification and inventory information.</p>
                </div>
                <button type="button" @click="showDetailModal=false" class="icon-button">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div x-show="selectedCarabao" class="max-h-[70vh] overflow-y-auto p-6">
                <div class="mb-6 flex items-center gap-4 rounded-xl bg-slate-50 p-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-blue-100 text-blue-800">
                        <i class="fa-solid fa-cow text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold" x-text="selectedCarabao?.animal_id"></h3>
                        <p class="mt-1 text-sm text-slate-500" x-text="farmerName(selectedCarabao || {})"></p>
                    </div>
                    <span class="ml-auto rounded-full px-3 py-1 text-xs font-semibold"
                        :class="selectedCarabao?.status === 'Live' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'"
                        x-text="selectedCarabao?.status || 'Live'"></span>
                </div>

                <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                    <template x-for="item in detailFields()" :key="item.label">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500" x-text="item.label"></p>
                            <p class="mt-1 break-words text-sm font-medium" x-text="item.value || '—'"></p>
                        </div>
                    </template>
                </div>
            </div>

            <div class="flex justify-end border-t bg-slate-50 px-6 py-4">
                <button type="button" @click="showDetailModal=false"
                    class="rounded-lg bg-[#0b1f3a] px-5 py-2.5 text-sm font-semibold text-white">
                    Close
                </button>
            </div>
        </div>
    </div>

    {{-- TOAST --}}
    <div x-show="toast.show" x-cloak x-transition
        class="fixed bottom-5 right-5 z-[200] flex max-w-sm items-start gap-3 rounded-xl border bg-white p-4 shadow-xl">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
            :class="toast.type === 'error' ? 'bg-red-100 text-red-600' : 'bg-emerald-100 text-emerald-600'">
            <i :class="toast.type === 'error' ? 'fa-solid fa-circle-exclamation' : 'fa-solid fa-circle-check'"></i>
        </div>
        <div class="min-w-0 flex-1">
            <p class="font-bold" x-text="toast.type === 'error' ? 'Unable to complete' : 'Notice'"></p>
            <p class="mt-1 text-sm text-slate-600" x-text="toast.message"></p>
        </div>
        <button type="button" @click="toast.show=false"><i class="fa-solid fa-xmark"></i></button>
    </div>
</div>
@endsection

@push('styles')
<style>
    [x-cloak] { display: none !important; }
    .field-label {
        display:block;
        margin-bottom:.375rem;
        font-size:.75rem;
        font-weight:700;
        text-transform:uppercase;
        letter-spacing:.04em;
        color:#64748b;
    }
    .field-input {
        width:100%;
        border:1px solid #cbd5e1;
        border-radius:.5rem;
        background:#fff;
        padding:.625rem .75rem;
        font-size:.875rem;
        outline:none;
    }
    .field-input:focus {
        border-color:#3b82f6;
        box-shadow:0 0 0 2px #dbeafe;
    }
    .field-input:disabled {
        cursor:not-allowed;
        background:#f1f5f9;
        color:#94a3b8;
    }
    .icon-button {
        display:inline-flex;
        width:2.25rem;
        height:2.25rem;
        align-items:center;
        justify-content:center;
        border:1px solid #e2e8f0;
        border-radius:.5rem;
    }
</style>
@endpush



@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.4/jspdf.plugin.autotable.min.js"></script>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('carabaoPage', () => ({
        carabaos: [],
        cooperatives: [],
        farmers: [],

        loading: false,
        saving: false,
        loadError: '',
        page: 1,
        perPage: 10,

        showAddModal: false,
        showDetailModal: false,
        editing: false,
        editingRecordId: null,
        selectedCarabao: null,

        bulkRows: [],
        inventoryAnimals: [],
        inventoryLoading: false,
        inventoryError: '',
        addSelection: { cooperative_id: '', farmer_id: '', province: '', municipality: '', barangay: '' },
        nextRowId: 1,
        form: {},
        toastTimer: null,

        toast: {
            show: false,
            message: '',
            type: 'success'
        },

        filters: {
            search: '',
            province: '',
            breed: '',
            sex: '',
            classification: '',
            status: '',
            entrustment: ''
        },

        classificationOptions: [
            'Female Calf',
            'Male Calf',
            'Yearling Heifer',
            'Yearling Bull',
            'Heifer',
            'Heifer Pregnant',
            'Cow Dry',
            'Cow Dry Pregnant',
            'Cow Lactating',
            'Cow Lactating Pregnant',
            'Junior Bull',
            'Senior Bull'
        ],

        entrustmentOptions: [
            'Pamamahagi',
            'CBED',
            'CBIN',
            'CHBU'
        ],

        urls: {
            index: @json(route('admin.carabaos.index')),
            options: @json(route('admin.carabaos.options')),
            store: @json(route('admin.carabaos.store')),
            bulk: @json(route('admin.carabaos.bulk')),
            import: @json(route('admin.carabaos.import')),
            updateBase: @json(url('admin/carabaos')),
            deleteBase: @json(url('admin/carabaos')),
            inventoryBase: @json(url('admin/carabao-inventory-api'))
        },

        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',

        async init() {
            this.loading = true;
            this.loadError = '';

            try {
                await this.loadOptions();
                await this.loadCarabaos();
                await this.loadInventoryAnimals();
            } catch (error) {
                this.loadError = error.message || 'Unable to load data.';
                this.notify(this.loadError, 'error');
            } finally {
                this.loading = false;
            }
        },

        async request(url, options = {}) {
            const headers = {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.csrfToken,
                ...(options.headers || {})
            };

            const response = await fetch(url, {
                ...options,
                headers,
                credentials: 'same-origin'
            });

            let data = {};

            try {
                data = await response.json();
            } catch (error) {
                // Response may not contain JSON.
            }

            if (!response.ok) {
                let message = data.message || `Request failed (${response.status}).`;

                if (data.errors) {
                    message = Object.values(data.errors).flat().join(' ');
                }

                throw new Error(message);
            }

            return data;
        },

        async loadOptions() {
            const data = await this.request(this.urls.options);

            this.cooperatives = data.cooperatives || [];
            this.farmers = data.farmers || [];
        },

        async loadCarabaos() {
            const data = await this.request(this.urls.index);
            const records = Array.isArray(data) ? data : (data.data || []);

            this.carabaos = records.map((record, index) => ({
                ...record,
                _key: record.id ?? record.animal_id ?? index
            }));

            this.page = Math.min(this.page, this.totalPages());
        },

                downloadImportTemplate() {
            const headers = [
                'animal_id',
                'bolus',
                'tag_no',
                'sire_id',
                'sire_name',
                'dam_id',
                'dam_name',
                'parity',
                'farmer_id',
                'cooperative_id',
                'province',
                'municipality',
                'barangay',
                'sex',
                'breed',
                'classification',
                'birth_place',
                'breeder',
                'type_of_entrustment',
                'status',
                'animal_source'
            ];

            const sampleRow = [
                'CBED-0001',
                '123456789',
                'TAG-001',
                'SIRE-001',
                'Sample Sire',
                'DAM-001',
                'Sample Dam',
                '2',
                '',
                '',
                'Pampanga',
                'Lubao',
                'Sample Barangay',
                'Female',
                'Philippine Carabao',
                'Cow Lactating',
                'Farm Born',
                'CBED',
                'CBED',
                'Live',
                'CBED'
            ];

            const escapeCSV = value =>
                `"${String(value ?? '').replace(/"/g, '""')}"`;

            const csvContent = [
                headers.map(escapeCSV).join(','),
                sampleRow.map(escapeCSV).join(',')
            ].join('\r\n');

            const blob = new Blob(['\uFEFF' + csvContent], {
                type: 'text/csv;charset=utf-8;'
            });

            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');

            link.href = url;
            link.download = 'CBED_Carabao_Import_Template.csv';

            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            URL.revokeObjectURL(url);

            this.notify('Import template downloaded successfully.', 'success');
        },

        blankAnimal() {
            return {
                animal_id: '',
                bolus: '',
                tag_no: '',
                sire_id: '',
                sire_name: '',
                dam_id: '',
                dam_name: '',
                parity: '',
                farmer_id: '',
                cooperative_id: '',
                province: '',
                municipality: '',
                barangay: '',
                inventory_id: '',
                inventory_search: '',
                sex: '',
                birthday: '',
                breed: '',
                classification: '',
                birth_place: '',
                breeder: '',
                ownership_type: '',
                type_of_entrustment: '',
                animal_source: '',
                date_of_acquisition: '',
                beginning_year: new Date().getFullYear(),
                status: 'Live'
            };
        },

        // FARMER AND COOPERATIVE DROPDOWNS

        farmerNameOption(farmer) {
            if (!farmer) return '';

            return [
                farmer.first_name,
                farmer.middle_name,
                farmer.last_name
            ].filter(Boolean).join(' ') || `Farmer ${farmer.id}`;
        },

        farmerCooperativeId(farmer) {
            // Support common API response shapes for the farmer's cooperative.
            return farmer?.cooperative_id
                ?? farmer?.coop_id
                ?? farmer?.cooperativeId
                ?? farmer?.cooperative?.id
                ?? '';
        },

        farmersForCooperative(cooperativeId) {
            if (!cooperativeId) return [];

            const selectedCooperative = this.cooperatives.find(item =>
                String(item.id) === String(cooperativeId)
            );
            const selectedName = String(selectedCooperative?.cooperative_name || '')
                .trim().toLowerCase();

            return this.farmers.filter(farmer => {
                const farmerCoopId = this.farmerCooperativeId(farmer);
                if (farmerCoopId !== '' && farmerCoopId !== null) {
                    return String(farmerCoopId) === String(cooperativeId);
                }

                // Fallback only when the API does not return a cooperative ID.
                const farmerCoopName = String(
                    farmer?.cooperative_name
                    ?? farmer?.coop_name
                    ?? farmer?.cooperative?.cooperative_name
                    ?? ''
                ).trim().toLowerCase();

                return !!selectedName && farmerCoopName === selectedName;
            });
        },

        cooperativeProvince(cooperativeId) {
            const cooperative = this.cooperatives.find(item =>
                String(item.id) === String(cooperativeId)
            );
            return cooperative?.province
                || cooperative?.province_name
                || cooperative?.address_province
                || '';
        },

        farmerProvince(farmer) {
            if (!farmer) return '';

            // Prefer the selected farmer's province, supporting common API aliases.
            const ownProvince = farmer.province
                || farmer.province_name
                || farmer.address_province
                || farmer.farmer_province
                || farmer.address?.province
                || farmer.cooperative?.province
                || '';

            return ownProvince || this.cooperativeProvince(this.farmerCooperativeId(farmer));
        },

        farmerMunicipality(farmer) {
            return farmer?.municipality || farmer?.municipality_name || farmer?.city_municipality || farmer?.municipality_city || farmer?.city || farmer?.address?.municipality || '';
        },

        farmerBarangay(farmer) {
            return farmer?.barangay || farmer?.barangay_name || farmer?.brgy || farmer?.address?.barangay || '';
        },

        async loadInventoryAnimals() {
            this.inventoryLoading = true;
            this.inventoryError = '';
            try {
                const response = await fetch('/admin/carabao-inventory-api', {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                if (!response.ok) throw new Error('Inventory API returned HTTP ' + response.status + '.');
                const payload = await response.json();
                const rows = Array.isArray(payload) ? payload : (payload.data || payload.records || []);
                if (!Array.isArray(rows)) throw new Error('Inventory API did not return a list of animals.');
                this.inventoryAnimals = rows.filter(item => !item.nature_of_exit || String(item.nature_of_exit).trim() === '');
            } catch (error) {
                this.inventoryAnimals = [];
                this.inventoryError = 'Unable to load Carabao Inventory: ' + (error.message || 'Unknown error');
            } finally {
                this.inventoryLoading = false;
            }
        },

        availableInventoryAnimals(currentInventoryId = '') {
            const alreadyAssignedRegistryNumbers = new Set(
                this.carabaos.map(a => String(a.animal_id || '').trim().toLowerCase()).filter(Boolean)
            );
            const selectedInventoryKeys = new Set(
                this.bulkRows
                    .filter(row => String(row.inventory_id || '') !== String(currentInventoryId || ''))
                    .map(row => String(row.inventory_id || '').trim().toLowerCase())
                    .filter(Boolean)
            );

            return this.inventoryAnimals.filter(animal => {
                const inventoryKey = String(animal.id || animal.registry_number || animal.animal_id || '').trim();
                const registryNumber = String(animal.registry_number || animal.animal_id || '').trim().toLowerCase();
                if (!inventoryKey || !registryNumber) return false;
                if (alreadyAssignedRegistryNumbers.has(registryNumber)) return false;
                return !selectedInventoryKeys.has(inventoryKey.toLowerCase())
                    || inventoryKey === String(currentInventoryId || '');
            });
        },

        filteredInventoryAnimals(row) {
            const term = String(row.inventory_search || '').trim().toLowerCase();
            const animals = this.availableInventoryAnimals(row.inventory_id);
            if (!term) return animals;

            return animals.filter(animal => {
                const searchable = [
                    animal.registry_number,
                    animal.animal_id,
                    animal.bolus_id,
                    animal.bolus,
                    animal.id
                ].filter(value => value !== null && value !== undefined)
                 .join(' ')
                 .toLowerCase();
                return searchable.includes(term);
            });
        },

        onInventoryAnimalChange(row) {
            const animal = this.inventoryAnimals.find(a => String(a.id || a.registry_number || a.animal_id || '') === String(row.inventory_id));
            if (!animal) {
                row.animal_id = row.bolus = row.sex = row.birthday = row.breed = row.birth_place = row.breeder = '';
                row.inventory_record_id = '';
                return;
            }
            row.inventory_record_id = animal.id ?? '';
            row.animal_id = String(animal.registry_number || animal.animal_id || '');
            row.bolus = animal.bolus_id || animal.bolus || '';
            row.sex = animal.sex || '';
            row.birthday = this.dateValue(animal.birthday);
            row.breed = animal.breed || '';
            row.birth_place = animal.birthday_place || animal.birth_place || '';
            row.breeder = animal.breeder || '';
            row.animal_source = animal.animal_source || '';
        },

        onAddCooperativeChange() {
            this.addSelection.farmer_id = '';
            this.addSelection.province = '';
            this.addSelection.municipality = '';
            this.addSelection.barangay = '';
            this.bulkRows.forEach(row => {
                row.cooperative_id = this.addSelection.cooperative_id;
                row.farmer_id = '';
                row.province = row.municipality = row.barangay = '';
            });
        },

        onAddFarmerChange() {
            const farmer = this.farmers.find(item => String(item.id) === String(this.addSelection.farmer_id));
            this.addSelection.province = farmer ? (this.farmerProvince(farmer) || this.cooperativeProvince(this.addSelection.cooperative_id)) : '';
            this.addSelection.municipality = farmer ? this.farmerMunicipality(farmer) : '';
            this.addSelection.barangay = farmer ? this.farmerBarangay(farmer) : '';
            this.bulkRows.forEach(row => {
                row.cooperative_id = this.addSelection.cooperative_id;
                row.farmer_id = this.addSelection.farmer_id;
                row.province = this.addSelection.province;
                row.municipality = this.addSelection.municipality;
                row.barangay = this.addSelection.barangay;
            });
        },

        onCooperativeChange(row) {
            row.farmer_id = '';
            row.province = '';
            const cooperative = this.cooperatives.find(item =>
                String(item.id) === String(row.cooperative_id)
            );
            row.cooperative = cooperative?.cooperative_name || '';
            row.farmer = '';
        },

        onFarmerChange(row) {
            const farmer = this.farmers.find(item =>
                String(item.id) === String(row.farmer_id)
            );
            row.farmer = farmer ? this.farmerNameOption(farmer) : '';
            row.province = farmer
                ? (this.farmerProvince(farmer) || this.cooperativeProvince(row.cooperative_id))
                : '';
            row.municipality = farmer ? this.farmerMunicipality(farmer) : '';
            row.barangay = farmer ? this.farmerBarangay(farmer) : '';
        },

        farmerName(animal) {
            if (!animal) return '—';

            if (animal.farmer && typeof animal.farmer === 'object') {
                return this.farmerNameOption(animal.farmer);
            }

            if (animal.farmer_name) return animal.farmer_name;

            if (typeof animal.farmer === 'string' && animal.farmer) {
                return animal.farmer;
            }

            const farmer = this.farmers.find(item =>
                String(item.id) === String(animal.farmer_id)
            );

            return farmer ? this.farmerNameOption(farmer) : '—';
        },

        cooperativeName(animal) {
            if (!animal) return '—';

            if (animal.cooperative && typeof animal.cooperative === 'object') {
                return animal.cooperative.cooperative_name
                    || animal.cooperative.name
                    || '—';
            }

            if (animal.cooperative_name) return animal.cooperative_name;

            if (typeof animal.cooperative === 'string' && animal.cooperative) {
                return animal.cooperative;
            }

            const cooperative = this.cooperatives.find(item =>
                String(item.id) === String(animal.cooperative_id)
            );

            return cooperative?.cooperative_name || '—';
        },

        provinceName(animal) {
            if (!animal) return 'Unspecified';

            const cooperative = this.cooperatives.find(item =>
                String(item.id) === String(animal.cooperative_id)
            );

            return animal.province
                || animal.cooperative?.province
                || cooperative?.province
                || this.provinceFromBirthPlace(animal.birth_place)
                || 'Unspecified';
        },

        provinceFromBirthPlace(place) {
            if (!place) return '';

            const parts = String(place)
                .split(',')
                .map(value => value.trim())
                .filter(Boolean);

            return parts.length > 1 ? parts[parts.length - 1] : '';
        },

        // SUMMARY CARDS

        summaryCards() {
            const live = this.carabaos.filter(
                animal => (animal.status || 'Live') === 'Live'
            );

            const female = this.carabaos.filter(
                animal => animal.sex === 'Female'
            ).length;

            const male = this.carabaos.filter(
                animal => animal.sex === 'Male'
            ).length;

            const breedable = live.filter(animal =>
                ['Heifer', 'Cow Dry', 'Cow Lactating'].includes(animal.classification)
            ).length;

            const lactating = live.filter(animal =>
                ['Cow Lactating', 'Cow Lactating Pregnant']
                    .includes(animal.classification)
            ).length;

            const pregnant = live.filter(animal =>
                ['Cow Dry Pregnant', 'Heifer Pregnant', 'Cow Lactating Pregnant']
                    .includes(animal.classification)
            ).length;

            return [
                {
                    label: 'Total Carabaos',
                    value: this.carabaos.length,
                    description: 'All inventory records',
                    icon: 'fa-solid fa-cow',
                    iconBg: 'bg-blue-50',
                    iconColor: 'text-blue-700'
                },
                {
                    label: 'Live Animals',
                    value: live.length,
                    description: 'Currently marked live',
                    icon: 'fa-solid fa-heart-pulse',
                    iconBg: 'bg-emerald-50',
                    iconColor: 'text-emerald-700'
                },
                {
                    label: 'Female Animals',
                    value: female,
                    description: 'Female records',
                    icon: 'fa-solid fa-venus',
                    iconBg: 'bg-pink-50',
                    iconColor: 'text-pink-700'
                },
                {
                    label: 'Male Animals',
                    value: male,
                    description: 'Male records',
                    icon: 'fa-solid fa-mars',
                    iconBg: 'bg-indigo-50',
                    iconColor: 'text-indigo-700'
                },
                {
                    label: 'Breedable',
                    value: breedable,
                    description: 'Based on animal classification',
                    icon: 'fa-solid fa-venus-mars',
                    iconBg: 'bg-violet-50',
                    iconColor: 'text-violet-700'
                },
                {
                    label: 'Lactating',
                    value: lactating,
                    description: 'Lactating classifications',
                    icon: 'fa-solid fa-droplet',
                    iconBg: 'bg-cyan-50',
                    iconColor: 'text-cyan-700'
                },
                {
                    label: 'Pregnant',
                    value: pregnant,
                    description: 'Pregnant classifications',
                    icon: 'fa-solid fa-heart',
                    iconBg: 'bg-rose-50',
                    iconColor: 'text-rose-700'
                }
            ];
        },

        provinceBreakdown() {
            const counts = {};

            this.carabaos.forEach(animal => {
                const name = this.provinceName(animal);
                counts[name] = (counts[name] || 0) + 1;
            });

            return Object.entries(counts)
                .map(([name, count]) => ({ name, count }))
                .sort((a, b) => b.count - a.count || a.name.localeCompare(b.name));
        },

        breedBreakdown() {
            const counts = {};

            this.carabaos.forEach(animal => {
                const name = String(animal.breed || 'Unspecified').trim()
                    || 'Unspecified';

                counts[name] = (counts[name] || 0) + 1;
            });

            return Object.entries(counts)
                .map(([name, count]) => ({ name, count }))
                .sort((a, b) => b.count - a.count || a.name.localeCompare(b.name));
        },

        provinceOptions() {
            return [...new Set(
                this.carabaos
                    .map(animal => this.provinceName(animal))
                    .filter(name => name !== 'Unspecified')
            )].sort();
        },

        breedOptions() {
            return [...new Set(
                this.carabaos
                    .map(animal => String(animal.breed || '').trim())
                    .filter(Boolean)
            )].sort();
        },

        // SEARCH, FILTERS AND PAGINATION

        filteredCarabaos() {
            const search = String(this.filters.search || '')
                .toLowerCase()
                .trim();

            return this.carabaos.filter(animal => {
                const province = this.provinceName(animal);
                const farmer = this.farmerName(animal);
                const cooperative = this.cooperativeName(animal);

                const haystack = [
                    animal.animal_id,
                    animal.bolus,
                    farmer,
                    cooperative,
                    province,
                    animal.sex,
                    animal.breed,
                    animal.classification,
                    animal.birth_place,
                    animal.breeder,
                    animal.type_of_entrustment,
                    animal.status,
                    animal.animal_source
                ].join(' ').toLowerCase();

                return (!search || haystack.includes(search))
                    && (!this.filters.province || province === this.filters.province)
                    && (!this.filters.breed || animal.breed === this.filters.breed)
                    && (!this.filters.sex || animal.sex === this.filters.sex)
                    && (!this.filters.classification
                        || animal.classification === this.filters.classification)
                    && (!this.filters.status
                        || (animal.status || 'Live') === this.filters.status)
                    && (!this.filters.entrustment
                        || animal.type_of_entrustment === this.filters.entrustment);
            });
        },

        paginatedCarabaos() {
            const start = (this.page - 1) * this.perPage;
            return this.filteredCarabaos().slice(start, start + this.perPage);
        },

        totalPages() {
            return Math.max(
                1,
                Math.ceil(this.filteredCarabaos().length / this.perPage)
            );
        },

        nextPage() {
            if (this.page < this.totalPages()) this.page++;
        },

        previousPage() {
            if (this.page > 1) this.page--;
        },

        resetFilters() {
            this.filters = {
                search: '',
                province: '',
                breed: '',
                sex: '',
                classification: '',
                status: '',
                entrustment: ''
            };

            this.page = 1;
        },

        // AGE AND CLASSIFICATION

        calculateAge(birthday) {
            if (!birthday) return '—';

            const birth = new Date(String(birthday).slice(0, 10) + 'T00:00:00');
            const today = new Date();

            if (Number.isNaN(birth.getTime()) || birth > today) return '—';

            let years = today.getFullYear() - birth.getFullYear();
            let months = today.getMonth() - birth.getMonth();

            if (today.getDate() < birth.getDate()) months--;

            if (months < 0) {
                years--;
                months += 12;
            }

            if (years === 0 && months === 0) return 'Less than 1 month';

            if (years === 0) {
                return months + (months === 1 ? ' month' : ' months');
            }

            return years + (years === 1 ? ' year ' : ' years ')
                + months + (months === 1 ? ' month' : ' months');
        },

        getClassification(sex, birthday) {
            if (!sex || !birthday) return '';

            const parts = String(birthday).slice(0, 10).split('-');
            if (parts.length !== 3) return '';

            const year = Number(parts[0]);
            const month = Number(parts[1]) - 1;
            const day = Number(parts[2]);
            const birth = new Date(year, month, day);
            const today = new Date();

            if (
                birth.getFullYear() !== year
                || birth.getMonth() !== month
                || birth.getDate() !== day
                || birth > new Date(today.getFullYear(), today.getMonth(), today.getDate())
            ) {
                return '';
            }

            let age = today.getFullYear() - year;

            if (
                today.getMonth() < month
                || (today.getMonth() === month && today.getDate() < day)
            ) {
                age--;
            }

            if (sex === 'Female') {
                if (age < 1) return 'Female Calf';
                if (age < 2) return 'Yearling Heifer';
                return 'Heifer';
            }

            if (sex === 'Male') {
                if (age < 1) return 'Male Calf';
                if (age < 2) return 'Yearling Bull';
                if (age < 4) return 'Junior Bull';
                return 'Senior Bull';
            }

            return '';
        },

        formatDate(date) {
            if (!date) return '—';

            const parts = String(date).slice(0, 10).split('-');
            if (parts.length !== 3) return date;

            const months = [
                'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
            ];

            return (months[Number(parts[1]) - 1] || parts[1])
                + ' ' + Number(parts[2]) + ', ' + parts[0];
        },

        dateValue(value) {
            return value ? String(value).slice(0, 10) : '';
        },

        // ADD AND EDIT MODALS

        newBulkRow() {
            return {
                ...this.blankAnimal(),
                rowId: this.nextRowId++
            };
        },

        openAddModal() {
            this.editing = false;
            this.editingRecordId = null;
            this.form = this.blankAnimal();
            this.addSelection = { cooperative_id: '', farmer_id: '', province: '', municipality: '', barangay: '' };
            this.bulkRows = [this.newBulkRow()];
            this.showAddModal = true;
            this.loadInventoryAnimals();
        },

        addRow() {
            this.bulkRows.push(this.newBulkRow());
        },

        removeRow(index) {
            if (this.bulkRows.length <= 1) {
                this.notify('At least one animal row is required.', 'error');
                return;
            }

            this.bulkRows.splice(index, 1);
        },

        openEditModal(animal) {
            this.editing = true;
            this.editingRecordId = animal.id;

            const farmerId = animal.farmer_id ?? animal.farmer?.id ?? '';
            const cooperativeId = animal.cooperative_id
                ?? animal.cooperative?.id
                ?? '';

            this.form = {
                ...this.blankAnimal(),
                ...animal,
                animal_id: animal.animal_id || '',
                farmer_id: farmerId === null ? '' : String(farmerId),
                cooperative_id: cooperativeId === null ? '' : String(cooperativeId),
                birthday: this.dateValue(animal.birthday),
                date_of_acquisition: this.dateValue(animal.date_of_acquisition),
                province: animal.province || animal.cooperative?.province || '',
                municipality: animal.municipality || '',
                barangay: animal.barangay || '',
                status: animal.status || 'Live'
            };

            if (this.form.cooperative_id) {
                const selectedFarmer = this.farmers.find(item => String(item.id) === String(this.form.farmer_id));
                this.form.province = selectedFarmer ? (this.farmerProvince(selectedFarmer) || this.cooperativeProvince(this.form.cooperative_id)) : (this.form.province || this.cooperativeProvince(this.form.cooperative_id));
            }

            this.bulkRows = [];
            this.showAddModal = true;
        },

        // PAYLOAD AND VALIDATION

        payloadFor(row) {
            return {
                animal_id: String(row.animal_id || '').trim(),
                bolus: row.bolus || null,
                tag_no: row.tag_no || null,
                sire_id: row.sire_id || null,
                sire_name: row.sire_name || null,
                dam_id: row.dam_id || null,
                dam_name: row.dam_name || null,
                parity: row.parity === '' || row.parity === null || row.parity === undefined
                    ? null : Number(row.parity),
                farmer_id: row.farmer_id ? Number(row.farmer_id) : null,
                cooperative_id: row.cooperative_id ? Number(row.cooperative_id) : null,
                province: row.province || null,
                municipality: row.municipality || null,
                barangay: row.barangay || null,
                sex: row.sex || '',
                birthday: row.birthday || null,
                breed: row.breed || null,
                classification: this.getClassification(row.sex, row.birthday) || null,
                birth_place: row.birth_place || null,
                breeder: row.breeder || null,
                ownership_type: row.ownership_type || null,
                type_of_entrustment: row.type_of_entrustment || null,
                animal_source: row.animal_source || null,
                date_of_acquisition: row.date_of_acquisition || null,
                beginning_year: row.beginning_year === '' || row.beginning_year === null
                    ? null : Number(row.beginning_year),
                status: row.status || 'Live'
            };
        },

        inventoryTransferPayload(row) {
            const inventory = this.inventoryAnimals.find(item =>
                String(item.id) === String(row.inventory_record_id || row.inventory_id)
            );
            if (!inventory) {
                throw new Error('The selected animal was not found in Carabao Inventory. Refresh the inventory list and try again.');
            }

            const farmer = this.farmers.find(item =>
                String(item.id) === String(row.farmer_id)
            );
            if (!farmer) throw new Error('The selected farmer was not found.');

            const address = [
                this.farmerBarangay(farmer),
                this.farmerMunicipality(farmer),
                this.farmerProvince(farmer)
            ].filter(value => String(value || '').trim() !== '').join(', ');

            if (!address) {
                throw new Error('The selected farmer has no address recorded. Update the farmer address before saving.');
            }

            const transferDate = row.date_of_acquisition || null;

            return {
                farmer_recipient: String(farmer.id),
                recipient_cooperative_id: Number(row.cooperative_id),
                registry_number: String(row.animal_id || inventory.registry_number || '').trim(),
                bolus_id: row.bolus || inventory.bolus_id || null,
                tag_no: row.tag_no || null,
                sire_id: row.sire_id || null,
                sire_name: row.sire_name || null,
                dam_id: row.dam_id || null,
                dam_name: row.dam_name || null,
                parity: row.parity === '' || row.parity === null || row.parity === undefined
                    ? null : Number(row.parity),
                sex: row.sex || inventory.sex || null,
                birthday: row.birthday || inventory.birthday || null,
                breed: row.breed || inventory.breed || null,
                birthday_place: row.birth_place || inventory.birthday_place || null,
                breeder: row.breeder || inventory.breeder || null,
                association_coop_institution: inventory.association_coop_institution || null,
                brgy: inventory.brgy || null,
                municipality: inventory.municipality || null,
                province: inventory.province || null,
                district: inventory.district || null,
                region: inventory.region || null,
                date_of_acquisition: inventory.date_of_acquisition || null,
                acquisition_cost: inventory.acquisition_cost ?? null,
                nature_of_exit: 'Transferred',
                reason_of_exit: 'TRANSFERRED-ENTRUSTMENT (FARMER)',
                type_of_entrustment: row.type_of_entrustment || null,
                ownership_status: row.ownership_type || null,
                disposed_to: this.farmerNameOption(farmer),
                place_of_disposal: address,
                date_of_disposal_death: transferDate,
                value_of_disposed_animals: inventory.value_of_disposed_animals ?? null,
                remarks: inventory.remarks || null
            };
        },

        validateRow(row) {
            if (!String(row.animal_id || '').trim()) {
                return 'Animal ID is required.';
            }

            if (!row.cooperative_id) return 'Please select a cooperative.';
            if (!row.farmer_id) return 'Please select a farmer for each animal.';
            if (!row.sex) return 'The selected inventory animal has no sex recorded.';
            if (!this.editing && !row.inventory_id) return 'Please select an animal from Carabao Inventory.';
            if (!this.editing && !row.ownership_type) return 'Please select an ownership type for every animal.';
            if (!this.editing && !row.type_of_entrustment) return 'Please select a type of entrustment for every animal.';

            const farmer = this.farmers.find(item =>
                String(item.id) === String(row.farmer_id)
            );

            if (!farmer) return 'The selected farmer was not found.';

            if (String(this.farmerCooperativeId(farmer) || '') !== String(row.cooperative_id) && this.farmerCooperativeId(farmer) !== '') {
                return 'The selected farmer does not belong to the selected cooperative.';
            }

            return '';
        },

        // SAVE SINGLE OR MULTIPLE ANIMALS

        async saveCarabaos() {
            if (this.saving) return;

            this.saving = true;

            try {
                if (this.editing) {
                    const error = this.validateRow(this.form);
                    if (error) throw new Error(error);

                    const url = this.urls.updateBase.replace(/\/$/, '')
                        + '/' + encodeURIComponent(this.editingRecordId);

                    await this.request(url, {
                        method: 'PUT',
                        body: JSON.stringify(this.payloadFor(this.form))
                    });

                    this.notify('Carabao record updated successfully.');
                } else {
                    if (!this.bulkRows.length) {
                        throw new Error('Please add at least one animal row.');
                    }

                    const existingIds = new Set(
                        this.carabaos.map(animal =>
                            String(animal.animal_id || '').toLowerCase()
                        )
                    );

                    const pendingIds = new Set();

                    for (const row of this.bulkRows) {
                        row.cooperative_id = this.addSelection.cooperative_id;
                        row.farmer_id = this.addSelection.farmer_id;
                        row.province = this.addSelection.province;
                        row.municipality = this.addSelection.municipality;
                        row.barangay = this.addSelection.barangay;
                        const error = this.validateRow(row);
                        if (error) throw new Error(error);

                        const id = String(row.animal_id).trim().toLowerCase();

                        if (existingIds.has(id) || pendingIds.has(id)) {
                            throw new Error('Duplicate Animal ID: ' + row.animal_id);
                        }

                        pendingIds.add(id);
                    }

                    // Update the selected existing inventory record instead of creating
                    // a duplicate. The inventory controller then synchronizes the animal
                    // into Carabao Management using the selected farmer and transfer details.
                    let saved = 0;

                    for (const row of this.bulkRows) {
                        const inventoryId = row.inventory_record_id || row.inventory_id;
                        if (!inventoryId) {
                            throw new Error('Select an existing Carabao Inventory animal for every row.');
                        }

                        await this.request(
                            this.urls.inventoryBase.replace(/\/$/, '') + '/' + encodeURIComponent(inventoryId),
                            {
                                method: 'PUT',
                                body: JSON.stringify(this.inventoryTransferPayload(row))
                            }
                        );

                        saved++;
                    }

                    this.notify(saved + ' animal(s) transferred. Carabao Inventory exit details were updated and synchronized with Carabao Management.');
                }

                this.showAddModal = false;
                await this.loadCarabaos();
                this.page = 1;
            } catch (error) {
                this.notify(error.message || 'Unable to save the records.', 'error');
                await this.loadCarabaos().catch(() => {});
            } finally {
                this.saving = false;
            }
        },

        // DETAILS MODAL

        viewCarabao(animal) {
            this.selectedCarabao = animal;
            this.showDetailModal = true;
        },

        detailFields() {
            const animal = this.selectedCarabao;
            if (!animal) return [];

            return [
                { label: 'Animal ID', value: animal.animal_id },
                { label: 'Bolus', value: animal.bolus },
                { label: 'Tag No.', value: animal.tag_no },
                { label: 'Sire ID', value: animal.sire_id },
                { label: 'Sire Name', value: animal.sire_name },
                { label: 'Dam ID', value: animal.dam_id },
                { label: 'Dam Name', value: animal.dam_name },
                { label: 'Parity', value: animal.parity },
                { label: 'Farmer / Owner', value: this.farmerName(animal) },
                { label: 'Cooperative', value: this.cooperativeName(animal) },
                { label: 'Province', value: this.provinceName(animal) },
                { label: 'Sex', value: animal.sex },
                { label: 'Birthday', value: this.formatDate(animal.birthday) },
                { label: 'Age', value: this.calculateAge(animal.birthday) },
                { label: 'Breed', value: animal.breed },
                { label: 'Classification', value: animal.classification },
                { label: 'Birth Place', value: animal.birth_place },
                { label: 'Breeder', value: animal.breeder },
                { label: 'Ownership Type', value: animal.ownership_type },
                { label: 'Type of Entrustment', value: animal.type_of_entrustment },
                { label: 'Animal Source', value: animal.animal_source },
                {
                    label: 'Date of Acquisition',
                    value: this.formatDate(animal.date_of_acquisition)
                },
                { label: 'Beginning Year', value: animal.beginning_year },
                { label: 'Status', value: animal.status },
                { label: 'Remarks', value: animal.remarks }
            ];
        },

        // DELETE ANIMAL

        async deleteCarabao(animal) {
            if (!confirm('Delete animal record ' + animal.animal_id + '?')) {
                return;
            }

            try {
                const url = this.urls.deleteBase.replace(/\/$/, '')
                    + '/' + encodeURIComponent(animal.id);

                await this.request(url, { method: 'DELETE' });
                await this.loadCarabaos();

                this.page = Math.min(this.page, this.totalPages());
                this.notify('Carabao record deleted successfully.');
            } catch (error) {
                this.notify(error.message || 'Unable to delete this record.', 'error');
            }
        },

        // NOTIFICATIONS

        notify(message, type = 'success') {
            this.toast.message = message;
            this.toast.type = type;
            this.toast.show = true;

            if (this.toastTimer) clearTimeout(this.toastTimer);

            this.toastTimer = setTimeout(() => {
                this.toast.show = false;
            }, 4000);
        },

        // CSV EXPORT

        csvHeaders() {
            return [
                'Animal ID', 'Bolus', 'Tag No.', 'Sire ID', 'Sire Name',
                'Dam ID', 'Dam Name', 'Parity', 'Farmer ID', 'Cooperative ID',
                'Province', 'Sex', 'Birthday', 'Breed', 'Classification',
                'Birth Place', 'Breeder', 'Ownership Type',
                'Type of Entrustment', 'Animal Source', 'Date of Acquisition',
                'Beginning Year', 'Status', 'Remarks'
            ];
        },

        csvValues(animal) {
            return [
                animal.animal_id,
                animal.bolus,
                animal.tag_no,
                animal.sire_id,
                animal.sire_name,
                animal.dam_id,
                animal.dam_name,
                animal.parity,
                animal.farmer_id,
                animal.cooperative_id,
                this.provinceName(animal),
                animal.sex,
                this.dateValue(animal.birthday),
                animal.breed,
                animal.classification,
                animal.birth_place,
                animal.breeder,
                animal.ownership_type,
                animal.type_of_entrustment,
                animal.animal_source,
                this.dateValue(animal.date_of_acquisition),
                animal.beginning_year,
                animal.status,
                animal.remarks
            ];
        },

        csvEscape(value) {
            return '"' + String(value ?? '').replace(/"/g, '""') + '"';
        },

        exportCSV() {
            const rows = this.filteredCarabaos().map(animal =>
                this.csvValues(animal).map(value => this.csvEscape(value)).join(',')
            );

            const csv = '\uFEFF' + [
                this.csvHeaders().map(value => this.csvEscape(value)).join(','),
                ...rows
            ].join('\r\n');

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');

            link.href = url;
            link.download = 'cbed-carabaos.csv';

            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);

            this.notify('CSV exported successfully.');
        },

        // CSV PARSER

        parseCSV(text) {
            const rows = [];
            let row = [];
            let value = '';
            let quoted = false;

            text = text.replace(/^\uFEFF/, '');

            for (let i = 0; i < text.length; i++) {
                const ch = text[i];
                const next = text[i + 1];

                if (ch === '"' && quoted && next === '"') {
                    value += '"';
                    i++;
                } else if (ch === '"') {
                    quoted = !quoted;
                } else if (ch === ',' && !quoted) {
                    row.push(value);
                    value = '';
                } else if ((ch === '\n' || ch === '\r') && !quoted) {
                    if (ch === '\r' && next === '\n') i++;

                    row.push(value);

                    if (row.some(cell => cell.trim() !== '')) rows.push(row);

                    row = [];
                    value = '';
                } else {
                    value += ch;
                }
            }

            row.push(value);
            if (row.some(cell => cell.trim() !== '')) rows.push(row);

            return rows;
        },

        // CSV IMPORT

       // CSV IMPORT

async importCSV(event) {
    const file = event.target.files?.[0];

    if (!file) return;

    try {
        if (!this.urls.import) {
            throw new Error('Carabao import route is not configured.');
        }

        if (!file.name.toLowerCase().endsWith('.csv')) {
            throw new Error('Please select a CSV file.');
        }

        const formData = new FormData();
        formData.append('file', file);

        const response = await fetch(this.urls.import, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': this.csrfToken
            },
            credentials: 'same-origin',
            body: formData
        });

        let result = {};

        try {
            result = await response.json();
        } catch (error) {
            throw new Error('The server returned an invalid response.');
        }

        if (!response.ok) {
            let message = result.message || 'Unable to import CSV file.';

            if (result.errors) {
                const errors = Array.isArray(result.errors)
                    ? result.errors
                    : Object.values(result.errors).flat();

                message = errors
                    .map(error => typeof error === 'string' ? error : JSON.stringify(error))
                    .join('\n') || message;
            }

            throw new Error(message);
        }

        await this.loadCarabaos();
        this.page = 1;

        const imported = Number(result.imported || 0);
        const skipped = Number(result.skipped || 0);

        this.notify(
            'Import completed: ' + imported +
            ' imported, ' + skipped + ' skipped.',
            imported > 0 ? 'success' : 'error'
        );

        if (result.errors?.length) {
            console.table(result.errors);
            console.warn('CSV import errors:', result.errors);
        }

        if (result.warnings?.length) {
            console.warn('CSV import warnings:', result.warnings);
        }

    } catch (error) {
        this.notify(
            error.message || 'Unable to import CSV file.',
            'error'
        );

        console.error('Carabao CSV import error:', error);

    } finally {
        event.target.value = '';
    }
},


        // PDF INVENTORY REPORT

        downloadPDFReport() {
            const records = this.filteredCarabaos();

            if (!window.jspdf || !window.jspdf.jsPDF) {
                this.notify(
                    'PDF library did not load. Check your internet connection.',
                    'error'
                );
                return;
            }

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({
                orientation: 'landscape',
                unit: 'mm',
                format: 'a4'
            });

            doc.setFont('helvetica', 'bold');
            doc.setFontSize(16);
            doc.text('CBED Data System', 14, 14);

            doc.setFontSize(12);
            doc.text('Carabao Inventory Report', 14, 21);

            doc.setFont('helvetica', 'normal');
            doc.setFontSize(9);
            doc.text('Generated: ' + new Date().toLocaleString(), 14, 27);
            doc.text('Records matching current filters: ' + records.length, 14, 32);

            const body = records.map(animal => [
                animal.animal_id || '',
                animal.bolus || '',
                this.farmerName(animal),
                this.cooperativeName(animal),
                this.provinceName(animal),
                animal.sex || '',
                this.dateValue(animal.birthday),
                animal.breed || '',
                animal.classification || '',
                animal.type_of_entrustment || '',
                this.dateValue(animal.date_of_acquisition),
                animal.status || 'Live'
            ]);

            doc.autoTable({
                startY: 37,
                head: [[
                    'Animal ID', 'Bolus', 'Farmer', 'Cooperative', 'Province',
                    'Sex', 'Birthday', 'Breed', 'Classification',
                    'Entrustment', 'Acquisition Date', 'Status'
                ]],
                body: body.length ? body : [[
                    'No records', '', '', '', '', '', '', '', '', '', '', ''
                ]],
                theme: 'grid',
                styles: {
                    fontSize: 7,
                    cellPadding: 2,
                    overflow: 'linebreak'
                },
                headStyles: {
                    fillColor: [11, 31, 58],
                    textColor: 255
                },
                margin: { left: 10, right: 10, bottom: 12 },
                didDrawPage: () => {
                    const pageCount = doc.internal.getNumberOfPages();

                    doc.setFontSize(8);
                    doc.text(
                        'CBED Data System - Carabao Management',
                        10,
                        doc.internal.pageSize.height - 5
                    );
                    doc.text(
                        'Page ' + doc.internal.getCurrentPageInfo().pageNumber
                            + ' of ' + pageCount,
                        doc.internal.pageSize.width - 10,
                        doc.internal.pageSize.height - 5,
                        { align: 'right' }
                    );
                }
            });

            doc.save('cbed-carabao-inventory-report.pdf');
            this.notify('PDF report downloaded successfully.');
        }
    }));
});
</script>
@endpush
