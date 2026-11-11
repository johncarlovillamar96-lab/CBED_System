{{-- resources/views/farmer.blade.php --}}

@extends('layouts.admin')

@section('title', 'Farmer Management | CBED')
@section('page-heading', 'Farmer Management')

@section('content')
    {{-- PAGE TITLE: intentionally outside the main Alpine/content div --}}
<div
    x-data="farmerPage()"
    x-init="init()"
    x-cloak
    data-farmer-index-url="{{ \Illuminate\Support\Facades\Route::has('admin.farmers.data') ? route('admin.farmers.data') : url('/admin/farmers/data') }}"
    data-farmer-store-url="{{ \Illuminate\Support\Facades\Route::has('admin.farmers.store') ? route('admin.farmers.store') : url('/admin/farmers') }}"
    data-farmer-bulk-url="{{ \Illuminate\Support\Facades\Route::has('admin.farmers.bulk') ? route('admin.farmers.bulk') : url('/admin/farmers/bulk') }}"
    data-farmer-import-url="{{ \Illuminate\Support\Facades\Route::has('admin.farmers.import') ? route('admin.farmers.import') : url('/admin/farmers/import') }}"
    data-farmer-update-url="{{ url('/admin/farmers') }}"
    data-cooperatives-url="{{ \Illuminate\Support\Facades\Route::has('admin.cooperatives.data') ? route('admin.cooperatives.data') : url('/admin/cooperatives/data') }}"
    class="management-page farmer-dashboard min-w-0 bg-slate-50 text-slate-800"
>
    {{-- HEADER --}}
    <div class="management-hero mb-6 flex flex-col gap-4 rounded-2xl p-5 text-white shadow-lg lg:flex-row lg:items-center lg:justify-between sm:p-6">
        <div>
            <div class="mb-2 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <i class="fa-solid fa-house"></i>
                <span>/</span>
                <span>CBED Data System</span>
                <span>/</span>
                <span class="font-semibold text-slate-700">Farmers</span>
            </div>

            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                Farmer Management
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Live MySQL records · Cooperative-linked farmer data
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="downloadPDFReport()"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-100">
                <i class="fa-solid fa-file-pdf text-red-600"></i> Download PDF Report
            </button>

            <button type="button"
                @click="openImportModal()"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                <i class="fa-solid fa-file-import"></i>
                Import Excel
            </button>

            <button type="button"
                @click="exportCSV()"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                <i class="fa-solid fa-file-export"></i>
                Export CSV
            </button>

            <button type="button"
                @click="openBulkModal()"
                class="inline-flex items-center gap-2 rounded-lg bg-[#0b1f3a] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#122b4d]">
                <i class="fa-solid fa-users"></i>
                Bulk Add Farmers
            </button>
        </div>
    </div>


    {{-- SUMMARY CARDS --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <template x-for="card in summaryCards()" :key="card.key">
            <button type="button"
                    @click="applyCardFilter(card.key)"
                    :aria-pressed="activeCard === card.key"
                    :class="activeCard === card.key
                        ? 'border-blue-500 ring-2 ring-blue-200 bg-white'
                        : 'border-slate-200 bg-white hover:border-blue-300 hover:shadow-md'"
                    class="management-summary-card w-full rounded-xl border p-5 text-left shadow-sm transition focus:outline-none focus:ring-2 focus:ring-blue-400">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-slate-500" x-text="card.label"></p>
                        <p class="mt-2 text-3xl font-bold tracking-tight text-slate-900"
                           x-text="card.value"></p>
                        <p class="mt-2 text-xs text-slate-500" x-text="card.note"></p>
                    </div>
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
                         :class="card.iconBg">
                        <i :class="card.icon + ' text-lg ' + card.iconColor"></i>
                    </div>
                </div>
                <p class="mt-3 text-xs font-semibold"
                   :class="activeCard === card.key ? 'text-slate-700' : 'text-slate-400'"
                   x-text="activeCard === card.key ? 'Filter active · Click to clear' : 'Click to filter records'"></p>
            </button>
        </template>
    </div>

    {{-- FARMER DIRECTORY --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="flex flex-col gap-3 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Farmer Records</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Search, filter, and manage farmer records.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700">
                    <span x-text="filteredFarmers().length"></span> records
                </span>
                <button @click="resetFilters()"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                    <i class="fa-solid fa-rotate-left"></i>
                    Reset Filters
                </button>
            </div>
        </div>

        {{-- FILTERS --}}
        <div class="grid grid-cols-1 gap-3 border-b border-slate-200 bg-slate-50 p-5 sm:grid-cols-2 xl:grid-cols-4">
            <div class="xl:col-span-2">
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search Farmers</label>
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input x-model="filters.search" @input="currentPage = 1; activeCard = ''" type="text"
                           placeholder="Search name, barangay, municipality, cooperative..."
                           class="w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm outline-none focus:border-blue-500">
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Registration Year</label>
                <select x-model="filters.year" @change="currentPage = 1; activeCard = ''" class="filter-input">
                    <option value="">All Years</option>
                    <template x-for="year in availableYears()" :key="year">
                        <option :value="String(year)" x-text="year"></option>
                    </template>
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Sex</label>
                <select x-model="filters.sex" @change="currentPage = 1; activeCard = ''" class="filter-input">
                    <option value="">All</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Municipality</label>
                <select x-model="filters.municipality" @change="currentPage = 1; activeCard = ''" class="filter-input">
                    <option value="">All Municipalities</option>
                    <option value="__has_municipality__">Has Municipality Recorded</option>
                    <template x-for="municipality in availableMunicipalities()" :key="municipality">
                        <option :value="municipality" x-text="municipality"></option>
                    </template>
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Quarter</label>
                <select x-model="filters.quarter" @change="currentPage = 1; activeCard = ''" class="filter-input">
                    <option value="">All Quarters</option>
                    <option value="1">Q1 (Jan–Mar)</option>
                    <option value="2">Q2 (Apr–Jun)</option>
                    <option value="3">Q3 (Jul–Sep)</option>
                    <option value="4">Q4 (Oct–Dec)</option>
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Month</label>
                <select x-model="filters.month" @change="currentPage = 1; activeCard = ''" class="filter-input">
                    <option value="">All Months</option>
                    <template x-for="(month, index) in months" :key="month">
                        <option :value="String(index + 1)" x-text="month"></option>
                    </template>
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Registration From</label>
                <input type="date" x-model="filters.dateFrom" @change="currentPage = 1; activeCard = ''" class="filter-input">
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Registration To</label>
                <input type="date" x-model="filters.dateTo" @change="currentPage = 1; activeCard = ''" class="filter-input">
            </div>
        </div>

        {{-- TABLE: FARMER COLUMNS --}}
        <div class="overflow-x-auto">
            <table class="min-w-[1500px] w-full divide-y divide-slate-200 text-left">
                <thead class="bg-slate-100 text-xs uppercase tracking-wider text-slate-600">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-3.5 font-semibold">No.</th>
                        <th class="whitespace-nowrap px-4 py-3.5 font-semibold">Farmer Name</th>
                        <th class="whitespace-nowrap px-4 py-3.5 font-semibold">Sex</th>
                        <th class="whitespace-nowrap px-4 py-3.5 font-semibold">Birthday</th>
                        <th class="whitespace-nowrap px-4 py-3.5 font-semibold">Age</th>
                        <th class="whitespace-nowrap px-4 py-3.5 font-semibold">Civil Status</th>
                        <th class="whitespace-nowrap px-4 py-3.5 font-semibold">Position</th>
                        <th class="whitespace-nowrap px-4 py-3.5 font-semibold">Barangay</th>
                        <th class="whitespace-nowrap px-4 py-3.5 font-semibold">Municipality</th>
                        <th class="whitespace-nowrap px-4 py-3.5 font-semibold">Province</th>
                        <th class="whitespace-nowrap px-4 py-3.5 font-semibold">Cooperative</th>
                        <th class="whitespace-nowrap px-4 py-3.5 font-semibold">Registration Date</th>
                        <th class="whitespace-nowrap px-4 py-3.5 text-right font-semibold">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 bg-white">
                    <template x-for="(farmer, index) in paginatedFarmers()" :key="farmer.id">
                        <tr class="transition hover:bg-slate-50">
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-500"
                                x-text="(currentPage - 1) * perPage + index + 1"></td>

                            <td class="whitespace-nowrap px-4 py-4">
                                <div class="font-semibold text-slate-800" x-text="farmerName(farmer)"></div>
                                <div class="mt-0.5 text-xs text-slate-500" x-text="farmer.id"></div>
                            </td>

                            <td class="whitespace-nowrap px-4 py-4">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                      :class="farmer.sex === 'Female' ? 'bg-pink-50 text-pink-700' : 'bg-blue-50 text-blue-700'"
                                      x-text="farmer.sex || '—'"></span>
                            </td>

                            <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600" x-text="farmer.birthday || '—'"></td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600" x-text="age(farmer.birthday)"></td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600" x-text="farmer.civil_status || '—'"></td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600" x-text="farmer.position || '—'"></td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600" x-text="farmer.barangay || '—'"></td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600" x-text="farmer.municipality || '—'"></td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600" x-text="farmer.province || '—'"></td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600" x-text="farmer.cooperative || '—'"></td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600" x-text="farmer.registration_date || '—'"></td>

                            <td class="whitespace-nowrap px-4 py-4 text-right">
                                <div class="flex justify-end gap-1.5">
                                    <button @click="viewFarmer(farmer)" title="View"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                    <button @click="editFarmer(farmer)" title="Edit"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 text-blue-700 hover:bg-blue-50">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button @click="deleteFarmer(farmer)" title="Delete"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 text-red-600 hover:bg-red-50">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <tr x-show="filteredFarmers().length === 0">
                        <td colspan="13" class="px-6 py-14 text-center">
                            <i class="fa-solid fa-users text-3xl text-slate-300"></i>
                            <p class="mt-3 font-semibold text-slate-700">No farmer records found</p>
                            <p class="mt-1 text-sm text-slate-500">Try adjusting your filters or add farmer records.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

      
        {{-- Pagination --}}
        <div class="flex flex-col gap-3 border-t border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-slate-500">
                Showing
                <span class="font-semibold text-slate-700"
                    x-text="filteredFarmers().length ? (currentPage - 1) * perPage + 1 : 0"></span>
                to
                <span class="font-semibold text-slate-700"
                    x-text="Math.min(currentPage * perPage, filteredFarmers().length)"></span>
                of
                <span class="font-semibold text-slate-700"
                    x-text="filteredFarmers().length"></span>
                farmers
            </p>

            <div class="flex items-center gap-2">
                <button type="button"
                    @click="previousPage()"
                    :disabled="currentPage <= 1"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40">
                    <i class="fa-solid fa-chevron-left mr-1"></i> Previous
                </button>

                <span class="px-2 text-sm font-semibold text-slate-700">
                    Page <span x-text="currentPage"></span>
                    of <span x-text="totalPages()"></span>
                </span>

                <button type="button"
                    @click="nextPage()"
                    :disabled="currentPage >= totalPages()"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40">
                    Next <i class="fa-solid fa-chevron-right ml-1"></i>
                </button>
            </div>
        </div>



    {{-- BULK ADD FARMERS MODAL --}}
    <div x-show="bulkModalOpen" x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-2 sm:p-4"
         @keydown.escape.window="bulkModalOpen = false"
         style="display:none">

        <div @click.outside="bulkModalOpen = false"
             class="flex max-h-[96vh] w-[98vw] max-w-[1800px] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">

            {{-- MODAL HEADER --}}
            <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
                <div class="min-w-0">
                    <h2 class="text-lg font-bold text-slate-900">Bulk Add Farmers</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Enter multiple farmer records below and save them together.
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    <span class="whitespace-nowrap rounded-lg bg-slate-100 px-3 py-2 text-sm font-bold text-slate-700">
                        <span x-text="bulkRows.length"></span> farmer(s)
                    </span>
                    <button @click="bulkModalOpen = false"
                            class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100"
                            title="Close">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>
            </div>

            {{-- BULK TOOLBAR --}}
            <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-600">
                    <i class="fa-solid fa-circle-info mr-1 text-blue-600"></i>
                    Fields marked <span class="font-bold text-red-500">*</span> are required.
                </p>
                <div class="flex flex-wrap gap-2">
                    <button @click="addBulkRow()"
                            class="inline-flex items-center gap-2 rounded-lg bg-[#0b1f3a] px-3 py-2 text-sm font-semibold text-white hover:bg-[#122b4d]">
                        <i class="fa-solid fa-plus"></i>
                        Add Row
                    </button>
                </div>
            </div>

            {{-- BULK ENTRY TABLE --}}
            <div class="min-h-0 flex-1 overflow-auto">
                <table class="min-w-[1900px] w-full border-collapse text-left">
                    <thead class="sticky top-0 z-10 bg-slate-100 text-xs uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="w-14 border-b border-slate-200 px-3 py-3 text-center">No.</th>
                            <th class="border-b border-slate-200 px-3 py-3">First Name *</th>
                            <th class="border-b border-slate-200 px-3 py-3">Middle Name</th>
                            <th class="border-b border-slate-200 px-3 py-3">Last Name *</th>
                            <th class="border-b border-slate-200 px-3 py-3">Sex *</th>
                            <th class="border-b border-slate-200 px-3 py-3">Birthday</th>
                            <th class="border-b border-slate-200 px-3 py-3">Civil Status</th>
                            <th class="border-b border-slate-200 px-3 py-3">Position</th>
                            <th class="border-b border-slate-200 px-3 py-3">Barangay</th>
                            <th class="border-b border-slate-200 px-3 py-3">Municipality</th>
                            <th class="border-b border-slate-200 px-3 py-3">Province</th>
                            <th class="border-b border-slate-200 px-3 py-3">Cooperative</th>
                            <th class="border-b border-slate-200 px-3 py-3">Registration Date *</th>
                            <th class="w-16 border-b border-slate-200 px-3 py-3 text-center">Action</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(row, index) in bulkRows" :key="row.rowKey">
                            <tr class="align-top hover:bg-slate-50">
                                <td class="border-b border-slate-100 px-3 py-3 text-center text-sm font-semibold text-slate-500"
                                    x-text="index + 1"></td>

                                <td class="border-b border-slate-100 px-2 py-2">
                                    <input x-model="row.first_name" class="bulk-input" placeholder="First name" required>
                                </td>
                                <td class="border-b border-slate-100 px-2 py-2">
                                    <input x-model="row.middle_name" class="bulk-input" placeholder="Middle name">
                                </td>
                                <td class="border-b border-slate-100 px-2 py-2">
                                    <input x-model="row.last_name" class="bulk-input" placeholder="Last name" required>
                                </td>
                                <td class="border-b border-slate-100 px-2 py-2">
                                    <select x-model="row.sex" class="bulk-input" required>
                                        <option value="">Select</option>
                                        <option>Male</option>
                                        <option>Female</option>
                                    </select>
                                </td>
                                <td class="border-b border-slate-100 px-2 py-2">
                                    <input type="date" x-model="row.birthday" class="bulk-input">
                                </td>
                                <td class="border-b border-slate-100 px-2 py-2">
                                    <select x-model="row.civil_status" class="bulk-input">
                                        <option value="">Select</option>
                                        <option>Single</option>
                                        <option>Married</option>
                                        <option>Widowed</option>
                                        <option>Separated</option>
                                        <option>Others</option>
                                    </select>
                                </td>
                                <td class="border-b border-slate-100 px-2 py-2">
                                    <input x-model="row.position" class="bulk-input" placeholder="Position">
                                </td>
                                <td class="border-b border-slate-100 px-2 py-2">
                                    <select x-model="row.barangay" class="bulk-input" :disabled="!row.municipality || row.locationLoading">
                                        <option value="" x-text="row.locationLoading ? 'Loading barangays...' : 'Select Barangay'"></option>
                                        <template x-for="barangay in (rowBarangays[row.rowKey] || [])" :key="barangay"><option :value="barangay" x-text="barangay"></option></template>
                                    </select>
                                </td>
                                <td class="border-b border-slate-100 px-2 py-2">
                                    <select x-model="row.municipality" class="bulk-input" :disabled="!row.province" @change="row.barangay=''; loadBarangaysForRow(row)">
                                        <option value="">Select Municipality</option>
                                        <template x-for="municipality in municipalityOptions(row.province)" :key="municipality"><option :value="municipality" x-text="municipality"></option></template>
                                    </select>
                                </td>
                                <td class="border-b border-slate-100 px-2 py-2">
                                    <select x-model="row.province" class="bulk-input" @change="row.municipality=''; row.barangay=''; row.cooperative_id=''; rowBarangays[row.rowKey]=[]">
                                        <option value="" x-text="locationDataLoading ? 'Loading provinces...' : 'Select Province'"></option>
                                        <template x-for="province in provinceOptions" :key="province"><option :value="province" x-text="province"></option></template>
                                    </select>
                                </td>
                                <td class="border-b border-slate-100 px-2 py-2">
                                    <select
                                        x-model="row.cooperative_id"
                                        @change="row.cooperative_id = $event.target.value"
                                        class="bulk-input"
                                        :disabled="!row.province"
                                    >
                                        <option value="">Select Cooperative</option>

                                        <template
                                            x-for="coop in filteredCooperatives(row.province)"
                                            :key="coop.id"
                                        >
                                            <option
                                                :value="String(coop.id)"
                                                x-text="coop.cooperative_name"
                                            ></option>
                                        </template>
                                    </select>
                                    <p
                                        x-show="row.province && filteredCooperatives(row.province).length === 0"
                                        class="mt-1 text-xs text-amber-600"
                                    >No cooperatives registered in this province.</p>
                                </td>
                                <td class="border-b border-slate-100 px-2 py-2">
                                    <input type="date" x-model="row.registration_date" class="bulk-input" required>
                                </td>
                                <td class="border-b border-slate-100 px-2 py-2 text-center">
                                    <button type="button" @click="removeBulkRow(index)"
                                            :disabled="bulkRows.length === 1"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-30"
                                            title="Remove row">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- BULK FOOTER --}}
            <div class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <p class="text-sm text-slate-500">
                    <span class="font-semibold text-slate-700" x-text="bulkRows.length"></span>
                    farmer row(s) ready for entry.
                </p>
                <div class="flex flex-wrap justify-end gap-2">
                    <button @click="bulkModalOpen = false"
                            class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button @click="saveBulkFarmers()"
                            class="inline-flex items-center gap-2 rounded-lg bg-[#0b1f3a] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#122b4d]">
                        <i class="fa-solid fa-floppy-disk"></i>
                        Save All Farmers
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- VIEW FARMER MODAL --}}
    <div x-show="viewModalOpen" x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-3 sm:p-5"
         @keydown.escape.window="viewModalOpen = false"
         style="display:none">
        <div @click.outside="viewModalOpen = false"
             class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Farmer Details</h2>
                    <p class="mt-1 text-sm text-slate-500">Farmer profile information.</p>
                </div>
                <button @click="viewModalOpen = false" class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <div class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2">
                <template x-for="item in viewItems()" :key="item.label">
                    <div class="rounded-lg border border-slate-200 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500" x-text="item.label"></p>
                        <p class="mt-1 break-words text-sm font-semibold text-slate-800" x-text="item.value || '—'"></p>
                    </div>
                </template>
            </div>
            <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-6 py-4">
                <button @click="viewModalOpen = false" class="rounded-lg bg-[#0b1f3a] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#122b4d]">
                    Close
                </button>
            </div>
        </div>
    </div>

    {{-- EDIT FARMER MODAL --}}
    <div x-show="editModalOpen" x-transition.opacity
         class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/50 p-3 sm:p-5"
         @keydown.escape.window="editModalOpen = false"
         style="display:none">
        <div @click.outside="editModalOpen = false"
             class="flex max-h-[94vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Update Farmer</h2>
                    <p class="mt-1 text-sm text-slate-500">Edit the farmer's details and save changes to MySQL.</p>
                </div>
                <button type="button" @click="editModalOpen = false"
                        class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100"
                        aria-label="Close edit farmer modal">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto p-5 sm:p-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">First Name *</label>
                        <input x-model="editForm.first_name" class="bulk-input" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Middle Name</label>
                        <input x-model="editForm.middle_name" class="bulk-input">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Last Name *</label>
                        <input x-model="editForm.last_name" class="bulk-input" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Sex *</label>
                        <select x-model="editForm.sex" class="bulk-input" required>
                            <option value="">Select Sex</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Birthday</label>
                        <input type="date" x-model="editForm.birthday" class="bulk-input">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Cell Number</label>
                        <input x-model="editForm.cell_number" class="bulk-input">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Civil Status</label>
                        <select x-model="editForm.civil_status" class="bulk-input">
                            <option value="">Select Status</option>
                            <option>Single</option><option>Married</option><option>Widowed</option>
                            <option>Separated</option><option>Others</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Occupation</label>
                        <input x-model="editForm.occupation" class="bulk-input">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Position</label>
                        <input x-model="editForm.position" class="bulk-input">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Province</label>
                        <input x-model="editForm.province" class="bulk-input">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Municipality</label>
                        <input x-model="editForm.municipality" class="bulk-input">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Barangay</label>
                        <input x-model="editForm.barangay" class="bulk-input">
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Cooperative / Association</label>
                        <select x-model="editForm.cooperative_id" class="bulk-input">
                            <option value="">Solo Farmer / No Cooperative</option>
                            <template x-for="coop in cooperatives" :key="coop.id">
                                <option :value="String(coop.id)" x-text="coop.cooperative_name"></option>
                            </template>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Choose “Solo Farmer / No Cooperative” to leave the farmer unlinked.</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Spouse Name</label>
                        <input x-model="editForm.spouse_name" class="bulk-input">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Spouse Occupation</label>
                        <input x-model="editForm.spouse_occupation" class="bulk-input">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Registration Date *</label>
                        <input type="date" x-model="editForm.registration_date" class="bulk-input" required>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
                <button type="button" @click="editModalOpen = false"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700">
                    Cancel
                </button>
                <button type="button" @click="saveFarmerUpdate()" :disabled="isSavingEdit"
                        class="inline-flex items-center gap-2 rounded-lg bg-[#0b1f3a] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#122b4d] disabled:opacity-60">
                    <i class="fa-solid" :class="isSavingEdit ? 'fa-spinner fa-spin' : 'fa-floppy-disk'"></i>
                    <span x-text="isSavingEdit ? 'Saving...' : 'Save Changes'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- EXCEL IMPORT MODAL --}}
    <div x-show="importModalOpen" x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-3 sm:p-5"
         @keydown.escape.window="closeImportModal()"
         style="display:none">
        <div @click.outside="closeImportModal()" class="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Import Farmer Excel File</h2>
                    <p class="mt-1 text-sm text-slate-500">Choose a cooperative, then import farmer names in A–Z order.</p>
                </div>
                <button type="button" @click="closeImportModal()" :disabled="importing || loadingImport" class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 disabled:opacity-50">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <div class="space-y-4 p-6">
                <div>
                    <label for="farmer_import_cooperative" class="mb-1.5 block text-sm font-semibold text-slate-700">Cooperative</label>
                    <select id="farmer_import_cooperative" x-model="importCooperativeId" :disabled="importing || loadingImport" class="w-full rounded-lg border border-slate-300 bg-white p-3 text-sm">
                        <option value="">Select an existing cooperative</option>
                        <template x-for="cooperative in cooperatives" :key="'import-coop-' + cooperative.id">
                            <option :value="cooperative.id" x-text="cooperative.cooperative_name + ' (ID ' + cooperative.id + ')' "></option>
                        </template>
                    </select>
                </div>
                <p class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">
                    Supported files: Excel (.xlsx, .xls). Use a <b>Farmer Name</b> column or separate <b>first_name</b> and <b>last_name</b> columns. New Farmer IDs are assigned alphabetically within the selected cooperative (for example, 1-1, 1-2).
                </p>
                <input type="file" accept=".xlsx,.xls" @change="handleImportFile($event)"
                       :disabled="importing || loadingImport"
                       class="block w-full rounded-lg border border-slate-300 bg-white p-3 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold">
                <div x-show="loadingImport" class="flex items-center gap-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                    <span>Uploading and saving records to MySQL. Please wait...</span>
                </div>
                <p x-show="importMessage" x-text="importMessage" class="whitespace-pre-line text-sm text-slate-600"></p>
            </div>

            <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">
                <button type="button" @click="closeImportModal()" :disabled="importing || loadingImport" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 disabled:opacity-50">
                    Cancel
                </button>
                <button type="button" @click="saveImportedFarmers()" :disabled="!importReady || !importCooperativeId || importing || loadingImport" class="inline-flex items-center gap-2 rounded-lg bg-[#0b1f3a] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#122b4d] disabled:cursor-not-allowed disabled:opacity-50">
                    <i class="fa-solid" :class="importing ? 'fa-spinner fa-spin' : 'fa-file-import'"></i>
                    <span x-text="importing ? 'Saving records...' : 'Import Excel'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- TOAST --}}
    <div x-show="toast.show" x-transition
         class="fixed bottom-5 right-5 z-[70] flex max-w-sm items-start gap-3 rounded-xl bg-slate-900 px-4 py-3 text-sm text-white shadow-xl"
         style="display:none">
        <i class="fa-solid fa-circle-check mt-0.5 text-emerald-400"></i>
        <span x-text="toast.message"></span>
    </div>
</div>


    {{-- FOOTER --}}
    <div class="mt-6 flex flex-col gap-1 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
        <span>CBED Data System · Farmer Management</span>
        <span>Live MySQL records · Cooperative-linked farmer data</span>
    </div>
@endsection

@push('styles')
<style>
    [x-cloak] { display: none !important; }

    .filter-input {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: .5rem;
        background: #fff;
        padding: .625rem .75rem;
        font-size: .875rem;
        outline: none;
    }

    .filter-input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgb(59 130 246 / 10%);
    }

    .bulk-input {
        display: block;
        width: 100%;
        min-width: 125px;
        border: 1px solid #cbd5e1;
        border-radius: .5rem;
        background: #fff;
        padding: .625rem .65rem;
        font-size: .8125rem;
        outline: none;
    }

    .bulk-input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgb(59 130 246 / 10%);
    }

    .management-page { background: radial-gradient(circle at 92% 0%,rgba(11,31,58,.06),transparent 25%),#f5f8fc; }
    .management-hero { position:relative; isolation:isolate; overflow:hidden; background:radial-gradient(circle at 88% 10%,rgba(255,255,255,.14),transparent 20%),linear-gradient(115deg,#07152b,#0b1f3a 55%,#1e477c); }
    .management-hero h1,.management-hero p,.management-hero span { color:#fff!important; }
    .management-hero button [class*="text-"],.management-hero a [class*="text-"] { color:#0b1f3a!important; }
    .management-hero .text-slate-500,.management-hero .text-slate-700 { color:#dbe4ee!important; }
    .management-hero button,.management-hero a { border-color:rgba(255,255,255,.5)!important;background:#fff!important;color:#0b1f3a!important; }
    .management-hero button:hover,.management-hero a:hover { background:#eaf0f6!important; }
    .management-page .management-summary-card { border-color:#dbe4ee;background:#fff;color:#0b1f3a;transition:transform .15s ease,box-shadow .15s ease; }
    .management-page .management-summary-card:hover { transform:translateY(-2px);box-shadow:0 12px 24px rgba(11,31,58,.12); }
    .management-page .management-summary-card[class*="ring-blue-"] { background:#fff!important;border-color:#0b1f3a!important;color:#0b1f3a!important; }
    .management-page .management-summary-card[class*="ring-blue-"] p { color:#0b1f3a!important; }
    .management-page .management-summary-card > div > div:last-child { background:#0b1f3a!important;color:#fff!important;border-radius:.85rem; }
    .management-page .management-summary-card > div > div:last-child [class*="text-"] { color:#fff!important; }
    .management-page .management-summary-card [class*="text-blue-"],.management-page .management-summary-card [class*="text-emerald-"],.management-page .management-summary-card [class*="text-violet-"] { color:#0b1f3a!important; }
    .management-page .management-summary-card[class*="ring-blue-"] [class*="text-"] { color:#0b1f3a!important; }
    .management-page .overflow-hidden.rounded-xl.border { border-color:#dbe4ee;box-shadow:0 5px 20px rgba(15,23,42,.045); }
    .management-page table { border-collapse:separate;border-spacing:0; }
    .management-page table thead th { position:sticky;top:0;z-index:10;background:#0b1f3a!important;color:#fff!important;box-shadow:inset 0 -1px 0 #29486b; }
    .management-page table tbody tr:nth-child(even) { background:#f2f6fb; }
    .management-page table tbody tr:hover { background:#e5edf7; }
    .management-page input:focus,.management-page select:focus,.management-page textarea:focus,.filter-input:focus,.bulk-input:focus { border-color:#0b1f3a!important;box-shadow:0 0 0 3px rgba(11,31,58,.13)!important;outline:none; }
    .management-page .bg-blue-700,.management-page .bg-blue-600,.management-page .bg-\[\#0b1f3a\] { background:#0b1f3a!important;color:#fff!important; }
    .management-page .text-blue-700,.management-page .text-blue-600,.management-page .text-emerald-700,.management-page .text-violet-600 { color:#0b1f3a!important; }
    .management-page .bg-blue-50,.management-page .bg-emerald-50,.management-page .bg-violet-50 { background:#f1f5f9!important; }
    .management-page .border-blue-500 { border-color:#0b1f3a!important; }
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
<script>
const registerFarmerPage = () => {
    if (!window.Alpine || window.__cbedFarmerPageRegistered) return;
    window.__cbedFarmerPageRegistered = true;
    window.Alpine.data('farmerPage', () => ({
        farmers: [],
        cooperatives: [],
        currentPage: 1,
        perPage: 10,
        activeCard: '',

        bulkModalOpen: false,
        viewModalOpen: false,
        editModalOpen: false,
        importModalOpen: false,
        isSavingEdit: false,
        editingFarmerId: null,
        editForm: {},

        selectedFarmer: null,
        bulkRows: [],
        rowSequence: 0,
        importMessage: '',
        importFile: null,
        importCooperativeId: '',
        importReady: false,
        importing: false,
        loadingImport: false,

        toast: { show: false, message: '' },
        toastTimer: null,

        api: {},
        isLoading: false,

        provinceOptions: [],
        locationProvinces: [],
        locationMunicipalities: [],
        rowBarangays: {},

        locationDataLoading: false,
        locationDataError: '',

        months: [
            'January', 'February', 'March', 'April',
            'May', 'June', 'July', 'August',
            'September', 'October', 'November', 'December'
        ],

        filters: {
            search: '',
            year: '',
            quarter: '',
            month: '',
            sex: '',
            municipality: '',
            dateFrom: '',
            dateTo: ''
        },

        // --------------------------------------------------
        // INITIALIZATION
        // --------------------------------------------------

        async init() {
            this.api = {
                index: this.$root.dataset.farmerIndexUrl,
                store: this.$root.dataset.farmerStoreUrl,
                bulk: this.$root.dataset.farmerBulkUrl,
                import: this.$root.dataset.farmerImportUrl,
                update: this.$root.dataset.farmerUpdateUrl,
                cooperatives: this.$root.dataset.cooperativesUrl
            };

            // Load the table immediately in parallel with location/cooperative data.
            // The slow external PSGC request should not block farmer records.
            await Promise.all([
                this.loadFarmers(),
                this.loadCooperatives(),
                this.loadRegionIIILocations()
            ]);
        },

        csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.content
                || @json(csrf_token());
        },

        async request(url, options = {}) {
            const response = await fetch(url, {
                credentials: 'same-origin',
                ...options,
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrf(),
                    ...(options.headers || {})
                }
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const validationErrors = payload.errors
                    ? Object.values(payload.errors).flat().join(' ')
                    : '';

                throw new Error(
                    payload.message ||
                    validationErrors ||
                    `Request failed (${response.status})`
                );
            }

            return payload;
        },

        // --------------------------------------------------
        // CHARACTER ENCODING FIX
        // --------------------------------------------------

        fixPSGCName(value) {
            const text = String(value ?? '').trim();

            // Do not alter correctly encoded text.
            if (!/[ÃÂâ]/.test(text)) {
                return text;
            }

            try {
                // Convert common UTF-8 mojibake back to UTF-8.
                const bytes = Uint8Array.from(
                    text,
                    character => character.charCodeAt(0)
                );

                return new TextDecoder('utf-8', {
                    fatal: true
                }).decode(bytes);
            } catch (error) {
                return text;
            }
        },

        // --------------------------------------------------
        // FARMER RECORD NORMALIZATION
        // --------------------------------------------------

        normalizeFarmer(row) {
            return {
                ...row,

                db_id: row.id ?? row.db_id ?? row.database_id,

                // Keep the database ID separately for update/delete.
                id: row.farmer_code || String(row.id),

                cooperative_id: row.cooperative_id
                    ? String(row.cooperative_id)
                    : '',

                cooperative:
                    row.cooperative_record?.cooperative_name ||
                    row.cooperative?.cooperative_name ||
                    row.cooperative ||
                    '',

                registration_date: row.registration_date
                    ? String(row.registration_date).slice(0, 10)
                    : '',

                birthday: row.birthday
                    ? String(row.birthday).slice(0, 10)
                    : ''
            };
        },

        async loadFarmers() {
            this.isLoading = true;

            try {
                const payload = await this.request(this.api.index);

                // FarmerController::data() returns { success, farmers }.
                const rows = Array.isArray(payload)
                    ? payload
                    : (
                        payload.farmers ??
                        payload.data ??
                        []
                    );

                this.farmers = rows.map(row =>
                    this.normalizeFarmer(row)
                );

                this.currentPage = Math.min(
                    this.currentPage,
                    this.totalPages()
                );
            } catch (error) {
                console.error('Farmer loading error:', error);

                this.notify(
                    'Unable to load farmers: ' + error.message
                );
            } finally {
                this.isLoading = false;
            }
        },

        async loadCooperatives() {
            try {
                const payload = await this.request(this.api.cooperatives);

                const rows = Array.isArray(payload)
                    ? payload
                    : (
                        payload.cooperatives ??
                        payload.data ??
                        []
                    );

                this.cooperatives = rows
                    .map(cooperative => {
                        let province =
                            cooperative.province ??
                            cooperative.province_name ??
                            cooperative.provinceName ??
                            '';

                        if (province && typeof province === 'object') {
                            province = province.name ??
                                province.province_name ??
                                '';
                        }

                        const databaseId =
                            cooperative.db_id ??
                            cooperative.cooperative_id ??
                            cooperative.id ??
                            '';

                        const cooperativeCode = String(
                            cooperative.cooperative_code ??
                            cooperative.coop_code ??
                            cooperative.herd_code ??
                            cooperative.code ??
                            // Older API responses incorrectly used `id` for the code.
                            (typeof cooperative.id === 'string' && !/^\d+$/.test(cooperative.id)
                                ? cooperative.id
                                : '')
                        ).trim();

                        const cooperativeName = String(
                            cooperative.cooperative_name ??
                            cooperative.name ??
                            ''
                        ).trim();

                        return {
                            ...cooperative,
                            id: String(databaseId),
                            db_id: String(databaseId),
                            cooperative_code: cooperativeCode,
                            cooperative_name: cooperativeName,
                            province: String(province).trim()
                        };
                    })
                    .filter(item => /^\d+$/.test(item.id) && item.cooperative_name)
                    .sort((a, b) =>
                        a.cooperative_name.localeCompare(b.cooperative_name)
                    );
            } catch (error) {
                console.error('Cooperative loading error:', error);

                this.notify(
                    'Unable to load cooperative options: ' +
                    error.message
                );
            }
        },

        filteredCooperatives(province) {
            if (!province) return [];

            const normalize = value => String(value ?? '')
                .trim()
                .toLowerCase()
                .replace(/\s+/g, ' ');

            return this.cooperatives.filter(cooperative =>
                normalize(cooperative.province) === normalize(province)
            );
        },


        

        // --------------------------------------------------
        // PSGC: REGION III PROVINCES AND MUNICIPALITIES
        // --------------------------------------------------

        async loadRegionIIILocations() {
            this.locationDataLoading = true;
            this.locationDataError = '';

            try {
                // Reuse location data for this browser session to avoid downloading
                // the full PSGC provinces/municipalities lists on every page refresh.
                const cacheKey = 'cbed_psgc_region_iii_locations_v1';
                let cachedLocations = null;

                try {
                    cachedLocations = JSON.parse(sessionStorage.getItem(cacheKey) || 'null');
                } catch (cacheError) {
                    cachedLocations = null;
                }

                if (
                    cachedLocations &&
                    Array.isArray(cachedLocations.locationProvinces) &&
                    Array.isArray(cachedLocations.locationMunicipalities) &&
                    cachedLocations.locationProvinces.length >= 7
                ) {
                    this.locationProvinces = cachedLocations.locationProvinces;
                    this.locationMunicipalities = cachedLocations.locationMunicipalities;
                    this.provinceOptions = this.locationProvinces.map(item => item.name);
                    return;
                }

                const base = 'https://psgc.cloud/api/v2';

                const list = async path => {
                    const response = await fetch(`${base}/${path}`, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });

                    if (!response.ok) {
                        throw new Error(
                            `PSGC request failed: ${response.status}`
                        );
                    }

                    const payload = await response.json();

                    return Array.isArray(payload)
                        ? payload
                        : (
                            payload.data ||
                            payload.results ||
                            []
                        );
                };

                const [provinces, cities] = await Promise.all([
                    list('provinces'),
                    list('cities-municipalities')
                ]);

                const code = item => String(
                    item.code ??
                    item.psgc_code ??
                    item.psgcCode ??
                    ''
                );

                const name = item => this.fixPSGCName(
                    item.name ??
                    item.psgc_name ??
                    item.psgcName ??
                    ''
                );

                const region = item => {
                    const value =
                        item.region ??
                        item.region_code ??
                        item.regionCode ??
                        '';

                    if (typeof value === 'object' && value !== null) {
                        return `${value.code || ''} ${value.name || ''}`;
                    }

                    return String(value);
                };

                const isRegionIII = item => {
                    const regionValue = region(item).toLowerCase().trim();
                    const itemCode = code(item);

                    return (
                        regionValue.includes('central luzon') ||
                        regionValue.includes('region iii') ||
                        regionValue === '03' ||
                        regionValue.startsWith('030') ||
                        itemCode.startsWith('03')
                    );
                };

                this.locationProvinces = provinces
                    .filter(isRegionIII)
                    .map(item => ({
                        code: code(item),
                        name: name(item)
                    }))
                    .filter(item => item.code && item.name)
                    .sort((a, b) =>
                        a.name.localeCompare(b.name)
                    );

                const normalize = value =>
                    String(value || '')
                        .toLowerCase()
                        .replace(/[^a-z0-9]/g, '');

                const provinceCode = item => {
                    const raw =
                        item.province ??
                        item.province_code ??
                        item.provinceCode ??
                        item.provinceCodeValue ??
                        '';

                    const reference =
                        typeof raw === 'object' && raw !== null
                            ? String(
                                raw.code ??
                                raw.psgc_code ??
                                raw.psgcCode ??
                                raw.name ??
                                ''
                            )
                            : String(raw);

                    const matched = this.locationProvinces.find(province =>
                        province.code === reference ||
                        normalize(province.name) === normalize(reference) ||
                        (
                            reference.length >= 4 &&
                            province.code.startsWith(reference)
                        )
                    );

                    return matched?.code || '';
                };

                this.locationMunicipalities = cities
                    .map(item => ({
                        code: code(item),
                        name: name(item),
                        provinceCode: provinceCode(item)
                    }))
                    .filter(item =>
                        item.code &&
                        item.name &&
                        item.provinceCode
                    )
                    .sort((a, b) =>
                        a.name.localeCompare(b.name)
                    );

                this.provinceOptions =
                    this.locationProvinces.map(item => item.name);

                try {
                    sessionStorage.setItem('cbed_psgc_region_iii_locations_v1', JSON.stringify({
                        locationProvinces: this.locationProvinces,
                        locationMunicipalities: this.locationMunicipalities
                    }));
                } catch (cacheError) {
                    console.warn('Unable to cache PSGC locations:', cacheError);
                }

                if (this.locationProvinces.length < 7) {
                    throw new Error(
                        'Unable to verify all Region III provinces. ' +
                        'Check the PSGC API response.'
                    );
                }

                console.info(
                    'Region III provinces loaded:',
                    this.locationProvinces.length
                );

                console.info(
                    'Region III municipalities loaded:',
                    this.locationMunicipalities.length
                );
            } catch (error) {
                console.error('PSGC location error:', error);

                this.locationDataError =
                    'Unable to load PSGC locations. Check your internet connection.';

                this.notify(this.locationDataError);
            } finally {
                this.locationDataLoading = false;
            }
        },

        municipalityOptions(province) {
            const selectedProvince = this.locationProvinces.find(
                item => item.name === province
            );

            if (!selectedProvince) {
                return [];
            }

            return this.locationMunicipalities
                .filter(item =>
                    item.provinceCode === selectedProvince.code
                )
                .map(item => item.name)
                .sort((a, b) => a.localeCompare(b));
        },

        // --------------------------------------------------
        // PSGC: BARANGAYS
        // --------------------------------------------------

        async loadBarangaysForRow(row) {
            this.rowBarangays[row.rowKey] = [];

            if (!row.province || !row.municipality) {
                return;
            }

            const province = this.locationProvinces.find(
                item => item.name === row.province
            );

            const municipality = this.locationMunicipalities.find(
                item =>
                    item.name === row.municipality &&
                    item.provinceCode === province?.code
            );

            if (!municipality) {
                return;
            }

            row.locationLoading = true;

            try {
                const response = await fetch(
                    `https://psgc.cloud/api/v2/cities-municipalities/${encodeURIComponent(municipality.code)}/barangays`,
                    {
                        headers: {
                            'Accept': 'application/json'
                        }
                    }
                );

                if (!response.ok) {
                    throw new Error(
                        `Barangay request failed: ${response.status}`
                    );
                }

                const payload = await response.json();

                const items = Array.isArray(payload)
                    ? payload
                    : (
                        payload.data ||
                        payload.results ||
                        []
                    );

                this.rowBarangays[row.rowKey] = items
                    .map(item => this.fixPSGCName(
                        item.name ??
                        item.psgc_name ??
                        item.psgcName ??
                        ''
                    ))
                    .filter(Boolean)
                    .sort((a, b) => a.localeCompare(b));
            } catch (error) {
                console.error('Barangay loading error:', error);

                this.notify(
                    'Unable to load barangays. Please select the municipality again.'
                );
            } finally {
                row.locationLoading = false;
            }
        },

        // --------------------------------------------------
        // BULK ROWS
        // --------------------------------------------------

        emptyRow() {
            return {
                rowKey: ++this.rowSequence,
                first_name: '',
                middle_name: '',
                last_name: '',
                sex: '',
                birthday: '',
                cell_number: '',
                civil_status: '',
                occupation: '',
                spouse_name: '',
                spouse_occupation: '',
                position: '',
                barangay: '',
                municipality: '',
                province: '',
                cooperative_id: '',
                registration_date: new Date()
                    .toISOString()
                    .slice(0, 10),
                locationLoading: false
            };
        },

        openBulkModal() {
            this.bulkRows = [this.emptyRow()];
            this.bulkModalOpen = true;
        },

        addBulkRow() {
            this.bulkRows.push(this.emptyRow());
        },

        removeBulkRow(index) {
            if (this.bulkRows.length <= 1) {
                return;
            }

            const row = this.bulkRows[index];

            if (row) {
                delete this.rowBarangays[row.rowKey];
            }

            this.bulkRows.splice(index, 1);
        },

        // --------------------------------------------------
        // FARMER DISPLAY AND AGE
        // --------------------------------------------------

        farmerName(farmer) {
            return [
                farmer.first_name,
                farmer.middle_name,
                farmer.last_name
            ].filter(Boolean).join(' ');
        },

        age(birthday) {
            if (!birthday) {
                return '—';
            }

            const birthDate = new Date(birthday + 'T00:00:00');

            if (Number.isNaN(birthDate.getTime())) {
                return '—';
            }

            const today = new Date();

            let years =
                today.getFullYear() - birthDate.getFullYear();

            if (
                today.getMonth() < birthDate.getMonth() ||
                (
                    today.getMonth() === birthDate.getMonth() &&
                    today.getDate() < birthDate.getDate()
                )
            ) {
                years--;
            }

            return years >= 0 ? years : '—';
        },

        // --------------------------------------------------
        // SEARCH AND FILTERS
        // --------------------------------------------------

        filteredFarmers() {
            const query = this.filters.search.trim().toLowerCase();

            return this.farmers
                .filter(farmer => {
                    const searchable = [
                        this.farmerName(farmer),
                        farmer.id,
                        farmer.sex,
                        farmer.civil_status,
                        farmer.position,
                        farmer.cell_number,
                        farmer.occupation,
                        farmer.spouse_name,
                        farmer.spouse_occupation,
                        farmer.barangay,
                        farmer.municipality,
                        farmer.province,
                        farmer.cooperative
                    ].join(' ').toLowerCase();

                    if (query && !searchable.includes(query)) {
                        return false;
                    }

                    if (
                        this.filters.sex &&
                        farmer.sex !== this.filters.sex
                    ) {
                        return false;
                    }

                    if (
                        this.filters.municipality === '__has_municipality__' &&
                        !(farmer.municipality || '').trim()
                    ) {
                        return false;
                    }

                    if (
                        this.filters.municipality &&
                        this.filters.municipality !== '__has_municipality__' &&
                        (farmer.municipality || '').trim() !==
                            this.filters.municipality
                    ) {
                        return false;
                    }

                    const date = farmer.registration_date || '';

                    if (
                        this.filters.year &&
                        date.slice(0, 4) !== this.filters.year
                    ) {
                        return false;
                    }

                    if (
                        this.filters.month &&
                        Number(date.slice(5, 7)) !==
                            Number(this.filters.month)
                    ) {
                        return false;
                    }

                    if (
                        this.filters.quarter &&
                        Math.ceil(Number(date.slice(5, 7)) / 3) !==
                            Number(this.filters.quarter)
                    ) {
                        return false;
                    }

                    if (
                        this.filters.dateFrom &&
                        (!date || date < this.filters.dateFrom)
                    ) {
                        return false;
                    }

                    if (
                        this.filters.dateTo &&
                        (!date || date > this.filters.dateTo)
                    ) {
                        return false;
                    }

                    return true;
                })
                .sort((a, b) =>
                    this.farmerName(a).localeCompare(
                        this.farmerName(b)
                    )
                );
        },

        paginatedFarmers() {
            const start = (this.currentPage - 1) * this.perPage;

            return this.filteredFarmers().slice(
                start,
                start + this.perPage
            );
        },

        totalPages() {
            return Math.max(
                1,
                Math.ceil(
                    this.filteredFarmers().length / this.perPage
                )
            );
        },

        previousPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
            }
        },

        nextPage() {
            if (this.currentPage < this.totalPages()) {
                this.currentPage++;
            }
        },

        availableYears() {
            return [
                ...new Set(
                    this.farmers
                        .map(farmer => farmer.registration_date)
                        .filter(Boolean)
                        .map(date => date.slice(0, 4))
                )
            ].sort((a, b) => Number(b) - Number(a));
        },

        availableMunicipalities() {
            return [
                ...new Set(
                    this.farmers
                        .map(farmer =>
                            (farmer.municipality || '').trim()
                        )
                        .filter(Boolean)
                )
            ].sort((a, b) => a.localeCompare(b));
        },

        // --------------------------------------------------
        // SUMMARY CARDS
        // --------------------------------------------------

        summaryCards() {
            const records = this.filteredFarmers();

            const maleCount = records.filter(
                farmer => (farmer.sex || '').toLowerCase() === 'male'
            ).length;

            const femaleCount = records.filter(
                farmer => (farmer.sex || '').toLowerCase() === 'female'
            ).length;

            const municipalityCount = new Set(
                records
                    .map(farmer =>
                        (farmer.municipality || '').trim()
                    )
                    .filter(Boolean)
            ).size;

            return [
                {
                    key: 'all',
                    label: 'Total Farmers',
                    value: records.length,
                    note: 'Number of farmers currently shown in the table',
                    icon: 'fa-solid fa-users',
                    iconBg: 'bg-blue-50',
                    iconColor: 'text-blue-700'
                },
                {
                    key: 'male',
                    label: 'Male Farmers',
                    value: maleCount,
                    note: 'Male farmers in filtered results',
                    icon: 'fa-solid fa-person',
                    iconBg: 'bg-indigo-50',
                    iconColor: 'text-indigo-700'
                },
                {
                    key: 'female',
                    label: 'Female Farmers',
                    value: femaleCount,
                    note: 'Female farmers in filtered results',
                    icon: 'fa-solid fa-person-dress',
                    iconBg: 'bg-pink-50',
                    iconColor: 'text-pink-700'
                },
                {
                    key: 'municipalities',
                    label: 'Municipalities',
                    value: municipalityCount,
                    note: 'Distinct municipalities in filtered results',
                    icon: 'fa-solid fa-map-location-dot',
                    iconBg: 'bg-emerald-50',
                    iconColor: 'text-emerald-700'
                }
            ];
        },

        applyCardFilter(key) {
            if (this.activeCard === key) {
                this.activeCard = '';

                if (key === 'male' || key === 'female') {
                    this.filters.sex = '';
                }

                if (key === 'municipalities') {
                    this.filters.municipality = '';
                }
            } else {
                this.activeCard = key;

                if (key === 'all') {
                    this.filters.sex = '';
                    this.filters.municipality = '';
                } else if (key === 'male') {
                    this.filters.sex = 'Male';
                    this.filters.municipality = '';
                } else if (key === 'female') {
                    this.filters.sex = 'Female';
                    this.filters.municipality = '';
                } else if (key === 'municipalities') {
                    this.filters.municipality = '__has_municipality__';
                }
            }

            this.currentPage = 1;
        },

        resetFilters() {
            this.filters = {
                search: '',
                year: '',
                quarter: '',
                month: '',
                sex: '',
                municipality: '',
                dateFrom: '',
                dateTo: ''
            };

            this.activeCard = '';
            this.currentPage = 1;
        },

        // --------------------------------------------------
        // VIEW FARMER
        // --------------------------------------------------

        viewFarmer(farmer) {
            this.selectedFarmer = { ...farmer };
            this.viewModalOpen = true;
        },

        viewItems() {
            if (!this.selectedFarmer) {
                return [];
            }

            const farmer = this.selectedFarmer;

            return [
                { label: 'Farmer ID', value: farmer.id },
                { label: 'Full Name', value: this.farmerName(farmer) },
                { label: 'Sex', value: farmer.sex },
                { label: 'Birthday', value: farmer.birthday },
                { label: 'Age', value: this.age(farmer.birthday) },
                { label: 'Cell Number', value: farmer.cell_number },
                { label: 'Civil Status', value: farmer.civil_status },
                { label: 'Occupation', value: farmer.occupation },
                { label: 'Name of Spouse', value: farmer.spouse_name },
                { label: 'Spouse Occupation', value: farmer.spouse_occupation },
                { label: 'Position', value: farmer.position },
                { label: 'Barangay', value: farmer.barangay },
                { label: 'Municipality', value: farmer.municipality },
                { label: 'Province', value: farmer.province },
                { label: 'Cooperative / Association', value: farmer.cooperative },
                { label: 'Registration Date', value: farmer.registration_date }
            ];
        },

        // --------------------------------------------------
        // SAVE BULK FARMERS
        // --------------------------------------------------

        async saveBulkFarmers() {
            const validRows = [];
            const invalidRows = [];

            this.bulkRows.forEach((row, index) => {
                const empty =
                    !String(row.first_name || '').trim() &&
                    !String(row.last_name || '').trim() &&
                    !row.sex;

                if (empty) {
                    return;
                }

                const valid =
                    String(row.first_name || '').trim() &&
                    String(row.last_name || '').trim() &&
                    ['Male', 'Female'].includes(row.sex) &&
                    row.registration_date;

                if (!valid) {
                    invalidRows.push(index + 1);
                    return;
                }

                validRows.push({ ...row });
            });

            if (invalidRows.length) {
                this.notify(
                    'Complete required fields in row(s): ' +
                    invalidRows.join(', ')
                );
                return;
            }

            if (!validRows.length) {
                this.notify(
                    'Enter at least one complete farmer record.'
                );
                return;
            }

            try {
                const payload = await this.request(this.api.bulk, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        farmers: validRows.map(({ rowKey, locationLoading, ...row }) => {
                            // Read the selected dropdown value explicitly and send a numeric DB ID.
                            const rawCooperativeId = row.cooperative_id;
                            const cooperativeId = rawCooperativeId === '' ||
                                rawCooperativeId === null ||
                                rawCooperativeId === undefined
                                    ? null
                                    : Number(rawCooperativeId);

                            // Do not silently turn a selected-but-invalid ID into null.
                            if (rawCooperativeId !== '' && rawCooperativeId !== null && rawCooperativeId !== undefined &&
                                (!Number.isSafeInteger(cooperativeId) || cooperativeId <= 0)) {
                                throw new Error('Invalid cooperative selection in farmer row. Please select the cooperative again.');
                            }

                            return {
                                ...row,
                                cooperative_id: cooperativeId
                            };
                        })
                    })
                });

                this.bulkModalOpen = false;

                await this.loadFarmers();
                this.resetFilters();

                this.notify(
                    (payload.count ?? validRows.length) +
                    ' farmer record(s) saved to database.'
                );
            } catch (error) {
                console.error('Bulk save error:', error);

                this.notify(
                    'Unable to save farmers: ' + error.message
                );
            }
        },

        // --------------------------------------------------
        // COOPERATIVE ID NORMALIZATION
        // --------------------------------------------------

        normalizeCooperativeId(rawValue, allowNull = true) {
            if (
                rawValue === '' ||
                rawValue === null ||
                rawValue === undefined
            ) {
                if (!allowNull) {
                    throw new Error(
                        'Please select a cooperative before saving.'
                    );
                }

                return null;
            }

            // Some existing records may return the cooperative name/code
            // instead of the numeric foreign key. Resolve those values to
            // the actual database ID before sending the request.
            let value = rawValue;
            if (typeof value === 'object' && value !== null) {
                value = value.id ?? value.cooperative_id ?? value.db_id ??
                    value.cooperative_code ?? value.coop_code ?? '';
            }

            value = String(value ?? '').trim();

            if (/^[1-9]\d*$/.test(value)) {
                const id = Number(value);
                if (Number.isSafeInteger(id)) return id;
            }

            const normalizeText = text => String(text ?? '')
                .trim()
                .toLowerCase()
                .replace(/\s+/g, ' ');

            const match = this.cooperatives.find(cooperative => {
                const candidates = [
                    cooperative.cooperative_name,
                    cooperative.name,
                    cooperative.cooperative_code,
                    cooperative.coop_code,
                    cooperative.herd_code,
                    cooperative.code
                ];

                return candidates.some(candidate =>
                    normalizeText(candidate) === normalizeText(value)
                );
            });

            if (match && /^[1-9]\d*$/.test(String(match.id ?? '').trim())) {
                const matchedId = Number(match.id);
                if (Number.isSafeInteger(matchedId)) return matchedId;
            }

            throw new Error(
                `Cooperative value "${value}" is not a numeric database ID and could not be matched to a cooperative. Please choose the cooperative again.`
            );
        },

        // --------------------------------------------------
        // EDIT FARMER
        // --------------------------------------------------

        editFarmer(farmer) {
            const databaseId = farmer.db_id ?? farmer.database_id ?? farmer.id_db;

            if (
                databaseId === null ||
                databaseId === undefined ||
                String(databaseId).trim() === ''
            ) {
                this.notify('Unable to identify the database farmer ID.');
                return;
            }

            this.editingFarmerId = databaseId;

            // Prefer the saved foreign key, then the cooperative relation ID,
            // and finally match the displayed cooperative name/code.
            let editCooperativeId =
                farmer.cooperative_id ??
                farmer.cooperative_record?.id ??
                farmer.cooperative?.id ??
                farmer.cooperative_record?.cooperative_id ??
                '';

            const rawCooperativeText =
                typeof farmer.cooperative === 'string'
                    ? farmer.cooperative
                    : (
                        farmer.cooperative_record?.cooperative_name ||
                        farmer.cooperative?.cooperative_name ||
                        farmer.cooperative_name ||
                        ''
                    );

            if (!/^[1-9]\d*$/.test(String(editCooperativeId ?? '').trim())) {
                const normalizeText = text => String(text ?? '')
                    .trim().toLowerCase().replace(/\s+/g, ' ');
                const cooperativeMatch = this.cooperatives.find(item =>
                    normalizeText(item.cooperative_name) === normalizeText(rawCooperativeText) ||
                    normalizeText(item.cooperative_code) === normalizeText(rawCooperativeText) ||
                    normalizeText(item.coop_code) === normalizeText(rawCooperativeText) ||
                    normalizeText(item.herd_code) === normalizeText(rawCooperativeText)
                );
                editCooperativeId = cooperativeMatch?.id || '';
            }

            this.editForm = {
                first_name: farmer.first_name || '',
                middle_name: farmer.middle_name || '',
                last_name: farmer.last_name || '',
                sex: ['Male', 'Female'].includes(farmer.sex) ? farmer.sex : '',
                birthday: farmer.birthday ? String(farmer.birthday).slice(0, 10) : '',
                cell_number: farmer.cell_number || '',
                civil_status: farmer.civil_status || '',
                occupation: farmer.occupation || '',
                cooperative_id:
                    editCooperativeId === null ||
                    editCooperativeId === undefined ||
                    editCooperativeId === ''
                        ? ''
                        : String(editCooperativeId),
                position: farmer.position || '',
                spouse_name: farmer.spouse_name || '',
                spouse_occupation: farmer.spouse_occupation || '',
                barangay: farmer.barangay || '',
                municipality: farmer.municipality || '',
                province: farmer.province || '',
                registration_date: farmer.registration_date
                    ? String(farmer.registration_date).slice(0, 10)
                    : ''
            };

            this.editModalOpen = true;
        },

        async saveFarmerUpdate() {
            if (
                this.editingFarmerId === null ||
                this.editingFarmerId === undefined ||
                String(this.editingFarmerId).trim() === ''
            ) {
                this.notify('Unable to identify the farmer record.');
                return;
            }

            const form = { ...this.editForm };

            if (
                !String(form.first_name || '').trim() ||
                !String(form.last_name || '').trim() ||
                !['Male', 'Female'].includes(form.sex) ||
                !form.registration_date
            ) {
                this.notify(
                    'Complete First Name, Last Name, Sex, and Registration Date.'
                );
                return;
            }

            try {
                form.first_name = String(form.first_name).trim();
                form.middle_name = String(form.middle_name || '').trim() || null;
                form.last_name = String(form.last_name).trim();
                form.cooperative_id = this.normalizeCooperativeId(
                    form.cooperative_id,
                    true
                );

                this.isSavingEdit = true;

                console.log('Updating farmer:', {
                    farmer_id: this.editingFarmerId,
                    cooperative_id: form.cooperative_id,
                    request_url: `${this.api.update}/${this.editingFarmerId}`
                });

                await this.request(
                    `${this.api.update}/${encodeURIComponent(this.editingFarmerId)}`,
                    {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(form)
                    }
                );

                this.editModalOpen = false;
                await this.loadFarmers();
                this.notify('Farmer record updated successfully.');
            } catch (error) {
                console.error('Farmer update error:', error);
                this.notify('Unable to update farmer: ' + error.message);
            } finally {
                this.isSavingEdit = false;
            }
        },
        // --------------------------------------------------
        // DELETE FARMER
        // --------------------------------------------------

        async deleteFarmer(farmer) {
            if (!confirm(
                'Delete the record for ' +
                this.farmerName(farmer) +
                '?'
            )) {
                return;
            }

            try {
                await this.request(
                    `${this.api.update}/${farmer.db_id}`,
                    {
                        method: 'DELETE'
                    }
                );

                await this.loadFarmers();

                this.currentPage = Math.min(
                    this.currentPage,
                    this.totalPages()
                );

                this.notify('Farmer record deleted.');
            } catch (error) {
                this.notify(
                    'Unable to delete farmer: ' + error.message
                );
            }
        },

        // --------------------------------------------------
        // EXCEL IMPORT
        // --------------------------------------------------

        openImportModal() {
            this.importFile = null;
            this.importCooperativeId = '';
            this.importReady = false;
            this.importing = false;
            this.loadingImport = false;
            this.importMessage = '';
            this.importModalOpen = true;
        },

        closeImportModal() {
            if (this.importing || this.loadingImport) return;

            this.importFile = null;
            this.importCooperativeId = '';
            this.importReady = false;
            this.importMessage = '';
            this.importModalOpen = false;

            const input = this.$root.querySelector('input[type="file"][accept*=".xlsx"]');
            if (input) input.value = '';
        },

        async handleImportFile(event) {
            const file = event.target.files?.[0];
            this.importFile = null;
            this.importReady = false;
            this.importMessage = '';

            if (!file) return;

            const extension = file.name.split('.').pop().toLowerCase();
            if (!['xlsx', 'xls'].includes(extension)) {
                this.importMessage = 'Please select an Excel file (.xlsx or .xls).';
                event.target.value = '';
                return;
            }

            if (file.size === 0) {
                this.importMessage = 'The selected file is empty.';
                event.target.value = '';
                return;
            }

            if (file.size > 20 * 1024 * 1024) {
                this.importMessage = 'The file exceeds the 20 MB limit.';
                event.target.value = '';
                return;
            }

            this.importFile = file;
            this.importReady = true;
            this.importMessage = `${file.name} selected (${(file.size / 1024).toFixed(1)} KB). Click Import Excel to upload and validate the workbook.`;
        },

        async saveImportedFarmers() {
            if (!this.importFile || !this.importReady || this.importing || this.loadingImport) return;

            if (!this.importCooperativeId) {
                this.importMessage = 'Select the cooperative before importing farmers.';
                return;
            }

            if (!this.api.import) {
                this.importMessage = 'Import URL is missing. Check data-farmer-import-url on the Blade root element.';
                return;
            }

            this.importing = true;
            this.loadingImport = true;
            this.importMessage = 'Uploading Excel file, validating records, and saving to MySQL...';

            try {
                const formData = new FormData();
                formData.append('file', this.importFile);
                formData.append('cooperative_id', this.importCooperativeId);

                const response = await fetch(this.api.import, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf()
                    },
                    body: formData
                });

                const payload = await response.json().catch(() => ({}));
                if (!response.ok || payload.success !== true) {
                    let message = payload.message || 'Excel import failed.';
                    if (payload.errors) {
                        const details = Object.values(payload.errors).flat().join('\n');
                        if (details) message += '\n' + details;
                    }
                    throw new Error(message);
                }

                this.importMessage = payload.message || `${payload.count ?? 0} farmer record(s) imported successfully.`;
                this.importReady = false;
                this.importFile = null;

                const input = this.$root.querySelector('input[type="file"][accept*=".xlsx"]');
                if (input) input.value = '';

                await this.loadFarmers();
                this.resetFilters();
                this.currentPage = 1;
                this.notify(this.importMessage);
                this.importModalOpen = false;
            } catch (error) {
                console.error('Excel import error:', error);
                this.importMessage = 'Import failed: ' + error.message;
                this.importReady = Boolean(this.importFile);
            } finally {
                this.importing = false;
                this.loadingImport = false;
            }
        },

        // --------------------------------------------------
        // CSV EXPORT
        // --------------------------------------------------

        csvEscape(value) {
            return '"' +
                String(value ?? '').replace(/"/g, '""') +
                '"';
        },

        exportCSV() {
            const headers = [
                'farmer_code',
                'last_name',
                'first_name',
                'middle_name',
                'birthday',
                'cell_number',
                'sex',
                'civil_status',
                'occupation',
                'cooperative',
                'position',
                'spouse_name',
                'spouse_occupation',
                'barangay',
                'municipality',
                'province',
                'registration_date'
            ];

            const rows = this.filteredFarmers().map(farmer =>
                headers.map(header =>
                    this.csvEscape(
                        header === 'farmer_code'
                            ? farmer.id
                            : farmer[header]
                    )
                ).join(',')
            );

            const csv =
                '\uFEFF' +
                headers.join(',') +
                '\r\n' +
                rows.join('\r\n');

            const blob = new Blob([csv], {
                type: 'text/csv;charset=utf-8;'
            });

            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');

            link.href = url;
            link.download = 'cbed_farmer_records.csv';

            document.body.appendChild(link);
            link.click();
            link.remove();

            URL.revokeObjectURL(url);

            this.notify('Farmer records exported to CSV.');
        },

        // --------------------------------------------------
        // PDF / PRINT REPORT
        // --------------------------------------------------

        downloadPDFReport() {
            const rows = this.filteredFarmers();

            const html = `
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>CBED Farmer Report</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            padding: 20px;
                        }
                        h1 { color: #0b1f3a; }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            font-size: 10px;
                        }
                        th, td {
                            border: 1px solid #ccc;
                            padding: 5px;
                            text-align: left;
                        }
                        th {
                            background: #0b1f3a;
                            color: white;
                        }
                    </style>
                </head>
                <body>
                    <h1>CBED DATA SYSTEM</h1>
                    <h2>Farmer Records (${rows.length})</h2>

                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Sex</th>
                                <th>Cooperative</th>
                                <th>Barangay</th>
                                <th>Municipality</th>
                                <th>Province</th>
                                <th>Registration Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows.map(farmer => `
                                <tr>
                                    <td>${this.escapeHTML(farmer.id)}</td>
                                    <td>${this.escapeHTML(this.farmerName(farmer))}</td>
                                    <td>${this.escapeHTML(farmer.sex)}</td>
                                    <td>${this.escapeHTML(farmer.cooperative)}</td>
                                    <td>${this.escapeHTML(farmer.barangay)}</td>
                                    <td>${this.escapeHTML(farmer.municipality)}</td>
                                    <td>${this.escapeHTML(farmer.province)}</td>
                                    <td>${this.escapeHTML(farmer.registration_date)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>

                    <script>
                        window.onload = () => window.print();
                    <\/script>
                </body>
                </html>
            `;

            const reportWindow = window.open('', '_blank');

            if (!reportWindow) {
                this.notify('Allow pop-ups to create the report.');
                return;
            }

            reportWindow.document.write(html);
            reportWindow.document.close();
        },

        escapeHTML(value) {
            return String(value ?? '').replace(
                /[&<>"']/g,
                character => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                })[character]
            );
        },

        // --------------------------------------------------
        // NOTIFICATIONS
        // --------------------------------------------------

        notify(message) {
            this.toast.message = message;
            this.toast.show = true;

            if (this.toastTimer) {
                clearTimeout(this.toastTimer);
            }

            this.toastTimer = setTimeout(() => {
                this.toast.show = false;
            }, 3500);
        }
    }));
};

// Register whether Alpine loads before or after this Blade script.
if (window.Alpine) {
    registerFarmerPage();
} else {
    document.addEventListener('alpine:init', registerFarmerPage, { once: true });
}
</script>
@endpush
