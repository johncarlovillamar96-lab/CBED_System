
{{-- resources/views/cooperative.blade.php --}}

@extends('layouts.admin')

@section('title', 'Cooperative Management | CBED')
@section('page-heading', 'Cooperative Management')

@section('content')
<div x-data="cooperativePage()" x-init="init()" x-cloak
     data-cooperative-index-url="{{ route('admin.cooperatives.data') }}"
     data-cooperative-store-url="{{ route('admin.cooperatives.store') }}"
     data-cooperative-update-url="{{ url('/cooperatives') }}"
     data-csrf-token="{{ csrf_token() }}"
     class="management-page cooperative-dashboard min-w-0 bg-slate-50 text-slate-800">

    {{-- Header --}}
    <div class="management-hero mb-6 flex flex-col gap-4 rounded-2xl p-5 text-white shadow-lg lg:flex-row lg:items-center lg:justify-between sm:p-6">
        <div>
            <div class="mb-2 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <i class="fa-solid fa-house"></i>
                <span>/</span>
                <span>CBED Data System</span>
                <span>/</span>
                <span class="font-semibold text-slate-700">Cooperatives</span>
            </div>

            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                Cooperative Management
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Manage cooperative registration, accreditation, classification, and location records.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="button" @click="downloadPDFReport()"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-100">
                <i class="fa-solid fa-file-pdf text-red-600"></i> Download PDF Report
            </button>

            <a href="{{ route('admin.cooperatives.export') }}"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-100">
                <i class="fa-solid fa-file-excel text-emerald-700"></i> Export XLSX
            </a>

            <button type="button" @click="showImportModal = true; importFileName = ''"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-100">
                <i class="fa-solid fa-file-import text-blue-700"></i> Import File
            </button>

            <button type="button" @click="openAddModal()"
                class="inline-flex items-center gap-2 rounded-lg bg-[#0b1f3a] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#122b4d]">
                <i class="fa-solid fa-plus"></i> Add Cooperative
            </button>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <template x-for="card in summaryCards()" :key="card.label">
            <div class="management-summary-card rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-500" x-text="card.label"></p>
                        <p class="mt-2 text-3xl font-bold text-slate-900" x-text="card.value"></p>
                        <p class="mt-1 text-xs text-slate-500" x-text="card.description"></p>
                    </div>
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl"
                         :class="card.iconBg">
                        <i :class="card.icon + ' text-xl ' + card.iconColor"></i>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Directory --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Cooperative Directory</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Search, filter, view, and maintain cooperative registration records.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700">
                    <span x-text="filteredCooperatives().length"></span> records
                </span>
                <button type="button" @click="resetFilters()"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                    <i class="fa-solid fa-rotate-left mr-1"></i> Reset Filters
                </button>
            </div>
        </div>

        {{-- Filters --}}
        <div class="grid grid-cols-1 gap-3 border-b border-slate-200 bg-slate-50 p-5 sm:grid-cols-2 xl:grid-cols-5">
            <div class="xl:col-span-2">
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Search Cooperative
                </label>
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="search" x-model="filters.search" @input="page = 1"
                        placeholder="Name, CDA number, herd code..."
                        class="w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Region
                </label>
                <select x-model="filters.region" @change="page = 1; filters.province = ''; filters.municipality = ''"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500">
                    <option value="">All Regions</option>
                    <template x-for="item in availableValues('region')" :key="item">
                        <option :value="item" x-text="item"></option>
                    </template>
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Province
                </label>
                <select x-model="filters.province" @change="page = 1; filters.municipality = ''"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500">
                    <option value="">All Provinces</option>
                    <template x-for="item in availableValues('province')" :key="item">
                        <option :value="item" x-text="item"></option>
                    </template>
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Year Enrolled
                </label>
                <div class="flex items-center gap-2">
                    <select x-model="filters.year" @change="page = 1"
                        class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500">
                        <option value="">All Years</option>
                        <template x-for="year in availableYears()" :key="year">
                            <option :value="year" x-text="year"></option>
                        </template>
                    </select>
                    <a href="{{ route('admin.cooperatives.export') }}" title="Download cooperative records as Excel"
                        class="inline-flex shrink-0 items-center justify-center rounded-lg bg-[#0b1f3a] px-3 py-2.5 text-white hover:bg-[#122b4d]">
                        <i class="fa-solid fa-file-excel"></i>
                    </a>
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Municipality
                </label>
                <select x-model="filters.municipality" @change="page = 1"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500">
                    <option value="">All Municipalities</option>
                    <template x-for="item in availableValues('municipality')" :key="item">
                        <option :value="item" x-text="item"></option>
                    </template>
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Cooperative Type
                </label>
                <select x-model="filters.type" @change="page = 1"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500">
                    <option value="">All Types</option>
                    <template x-for="item in coopTypes" :key="item">
                        <option :value="item" x-text="item"></option>
                    </template>
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Accreditation
                </label>
                <select x-model="filters.accreditation" @change="page = 1"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500">
                    <option value="">All Statuses</option>
                    <template x-for="item in accreditationOptions" :key="item">
                        <option :value="item" x-text="item"></option>
                    </template>
                </select>
            </div>
        </div>

        {{-- Compliance completion filters --}}
        <div class="border-b border-slate-200 bg-white p-5">
            <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="font-bold text-slate-800">Compliance Completion Filters</h3>
                    <p class="mt-1 text-xs text-slate-500">Filter cooperatives by the completion status of each requirement. Filters can be combined.</p>
                </div>
                <div class="text-sm text-slate-600">
                    Matching records: <span class="font-bold text-slate-900" x-text="filteredCooperatives().length"></span>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <template x-for="item in complianceFilters" :key="item.key">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500" x-text="item.label"></label>
                        <select x-model="filters[item.key]" @change="page = 1"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            <option value="">All</option>
                            <option value="complete">Complete</option>
                            <option value="incomplete">Incomplete</option>
                        </select>
                    </div>
                </template>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="w-full min-w-[2500px] text-left text-sm">
                <thead class="bg-slate-100 text-xs uppercase tracking-wider text-slate-600">
                    <tr>
                        <th class="px-5 py-4 font-bold">CDA Registration No.</th>
                        <th class="px-5 py-4 font-bold">Cooperative / Herd Code</th>
                        <th class="px-5 py-4 font-bold">Cooperative Name</th>
                        <th class="px-5 py-4 font-bold">General Accreditation</th>
                        <th class="px-5 py-4 font-bold">Coop Type</th>
                        <th class="px-5 py-4 font-bold">Category</th>
                        <th class="px-5 py-4 font-bold">Region</th>
                        <th class="px-5 py-4 font-bold">Province</th>
                        <th class="px-5 py-4 font-bold">Municipality</th>
                        <th class="px-5 py-4 font-bold">FDA</th>
                        <th class="px-5 py-4 font-bold">NDA - LTO</th>
                        <th class="px-5 py-4 font-bold">NCISA</th>
                        <th class="px-5 py-4 font-bold">PhilGEPS</th>
                        <th class="px-5 py-4 font-bold">iREB</th>
                        <th class="px-5 py-4 font-bold">Milk Testing</th>
                        <th class="px-5 py-4 font-bold">Year Enrolled</th>
                        <th class="px-5 py-4 text-center font-bold">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    <template x-for="coop in paginatedCooperatives()" :key="coop.id">
                        <tr class="transition hover:bg-slate-50">
                            <td class="whitespace-nowrap px-5 py-4 text-slate-700"
                                x-text="coop.cda_registration_no || '—'"></td>
                            <td class="whitespace-nowrap px-5 py-4 font-semibold text-[#0b1f3a]"
                                x-text="coop.id"></td>

                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-800">
                                        <i class="fa-solid fa-people-group"></i>
                                    </div>
                                    <div class="max-w-xs">
                                        <p class="font-semibold text-slate-800" x-text="coop.name"></p>
                                    </div>
                                </div>
                            </td>

                            <td class="whitespace-nowrap px-5 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="accreditationClass(coop.accreditation_status)"
                                    x-text="coop.accreditation_status || 'Not specified'"></span>
                            </td>
                           

                            <td class="whitespace-nowrap px-5 py-4 text-slate-600"
                                x-text="coop.coop_type || '—'"></td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600"
                                x-text="coop.category || '—'"></td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600"
                                x-text="coop.region || '—'"></td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600"
                                x-text="coop.province || '—'"></td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600"
                                x-text="coop.municipality || '—'"></td>

                            <td class="whitespace-nowrap px-5 py-4 text-slate-600" x-text="coop.fda_status || '—'"></td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600" x-text="coop.nda_lto_status || '—'"></td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600" x-text="coop.ncisa_status || '—'"></td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600" x-text="coop.philgeps_status || '—'"></td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600" x-text="coop.ireb_status || '—'"></td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600" x-text="coop.milk_testing_status || '—'"></td>


                            <td class="whitespace-nowrap px-5 py-4 text-slate-600"
                                x-text="coop.year_enrolled || '—'"></td>

                            <td class="whitespace-nowrap px-5 py-4">
                                <div class="flex items-center justify-center gap-2">
                                    <button type="button" @click="viewCooperative(coop)"
                                        title="View details"
                                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                    <button type="button" @click="openEditModal(coop)"
                                        title="Edit cooperative"
                                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:border-amber-200 hover:bg-amber-50 hover:text-amber-700">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <tr x-show="filteredCooperatives().length === 0">
                        <td colspan="11" class="px-5 py-16 text-center">
                            <i class="fa-solid fa-people-group mb-3 text-3xl text-slate-300"></i>
                            <p class="font-semibold text-slate-700">No cooperatives found</p>
                            <p class="mt-1 text-sm text-slate-500">
                                Try changing your search or filters.
                            </p>
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
                    x-text="filteredCooperatives().length ? (page - 1) * perPage + 1 : 0"></span>
                to
                <span class="font-semibold text-slate-700"
                    x-text="Math.min(page * perPage, filteredCooperatives().length)"></span>
                of
                <span class="font-semibold text-slate-700" x-text="filteredCooperatives().length"></span>
                cooperatives
            </p>

            <div class="flex items-center gap-2">
                <button type="button" @click="previousPage()" :disabled="page <= 1"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40">
                    <i class="fa-solid fa-chevron-left mr-1"></i> Previous
                </button>
                <span class="whitespace-nowrap px-2 text-sm font-semibold text-slate-700">
                    Page <span x-text="page"></span> of <span x-text="totalPages()"></span>
                </span>
                <button type="button" @click="nextPage()" :disabled="page >= totalPages()"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40">
                    Next <i class="fa-solid fa-chevron-right ml-1"></i>
                </button>
            </div>
        </div>
    </div>

    {{-- Add / Edit Modal --}}
    <div x-show="showFormModal" x-cloak
         class="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-slate-950/50 p-4"
         @keydown.escape.window="showFormModal = false"
         @click.self="showFormModal = false"
         x-transition.opacity>

        <div class="bg-white rounded-2xl shadow-xl w-full max-w-7xl max-h-[90vh] overflow-y-auto"
             @click.stop x-transition.scale.origin.center>

            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                <div>
                    <h2 class="text-xl font-bold text-slate-900"
                        x-text="editing ? 'Edit Cooperative' : 'Add Cooperative'"></h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Enter the cooperative's official registration and location information.
                    </p>
                </div>
                <button type="button" @click="showFormModal = false"
                    class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form @submit.prevent="saveCooperative()">
                <div class="max-h-[70vh] overflow-y-auto p-6">

                    <div class="mb-5 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-[#0b1f3a]">
                        <i class="fa-solid fa-building"></i>
                        Cooperative Registration Information
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-2">

                        {{-- 1 --}}
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">
                                CDA Registration No.
                            </label>
                            <input type="text" x-model.trim="form.cda_registration_no"
                                placeholder="Enter CDA registration number"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        </div>

                        {{-- 2 --}}
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">
                                Cooperative Code / Herd Code *
                            </label>
                            <input type="text" x-model.trim="form.id" required
                                placeholder="Enter cooperative / herd code"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        </div>

                        {{-- 3 --}}
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">
                                Cooperative Name *
                            </label>
                            <input type="text" x-model.trim="form.name" required
                                placeholder="Official cooperative name"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        </div>

                        {{-- 4 --}}
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">
                                Accreditation Status
                            </label>
                            <select x-model="form.accreditation_status"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500">
                                <option value="">Select status</option>
                                <template x-for="item in accreditationOptions" :key="item">
                                    <option :value="item" x-text="item"></option>
                                </template>
                            </select>
                        </div>

                        {{-- 5 --}}
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">
                                Cooperative Type
                            </label>
                            <select x-model="form.coop_type"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500">
                                <option value="">Select type</option>
                                <template x-for="item in coopTypes" :key="item">
                                    <option :value="item" x-text="item"></option>
                                </template>
                            </select>
                        </div>

                        {{-- 6 --}}
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">
                                Category
                            </label>
                            <select x-model="form.category"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500">
                                <option value="">Select category</option>
                                <template x-for="item in categories" :key="item">
                                    <option :value="item" x-text="item"></option>
                                </template>
                            </select>
                        </div>

                        {{-- 7 --}}
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">
                                Date Established
                            </label>
                            <input type="date" x-model="form.date_established"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        </div>

                        {{-- 8 --}}
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">
                                Region
                            </label>
                            <input type="text" x-model.trim="form.region"
                                placeholder="e.g. Central Luzon"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        </div>

                        {{-- 9 --}}
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">
                                Province
                            </label>
                            <select x-model="form.province" @change="form.municipality = ''; form.barangay = ''; form.region = 'Central Luzon'"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                <option value="" x-text="locationDataLoading ? 'Loading provinces...' : (locationDataError ? 'Location list unavailable' : 'Select Province')"></option>
                                <template x-for="province in provinceOptions" :key="province">
                                    <option :value="province" x-text="province"></option>
                                </template>
                            </select>
                        </div>

                        {{-- 10 --}}
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">
                                City / Town / Municipality
                            </label>
                            <select x-model="form.municipality" @change="form.barangay = ''; loadBarangays()" :disabled="!form.province"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:bg-slate-100">
                                <option value="" x-text="locationDataLoading ? 'Loading locations...' : 'Select Municipality / City'"></option>
                                <template x-for="municipality in municipalityOptions(form.province)" :key="municipality">
                                    <option :value="municipality" x-text="municipality"></option>
                                </template>
                            </select>
                        </div>

                        {{-- 11 --}}
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">
                                Barangay
                            </label>
                            <select x-model="form.barangay" :disabled="!form.municipality"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:bg-slate-100">
                                <option value="" x-text="barangaysLoading ? 'Loading barangays...' : 'Select Barangay'"></option>
                                <template x-for="barangay in selectedBarangays" :key="barangay">
                                    <option :value="barangay" x-text="barangay"></option>
                                </template>
                            </select>
                            <p x-show="locationDataError" x-text="locationDataError" class="mt-1 text-xs text-red-600"></p>
                        </div>

                        {{-- 13 --}}
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">
                                Year Enrolled
                            </label>
                            <input type="number" x-model.number="form.year_enrolled"
                                min="1900" max="2200" step="1"
                                placeholder="e.g. 2024"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        </div>

                        {{-- Accreditation and compliance tracking --}}
                        <div class="sm:col-span-2 mt-2 border-t border-slate-200 pt-4">
                            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-700">Accreditations and Compliance</h3>
                            <p class="mt-1 text-xs text-slate-500">Record each requirement separately. Use Not Applicable when a requirement does not apply.</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">FDA</label>
                            <select x-model="form.fda_status" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500"><option value="">Select status</option><template x-for="item in complianceStatusOptions" :key="item"><option :value="item" x-text="item"></option></template></select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">NDA - LTO</label>
                            <select x-model="form.nda_lto_status" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500"><option value="">Select status</option><template x-for="item in complianceStatusOptions" :key="item"><option :value="item" x-text="item"></option></template></select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">NCISA</label>
                            <select x-model="form.ncisa_status" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500"><option value="">Select status</option><template x-for="item in complianceStatusOptions" :key="item"><option :value="item" x-text="item"></option></template></select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">PhilGEPS</label>
                            <select x-model="form.philgeps_status" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500"><option value="">Select status</option><template x-for="item in complianceStatusOptions" :key="item"><option :value="item" x-text="item"></option></template></select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">iREB</label>
                            <select x-model="form.ireb_status" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500"><option value="">Select status</option><template x-for="item in complianceStatusOptions" :key="item"><option :value="item" x-text="item"></option></template></select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">Milk Testing</label>
                            <select x-model="form.milk_testing_status" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500"><option value="">Select status</option><template x-for="item in complianceStatusOptions" :key="item"><option :value="item" x-text="item"></option></template></select>
                        </div>

                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end">
                    <button type="button" @click="showFormModal = false"
                        class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                        Cancel
                    </button>
                    <button type="submit"
                        class="rounded-lg bg-[#0b1f3a] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#122b4d]">
                        <i class="fa-solid fa-floppy-disk mr-2"></i>
                        <span x-text="editing ? 'Update Cooperative' : 'Save Cooperative'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- View Details Modal --}}
    <div x-show="showDetailModal" x-cloak
         class="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-slate-950/50 p-4"
         @keydown.escape.window="showDetailModal = false"
         @click.self="showDetailModal = false"
         x-transition.opacity>

        <div class="my-6 w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl"
             @click.stop x-transition.scale.origin.center>

            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">Cooperative Details</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Official cooperative registration and location information.
                    </p>
                </div>
                <button type="button" @click="showDetailModal = false"
                    class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <div class="max-h-[70vh] overflow-y-auto p-6" x-show="selectedCooperative">
                <div class="mb-6 flex items-center gap-4 rounded-xl bg-slate-50 p-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-blue-100 text-blue-800">
                        <i class="fa-solid fa-people-group text-2xl"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-lg font-bold text-slate-900"
                            x-text="selectedCooperative?.name"></h3>
                        <p class="mt-1 text-sm text-slate-500">
                            Cooperative / Herd Code:
                            <span class="font-semibold text-[#0b1f3a]"
                                x-text="selectedCooperative?.id"></span>
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <template x-for="field in detailFields()" :key="field.label">
                        <div :class="field.wide ? 'sm:col-span-2 lg:col-span-3' : ''">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                                x-text="field.label"></p>
                            <p class="mt-1 break-words font-medium text-slate-800 whitespace-pre-wrap"
                                x-text="field.value || '—'"></p>
                        </div>
                    </template>
                </div>
            </div>

            <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-6 py-4">
                <button type="button" @click="showDetailModal = false"
                    class="rounded-lg bg-[#0b1f3a] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#122b4d]">
                    Close
                </button>
            </div>
        </div>
    </div>

    {{-- Toast --}}
    <div x-show="toast.show" x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-y-3 opacity-0"
         x-transition:enter-end="translate-y-0 opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed bottom-5 right-5 z-[200] flex max-w-sm items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-xl">

        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
             :class="toast.type === 'error' ? 'bg-red-100 text-red-600' : 'bg-emerald-100 text-emerald-600'">
            <i :class="toast.type === 'error' ? 'fa-solid fa-circle-exclamation' : 'fa-solid fa-circle-check'"></i>
        </div>

        <div class="min-w-0 flex-1">
            <p class="font-bold text-slate-900"
                x-text="toast.type === 'error' ? 'Unable to complete' : 'Success'"></p>
            <p class="mt-1 text-sm text-slate-600" x-text="toast.message"></p>
        </div>

        <button type="button" @click="toast.show = false"
            class="text-slate-400 hover:text-slate-700">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    {{-- Printable PDF Report: use browser Print > Save as PDF --}}
    <section id="cooperative-pdf-report" x-show="printReport" x-cloak aria-hidden="true">
        <div class="pdf-report-header">
            <h1>CBED Data System</h1>
            <h2>Cooperative Management Report</h2>
            <p>Generated: <span x-text="reportGeneratedAt"></span></p>
            <p>Report scope: <span x-text="filteredCooperatives().length"></span> cooperative record(s) matching current filters</p>
        </div>

        <div class="pdf-summary-grid">
            <div class="pdf-summary-card">
                <span>Total Cooperatives</span>
                <strong x-text="filteredCooperatives().length"></strong>
            </div>
            <div class="pdf-summary-card">
                <span>Provinces Covered</span>
                <strong x-text="new Set(filteredCooperatives().map(c => c.province).filter(Boolean)).size"></strong>
            </div>
            <div class="pdf-summary-card">
                <span>Municipalities</span>
                <strong x-text="new Set(filteredCooperatives().map(c => c.municipality).filter(Boolean)).size"></strong>
            </div>
            <div class="pdf-summary-card">
                <span>Accredited</span>
                <strong x-text="filteredCooperatives().filter(c => c.accreditation_status === 'Accredited').length"></strong>
            </div>
        </div>

        <table class="pdf-report-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>CDA Registration No.</th>
                    <th>Cooperative / Herd Code</th>
                    <th>Cooperative Name</th>
                    <th>General Accreditation</th>
                    <th>FDA</th>
                    <th>NDA - LTO</th>
                    <th>NCISA</th>
                    <th>PhilGEPS</th>
                    <th>iREB</th>
                    <th>Milk Testing</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Region</th>
                    <th>Province</th>
                    <th>Municipality</th>
                    <th>Year Enrolled</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(coop, index) in filteredCooperatives()" :key="'pdf-' + coop.id">
                    <tr>
                        <td x-text="index + 1"></td>
                        <td x-text="coop.cda_registration_no || '—'"></td>
                        <td x-text="coop.id || '—'"></td>
                        <td x-text="coop.name || '—'"></td>
                        <td x-text="coop.accreditation_status || '—'"></td>
                        <td x-text="coop.fda_status || '—'"></td>
                        <td x-text="coop.nda_lto_status || '—'"></td>
                        <td x-text="coop.ncisa_status || '—'"></td>
                        <td x-text="coop.philgeps_status || '—'"></td>
                        <td x-text="coop.ireb_status || '—'"></td>
                        <td x-text="coop.milk_testing_status || '—'"></td>
                        <td x-text="coop.coop_type || '—'"></td>
                        <td x-text="coop.category || '—'"></td>
                        <td x-text="coop.region || '—'"></td>
                        <td x-text="coop.province || '—'"></td>
                        <td x-text="coop.municipality || '—'"></td>
                        <td x-text="coop.year_enrolled || '—'"></td>
                    </tr>
                </template>
                <tr x-show="filteredCooperatives().length === 0">
                    <td colspan="17">No cooperative records match the current filters.</td>
                </tr>
            </tbody>
        </table>

        <div class="pdf-report-footer">
            CBED Data System · Cooperative Management · Database records
        </div>
    </section>

    {{-- Footer --}}
    <div class="mt-6 flex flex-col gap-2 border-t border-slate-200 pt-4 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
        <p>CBED Data System · Cooperative Management</p>
        <p>Cooperative records are saved in the database</p>
    </div>
    <div x-cloak x-show="showImportModal" x-transition.opacity class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 p-4" @click.self="showImportModal = false" @keydown.escape.window="showImportModal = false">
        <section x-show="showImportModal" x-transition class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="cooperative-import-heading">
            <div class="flex items-start justify-between gap-4">
                <div><h2 id="cooperative-import-heading" class="text-xl font-bold text-slate-900">Import cooperative file</h2><p class="mt-1 text-sm text-slate-500">Select an Excel workbook or CSV, then press Import.</p></div>
                <button type="button" @click="showImportModal = false" class="rounded-lg px-2 py-1 text-slate-500 hover:bg-slate-100" aria-label="Close">✕</button>
            </div>
            <form method="POST" action="{{ route('admin.cooperatives.import') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                <label for="cooperative_import_file" class="block text-sm font-semibold text-slate-700">Import file</label>
                <input id="cooperative_import_file" name="file" type="file" accept=".xlsx,.xls,.csv,.txt" required @change="importFileName = $event.target.files[0]?.name || ''" class="block w-full rounded-lg border border-slate-300 p-2.5 text-sm">
                <p class="text-xs text-slate-500">Required columns: <b>No., Cooperative Name, Province, Municipality, Barangay</b>. Optional: <b>Coop Type</b>.</p>
                <p x-show="importFileName" x-text="importFileName" class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600"></p>
                <div class="flex justify-end gap-2 border-t pt-4">
                    <button type="button" @click="showImportModal = false" class="rounded-lg border px-4 py-2 text-sm font-semibold">Cancel</button>
                    <button :disabled="!importFileName" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50"><i class="fa-solid fa-file-import mr-1"></i> Import file</button>
                </div>
            </form>
        </section>
    </div>
</div>
@endsection

@push('styles')
<style>
    [x-cloak] { display: none !important; }

    #cooperative-pdf-report {
        display: none;
        color: #111827;
        background: #fff;
        font-family: Arial, Helvetica, sans-serif;
    }

    .pdf-report-header {
        margin-bottom: 18px;
        text-align: center;
    }

    .pdf-report-header h1 {
        margin: 0 0 4px;
        font-size: 18px;
        font-weight: 700;
    }

    .pdf-report-header h2 {
        margin: 0 0 8px;
        font-size: 15px;
        font-weight: 700;
    }

    .pdf-report-header p {
        margin: 3px 0;
        font-size: 10px;
    }

    .pdf-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 8px;
        margin-bottom: 16px;
    }

    .pdf-summary-card {
        border: 1px solid #cbd5e1;
        padding: 8px;
        text-align: center;
    }

    .pdf-summary-card span {
        display: block;
        font-size: 9px;
        color: #475569;
    }

    .pdf-summary-card strong {
        display: block;
        margin-top: 4px;
        font-size: 16px;
    }

    .pdf-report-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: auto;
        font-size: 8px;
    }

    .pdf-report-table th,
    .pdf-report-table td {
        border: 1px solid #cbd5e1;
        padding: 5px 4px;
        vertical-align: top;
        overflow-wrap: anywhere;
    }

    .pdf-report-table th {
        background: #e2e8f0 !important;
        font-weight: 700;
        text-align: left;
    }

    .pdf-report-footer {
        margin-top: 14px;
        border-top: 1px solid #cbd5e1;
        padding-top: 6px;
        font-size: 8px;
        text-align: center;
    }

    @media print {
        @page {
            size: A4 landscape;
            margin: 12mm 18mm 12mm 18mm;
        }

        body * {
            visibility: hidden !important;
        }

        #cooperative-pdf-report,
        #cooperative-pdf-report * {
            visibility: visible !important;
        }

        #cooperative-pdf-report {
            display: block !important;
            position: absolute !important;
            inset: 0 auto auto 0 !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #fff !important;
        }

        #cooperative-pdf-report .pdf-report-table thead {
            display: table-header-group;
        }

        #cooperative-pdf-report tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        #cooperative-pdf-report .pdf-report-table th {
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }
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
    .management-page .management-summary-card > div > div:last-child { background:#0b1f3a!important;color:#fff!important;border-radius:.85rem; }
    .management-page .management-summary-card > div > div:last-child [class*="text-"] { color:#fff!important; }
    .management-page .management-summary-card [class*="text-blue-"],.management-page .management-summary-card [class*="text-emerald-"],.management-page .management-summary-card [class*="text-violet-"] { color:#0b1f3a!important; }
    .management-page .overflow-hidden.rounded-xl.border { border-color:#dbe4ee;box-shadow:0 5px 20px rgba(15,23,42,.045); }
    .management-page table { border-collapse:separate;border-spacing:0; }
    .management-page table thead th { position:sticky;top:0;z-index:10;background:#0b1f3a!important;color:#fff!important;box-shadow:inset 0 -1px 0 #29486b; }
    .management-page table tbody tr:nth-child(even) { background:#f2f6fb; }
    .management-page table tbody tr:hover { background:#e5edf7; }
    .management-page input:focus,.management-page select:focus,.management-page textarea:focus { border-color:#0b1f3a!important;box-shadow:0 0 0 3px rgba(11,31,58,.13)!important;outline:none; }
    .management-page .bg-blue-700,.management-page .bg-blue-600,.management-page .bg-\[\#0b1f3a\] { background:#0b1f3a!important;color:#fff!important; }
    .management-page .text-blue-700,.management-page .text-blue-600,.management-page .text-emerald-700,.management-page .text-violet-600 { color:#0b1f3a!important; }
    .management-page .bg-blue-50,.management-page .bg-emerald-50,.management-page .bg-violet-50 { background:#f1f5f9!important; }
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
document.addEventListener('alpine:init', () => {
    Alpine.data('cooperativePage', () => ({
        cooperatives: [],

        coopTypes: [
            'Credit',
            'Multi Purpose',
            'Dairy',
            'Consumers',
            'Producers',
            'Service',
            'Marketing'
        ],

        categories: ['Primary', 'Secondary', 'Tertiary'],

        accreditationOptions: [
            'Accredited',
            'Not Accredited',
            'Pending',
            'Expired',
            'Suspended'
        ],

        complianceStatusOptions: [
            'Completed',
            'Registered',
            'Accredited',
            'Pending',
            'Not Started',
            'Expired',
            'Not Applicable'
        ],

        filters: {
            search: '',
            region: '',
            province: '',
            municipality: '',
            year: '',
            type: '',
            accreditation: '',
            fda_status: '',
            nda_lto_status: '',
            ncisa_status: '',
            philgeps_status: '',
            ireb_status: '',
            milk_testing_status: ''
        },

        complianceFilters: [
            { key: 'fda_status', label: 'FDA' },
            { key: 'nda_lto_status', label: 'NDA-LTO' },
            { key: 'ncisa_status', label: 'NCISA' },
            { key: 'philgeps_status', label: 'PHILGEPS' },
            { key: 'ireb_status', label: 'IREB' },
            { key: 'milk_testing_status', label: 'Milk Testing' }
        ],

        page: 1,
        perPage: 10,

        showFormModal: false,
        showDetailModal: false,
        showImportModal: false,
        importFileName: '',
        printReport: false,
        reportGeneratedAt: '',
        editing: false,
        editingOriginalId: null,
        editingOriginalDbId: null,

        apiUrls: {
            index: '',
            store: '',
            update: ''
        },

        isSaving: false,
        isLoadingRecords: false,
        selectedCooperative: null,
        form: {},

        toast: {
            show: false,
            message: '',
            type: 'success'
        },

        provinceOptions: [],
        locationProvinces: [],
        locationMunicipalities: [],
        locationBarangays: [],
        selectedBarangays: [],
        barangaysLoading: false,
        locationDataLoading: false,
        locationDataError: '',

        /*
         * Correct common UTF-8 decoding errors:
         * NiÃ±o -> Niño
         *
         * Also attempts to repair text that has been
         * incorrectly decoded more than once.
         */
        fixPSGCName(value) {
            let text = String(value ?? '').trim();

            for (let attempt = 0; attempt < 2; attempt++) {
                if (!/[ÃÂâ]/.test(text)) break;

                try {
                    const bytes = Uint8Array.from(
                        text,
                        character => character.charCodeAt(0)
                    );

                    const decoded = new TextDecoder('utf-8', {
                        fatal: true
                    }).decode(bytes);

                    if (decoded === text) break;

                    text = decoded;
                } catch (error) {
                    break;
                }
            }

            return text;
        },

        municipalityOptions(province) {
            if (!province) return [];

            const selected = this.locationProvinces.find(
                item => item.name === province
            );

            if (!selected) return [];

            return this.locationMunicipalities
                .filter(item =>
                    String(item.provinceCode || '') ===
                    String(selected.code)
                )
                .map(item => item.name)
                .sort((a, b) => a.localeCompare(b, 'en'));
        },

        async loadBarangays() {
            this.selectedBarangays = [];
            this.form.barangay = '';

            if (!this.form.province || !this.form.municipality) {
                return;
            }

            const selectedProvince = this.locationProvinces.find(
                item => item.name === this.form.province
            );

            const selectedMunicipality =
                this.locationMunicipalities.find(item =>
                    item.name === this.form.municipality &&
                    String(item.provinceCode || '') ===
                    String(selectedProvince?.code || '')
                );

            if (!selectedMunicipality?.code) {
                this.locationDataError =
                    'Unable to identify the selected municipality. Please select it again.';
                return;
            }

            this.barangaysLoading = true;
            this.locationDataError = '';

            try {
                const url =
                    `https://psgc.cloud/api/v2/cities-municipalities/` +
                    `${encodeURIComponent(selectedMunicipality.code)}/barangays`;

                const response = await fetch(url, {
                    headers: { 'Accept': 'application/json' }
                });

                if (!response.ok) {
                    throw new Error(
                        `Barangay request failed (${response.status})`
                    );
                }

                const payload = await response.json();

                const rows = Array.isArray(payload)
                    ? payload
                    : Array.isArray(payload.data)
                        ? payload.data
                        : Array.isArray(payload.results)
                            ? payload.results
                            : [];

                this.selectedBarangays = rows
                    .map(item => this.fixPSGCName(
                        item.name ??
                        item.psgc_name ??
                        item.psgcName ??
                        ''
                    ))
                    .filter(Boolean)
                    .sort((a, b) => a.localeCompare(b, 'en'));

                if (!this.selectedBarangays.length) {
                    this.locationDataError =
                        'No barangays were returned for this municipality. Check your internet connection or the PSGC service.';
                }
            } catch (error) {
                console.error('Unable to load barangays:', error);

                this.locationDataError =
                    'Unable to load barangays. Check your internet connection and try selecting the municipality again.';

                this.selectedBarangays = [];
            } finally {
                this.barangaysLoading = false;
            }
        },

        async loadRegionIIILocations() {
            this.locationDataLoading = true;
            this.locationDataError = '';

            try {
                const base = 'https://psgc.cloud/api/v2';

                const getList = async path => {
                    const response = await fetch(`${base}/${path}`, {
                        headers: { 'Accept': 'application/json' }
                    });

                    if (!response.ok) {
                        throw new Error(
                            `PSGC request failed (${response.status})`
                        );
                    }

                    const payload = await response.json();

                    if (Array.isArray(payload)) return payload;
                    if (Array.isArray(payload.data)) return payload.data;
                    if (Array.isArray(payload.results)) {
                        return payload.results;
                    }

                    return [];
                };

                const [provinces, citiesMunicipalities] =
                    await Promise.all([
                        getList('provinces'),
                        getList('cities-municipalities')
                    ]);

                const codeOf = item => String(
                    item.code ??
                    item.psgc_code ??
                    item.psgcCode ??
                    ''
                );

                // Apply the name correction to all PSGC location names.
                const nameOf = item => this.fixPSGCName(
                    item.name ??
                    item.psgc_name ??
                    item.psgcName ??
                    ''
                );

                const regionOf = item => {
                    const region =
                        item.region ??
                        item.region_code ??
                        item.regionCode ??
                        '';

                    if (typeof region === 'object' && region !== null) {
                        return `${region.code ?? ''} ${region.name ?? ''}`;
                    }

                    return String(region);
                };

                const isRegionIII = item => {
                    const region = regionOf(item).toLowerCase();
                    const code = codeOf(item);

                    return region.includes('central luzon') ||
                        region.includes('region iii') ||
                        region === '03' ||
                        region.startsWith('030') ||
                        code.startsWith('03');
                };

                const regionProvinces = provinces
                    .filter(item => {
                        const code = codeOf(item);
                        return code.startsWith('03') || isRegionIII(item);
                    })
                    .map(item => ({
                        code: codeOf(item),
                        name: nameOf(item)
                    }))
                    .filter(item => item.code && item.name)
                    .sort((a, b) => a.name.localeCompare(b.name, 'en'));

                if (regionProvinces.length < 7) {
                    throw new Error(
                        'The complete Region III province list could not be verified from PSGC Cloud.'
                    );
                }

                const normalize = value => String(value ?? '')
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9]/g, '');

                const provinceRefOf = item => {
                    const raw =
                        item.province ??
                        item.province_code ??
                        item.provinceCode ??
                        '';

                    if (typeof raw === 'object' && raw !== null) {
                        return String(
                            raw.code ??
                            raw.psgc_code ??
                            raw.name ??
                            ''
                        );
                    }

                    return String(raw);
                };

                const canonicalProvinceCode = item => {
                    const ref = provinceRefOf(item);

                    const match = regionProvinces.find(province =>
                        ref === province.code ||
                        normalize(ref) === normalize(province.name) ||
                        (
                            ref &&
                            province.code.startsWith(ref) &&
                            ref.length >= 4
                        )
                    );

                    return match?.code || '';
                };

                const regionMunicipalities = citiesMunicipalities
                    .filter(item => {
                        const provinceCode = canonicalProvinceCode(item);

                        return Boolean(provinceCode) || isRegionIII(item);
                    })
                    .map(item => ({
                        code: codeOf(item),
                        name: nameOf(item),
                        provinceCode: canonicalProvinceCode(item)
                    }))
                    .filter(item =>
                        item.code &&
                        item.name &&
                        item.provinceCode
                    )
                    .sort((a, b) => a.name.localeCompare(b.name, 'en'));

                this.locationProvinces = regionProvinces;
                this.provinceOptions = regionProvinces.map(
                    item => item.name
                );

                this.locationMunicipalities = regionMunicipalities;
                this.locationBarangays = [];
                this.selectedBarangays = [];
            } catch (error) {
                console.error(
                    'Unable to load PSGC Region III data:',
                    error
                );

                this.locationDataError =
                    'Unable to load the complete Region III location list. Please check your internet connection and reload this page.';

                this.provinceOptions = [];
                this.locationProvinces = [];
                this.locationMunicipalities = [];
                this.locationBarangays = [];
                this.selectedBarangays = [];
            } finally {
                this.locationDataLoading = false;
            }
        },

        async init() {
            // Load real cooperative records from Laravel/MySQL.
            this.apiUrls.index =
                this.$root.dataset.cooperativeIndexUrl;

            this.apiUrls.store =
                this.$root.dataset.cooperativeStoreUrl;

            this.apiUrls.update =
                this.$root.dataset.cooperativeUpdateUrl;

            this.cooperatives = [];
            this.resetForm();

            this.loadRegionIIILocations();
            await this.loadCooperatives();
        },

        async loadCooperatives() {
            this.isLoadingRecords = true;

            try {
                const response = await fetch(this.apiUrls.index, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(
                        payload.message ||
                        `Unable to load cooperative records (${response.status}).`
                    );
                }

                const rows = Array.isArray(payload)
                    ? payload
                    : Array.isArray(payload.data)
                        ? payload.data
                        : [];

                this.cooperatives = rows.map(row => ({
                    ...row,

                    db_id: row.db_id ??
                        row.database_id ??
                        row.id,

                    id: row.cooperative_code ?? row.id ?? '',
                    name: row.cooperative_name ?? row.name ?? '',

                    cda_registration_no: row.cda_registration_no ?? '',
                    accreditation_status: row.accreditation_status ?? '',

                    fda_status: row.fda_status ?? '',
                    nda_lto_status: row.nda_lto_status ?? '',
                    ncisa_status: row.ncisa_status ?? '',
                    philgeps_status: row.philgeps_status ?? '',
                    ireb_status: row.ireb_status ?? '',
                    milk_testing_status: row.milk_testing_status ?? '',

                    coop_type: row.coop_type ?? '',
                    category: row.category ?? '',

                    date_established: row.date_established
                        ? String(row.date_established).slice(0, 10)
                        : '',

                    region: row.region ?? '',
                    province: row.province ?? '',
                    municipality: row.municipality ?? '',
                    barangay: row.barangay ?? '',
                    year_enrolled: row.year_enrolled ?? ''
                }));
            } catch (error) {
                console.error(
                    'Unable to load cooperative records:',
                    error
                );

                this.notify(
                    error.message ||
                    'Unable to load cooperative records from the database.',
                    'error'
                );

                this.cooperatives = [];
            } finally {
                this.isLoadingRecords = false;
            }
        },

        async sendCooperativeRequest(url, method, record) {
            const csrf =
                this.$root.dataset.csrfToken ||
                document.querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') ||
                '';

            const response = await fetch(url, {
                method,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    cda_registration_no:
                        record.cda_registration_no || null,

                    cooperative_code: record.id,
                    cooperative_name: record.name,

                    accreditation_status:
                        record.accreditation_status || null,

                    fda_status: record.fda_status || null,
                    nda_lto_status: record.nda_lto_status || null,
                    ncisa_status: record.ncisa_status || null,
                    philgeps_status: record.philgeps_status || null,
                    ireb_status: record.ireb_status || null,

                    milk_testing_status:
                        record.milk_testing_status || null,

                    coop_type: record.coop_type || null,
                    category: record.category || null,

                    date_established: record.date_established || null,
                    region: record.region || null,
                    province: record.province || null,
                    municipality: record.municipality || null,
                    barangay: record.barangay || null,
                    year_enrolled: record.year_enrolled || null
                })
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const validation = payload.errors
                    ? Object.values(payload.errors).flat().join(' ')
                    : '';

                throw new Error(
                    validation ||
                    payload.message ||
                    `Request failed (${response.status}).`
                );
            }

            return payload.data;
        },

        summaryCards() {
            const records = this.filteredCooperatives();

            return [
                {
                    label: 'Total Cooperatives',
                    value: records.length,
                    description: 'Matching filtered records',
                    icon: 'fa-solid fa-people-group',
                    iconBg: 'bg-blue-50',
                    iconColor: 'text-blue-700'
                },
                {
                    label: 'Provinces Covered',
                    value: new Set(
                        records.map(c => c.province).filter(Boolean)
                    ).size,
                    description: 'Provinces in filtered results',
                    icon: 'fa-solid fa-map-location-dot',
                    iconBg: 'bg-emerald-50',
                    iconColor: 'text-emerald-700'
                },
                {
                    label: 'Municipalities',
                    value: new Set(
                        records.map(c => c.municipality).filter(Boolean)
                    ).size,
                    description: 'Municipalities in filtered results',
                    icon: 'fa-solid fa-location-dot',
                    iconBg: 'bg-indigo-50',
                    iconColor: 'text-indigo-700'
                },
                {
                    label: 'Accredited',
                    value: records.filter(
                        c => c.accreditation_status === 'Accredited'
                    ).length,
                    description: 'Accredited filtered records',
                    icon: 'fa-solid fa-certificate',
                    iconBg: 'bg-amber-50',
                    iconColor: 'text-amber-700'
                }
            ];
        },

        matchesCompliance(status, selected) {
            if (!selected) return true;

            const normalized = String(status ?? '')
                .trim()
                .toLowerCase();

            const completeValues = [
                'completed',
                'complete',
                'registered',
                'accredited',
                'yes',
                'active'
            ];

            const isComplete = completeValues.includes(normalized);

            return selected === 'complete'
                ? isComplete
                : !isComplete;
        },

        filteredCooperatives() {
            const search = String(this.filters.search || '')
                .toLowerCase()
                .trim();

            return this.cooperatives.filter(c => {
                const matchesSearch = !search || [
                    c.id,
                    c.cda_registration_no,
                    c.name,
                    c.accreditation_status,
                    c.fda_status,
                    c.nda_lto_status,
                    c.ncisa_status,
                    c.philgeps_status,
                    c.ireb_status,
                    c.milk_testing_status,
                    c.coop_type,
                    c.category,
                    c.region,
                    c.province,
                    c.municipality,
                    c.barangay,
                    c.year_enrolled
                ].some(value =>
                    String(value ?? '').toLowerCase().includes(search)
                );

                return matchesSearch
                    && (!this.filters.region ||
                        c.region === this.filters.region)
                    && (!this.filters.province ||
                        c.province === this.filters.province)
                    && (!this.filters.municipality ||
                        c.municipality === this.filters.municipality)
                    && (!this.filters.year ||
                        String(c.year_enrolled) ===
                        String(this.filters.year))
                    && (!this.filters.type ||
                        c.coop_type === this.filters.type)
                    && (!this.filters.accreditation ||
                        c.accreditation_status ===
                        this.filters.accreditation)
                    && this.matchesCompliance(
                        c.fda_status,
                        this.filters.fda_status
                    )
                    && this.matchesCompliance(
                        c.nda_lto_status,
                        this.filters.nda_lto_status
                    )
                    && this.matchesCompliance(
                        c.ncisa_status,
                        this.filters.ncisa_status
                    )
                    && this.matchesCompliance(
                        c.philgeps_status,
                        this.filters.philgeps_status
                    )
                    && this.matchesCompliance(
                        c.ireb_status,
                        this.filters.ireb_status
                    )
                    && this.matchesCompliance(
                        c.milk_testing_status,
                        this.filters.milk_testing_status
                    );
            });
        },

        paginatedCooperatives() {
            const start = (this.page - 1) * this.perPage;

            return this.filteredCooperatives().slice(
                start,
                start + this.perPage
            );
        },

        totalPages() {
            return Math.max(
                1,
                Math.ceil(
                    this.filteredCooperatives().length / this.perPage
                )
            );
        },

        nextPage() {
            if (this.page < this.totalPages()) this.page++;
        },

        previousPage() {
            if (this.page > 1) this.page--;
        },

        availableValues(field) {
            let records = this.cooperatives;

            if (field === 'province' && this.filters.region) {
                records = records.filter(
                    c => c.region === this.filters.region
                );
            }

            if (field === 'municipality') {
                if (this.filters.region) {
                    records = records.filter(
                        c => c.region === this.filters.region
                    );
                }

                if (this.filters.province) {
                    records = records.filter(
                        c => c.province === this.filters.province
                    );
                }
            }

            return [
                ...new Set(records.map(c => c[field]).filter(Boolean))
            ].sort((a, b) => a.localeCompare(b, 'en'));
        },

        availableYears() {
            return [
                ...new Set(
                    this.cooperatives
                        .map(c => String(c.year_enrolled || ''))
                        .filter(Boolean)
                )
            ].sort().reverse();
        },

        resetFilters() {
            this.filters = {
                search: '',
                region: '',
                province: '',
                municipality: '',
                year: '',
                type: '',
                accreditation: '',
                fda_status: '',
                nda_lto_status: '',
                ncisa_status: '',
                philgeps_status: '',
                ireb_status: '',
                milk_testing_status: ''
            };

            this.page = 1;
        },

        accreditationClass(status) {
            if (status === 'Accredited') {
                return 'bg-emerald-100 text-emerald-700';
            }

            if (status === 'Pending') {
                return 'bg-amber-100 text-amber-700';
            }

            if (status === 'Expired' || status === 'Suspended') {
                return 'bg-red-100 text-red-700';
            }

            return 'bg-slate-100 text-slate-600';
        },

        detailFields() {
            if (!this.selectedCooperative) return [];

            const c = this.selectedCooperative;

            return [
                { label: 'CDA Registration No.', value: c.cda_registration_no },
                { label: 'Cooperative Code / Herd Code', value: c.id },
                { label: 'Cooperative Name', value: c.name },
                { label: 'Accreditation Status', value: c.accreditation_status },
                { label: 'FDA', value: c.fda_status },
                { label: 'NDA - LTO', value: c.nda_lto_status },
                { label: 'NCISA', value: c.ncisa_status },
                { label: 'PhilGEPS', value: c.philgeps_status },
                { label: 'iREB', value: c.ireb_status },
                { label: 'Milk Testing', value: c.milk_testing_status },
                { label: 'Cooperative Type', value: c.coop_type },
                { label: 'Category', value: c.category },
                { label: 'Date Established', value: this.formatDate(c.date_established) },
                { label: 'Region', value: c.region },
                { label: 'Province', value: c.province },
                { label: 'City / Town / Municipality', value: c.municipality },
                { label: 'Barangay', value: c.barangay },
                { label: 'Year Enrolled', value: c.year_enrolled }
            ];
        },

        formatDate(date) {
            if (!date) return '—';

            const parts = String(date).split('-');
            if (parts.length !== 3) return date;

            const months = [
                'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
            ];

            const month = Number(parts[1]) - 1;

            if (month < 0 || month > 11) return date;

            return `${months[month]} ${Number(parts[2])}, ${parts[0]}`;
        },

        resetForm() {
            this.form = {
                cda_registration_no: '',
                id: '',
                name: '',
                accreditation_status: '',
                fda_status: '',
                nda_lto_status: '',
                ncisa_status: '',
                philgeps_status: '',
                ireb_status: '',
                milk_testing_status: '',
                coop_type: '',
                category: '',
                date_established: '',
                region: '',
                province: '',
                municipality: '',
                barangay: '',
                year_enrolled: new Date().getFullYear()
            };
        },

        openAddModal() {
            this.editing = false;
            this.editingOriginalId = null;
            this.editingOriginalDbId = null;

            this.resetForm();
            this.showFormModal = true;
        },

        openEditModal(coop) {
            this.editing = true;
            this.editingOriginalId = coop.id;
            this.editingOriginalDbId = coop.db_id;

            this.form = { ...coop };
            this.showFormModal = true;
        },

        viewCooperative(coop) {
            this.selectedCooperative = { ...coop };
            this.showDetailModal = true;
        },

        async saveCooperative() {
            if (this.isSaving) return;

            const id = String(this.form.id || '').trim();
            const name = String(this.form.name || '').trim();

            if (!id || !name) {
                this.notify(
                    'Cooperative Code / Herd Code and Cooperative Name are required.',
                    'error'
                );
                return;
            }

            const year = String(this.form.year_enrolled ?? '').trim();

            if (
                year &&
                (!/^\d{4}$/.test(year) ||
                    Number(year) < 1900 ||
                    Number(year) > 2200)
            ) {
                this.notify(
                    'Please enter a valid four-digit Year Enrolled.',
                    'error'
                );
                return;
            }

            const record = {
                cda_registration_no:
                    String(this.form.cda_registration_no || '').trim(),

                id,
                name,

                accreditation_status: this.form.accreditation_status || '',
                fda_status: this.form.fda_status || '',
                nda_lto_status: this.form.nda_lto_status || '',
                ncisa_status: this.form.ncisa_status || '',
                philgeps_status: this.form.philgeps_status || '',
                ireb_status: this.form.ireb_status || '',
                milk_testing_status: this.form.milk_testing_status || '',

                coop_type: this.form.coop_type || '',
                category: this.form.category || '',
                date_established: this.form.date_established || '',

                region: String(this.form.region || '').trim(),
                province: String(this.form.province || '').trim(),
                municipality: String(this.form.municipality || '').trim(),
                barangay: String(this.form.barangay || '').trim(),

                year_enrolled: year ? Number(year) : ''
            };

            this.isSaving = true;

            try {
                const url = this.editing
                    ? `${this.apiUrls.update}/${this.editingOriginalDbId}`
                    : this.apiUrls.store;

                await this.sendCooperativeRequest(
                    url,
                    this.editing ? 'PUT' : 'POST',
                    record
                );

                await this.loadCooperatives();

                this.showFormModal = false;
                this.page = 1;

                this.notify(
                    this.editing
                        ? 'Cooperative updated and saved to database.'
                        : 'Cooperative added and saved to database.'
                );
            } catch (error) {
                this.notify(
                    error.message ||
                    'Unable to save the cooperative to the database.',
                    'error'
                );
            } finally {
                this.isSaving = false;
            }
        },

        downloadPDFReport() {
            if (!this.filteredCooperatives().length) {
                this.notify(
                    'There are no cooperative records to include in the PDF report.',
                    'error'
                );
                return;
            }

            this.reportGeneratedAt = new Date().toLocaleString();
            this.printReport = true;

            this.$nextTick(() => {
                setTimeout(() => {
                    const resetPrintReport = () => {
                        this.printReport = false;
                        window.removeEventListener(
                            'afterprint',
                            resetPrintReport
                        );
                    };

                    window.addEventListener(
                        'afterprint',
                        resetPrintReport
                    );

                    window.print();

                    setTimeout(resetPrintReport, 1500);
                }, 100);
            });
        },

        notify(message, type = 'success') {
            this.toast.message = message;
            this.toast.type = type;
            this.toast.show = true;

            setTimeout(() => {
                this.toast.show = false;
            }, 3500);
        },

        csvHeaders() {
            return [
                'CDA Registration No.',
                'Cooperative Code / Herd Code',
                'Cooperative Name',
                'Accreditation Status',
                'FDA',
                'NDA - LTO',
                'NCISA',
                'PhilGEPS',
                'iREB',
                'Milk Testing',
                'Coop Type',
                'Category',
                'Date Established',
                'Region',
                'Province',
                'City/Town/Municipality',
                'Barangay',
                'Year Enrolled'
            ];
        },

        csvValues(c) {
            return [
                c.cda_registration_no,
                c.id,
                c.name,
                c.accreditation_status,
                c.fda_status,
                c.nda_lto_status,
                c.ncisa_status,
                c.philgeps_status,
                c.ireb_status,
                c.milk_testing_status,
                c.coop_type,
                c.category,
                c.date_established,
                c.region,
                c.province,
                c.municipality,
                c.barangay,
                c.year_enrolled
            ];
        },

        csvEscape(value) {
            return '"' +
                String(value ?? '').replace(/"/g, '""') +
                '"';
        },

        exportCSV() {
            const records = this.filteredCooperatives();

            if (!records.length) {
                this.notify(
                    'There are no filtered records to download.',
                    'error'
                );
                return;
            }

            const rows = records.map(c =>
                this.csvValues(c)
                    .map(value => this.csvEscape(value))
                    .join(',')
            );

            const csv = '\uFEFF' + [
                this.csvHeaders()
                    .map(value => this.csvEscape(value))
                    .join(','),
                ...rows
            ].join('\r\n');

            const blob = new Blob([csv], {
                type: 'text/csv;charset=utf-8;'
            });

            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');

            link.href = url;
            link.download = 'cbed-cooperatives-filtered.csv';

            document.body.appendChild(link);
            link.click();
            link.remove();

            setTimeout(() => URL.revokeObjectURL(url), 1000);

            this.notify(
                `${records.length} cooperative record(s) downloaded.`
            );
        },

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
                } else if (
                    (ch === '\n' || ch === '\r') &&
                    !quoted
                ) {
                    if (ch === '\r' && next === '\n') i++;

                    row.push(value);

                    if (row.some(v => v.trim() !== '')) {
                        rows.push(row);
                    }

                    row = [];
                    value = '';
                } else {
                    value += ch;
                }
            }

            row.push(value);

            if (row.some(v => v.trim() !== '')) {
                rows.push(row);
            }

            return rows;
        },

        async importCSV(event) {
            const file = event.target.files?.[0];
            if (!file) return;

            try {
                const rows = this.parseCSV(await file.text());

                if (rows.length < 2) {
                    this.notify(
                        'The CSV file contains no cooperative records.',
                        'error'
                    );
                    return;
                }

                const headers = rows[0].map(header =>
                    header.trim().toLowerCase().replace(/^\uFEFF/, '')
                );

                const normalize = value => value
                    .toLowerCase()
                    .replace(/[^a-z0-9]/g, '');

                const findIndex = (...names) => {
                    const wanted = names.map(normalize);

                    return headers.findIndex(header =>
                        wanted.includes(normalize(header))
                    );
                };

                const idIndex = findIndex(
                    'Cooperative Code / Herd Code',
                    'Cooperative Code',
                    'Herd Code',
                    'Coop Code',
                    'Code',
                    'ID'
                );

                const nameIndex = findIndex(
                    'Cooperative Name',
                    'Coop Name',
                    'Name'
                );

                if (idIndex < 0 || nameIndex < 0) {
                    this.notify(
                        'CSV must contain Cooperative Code / Herd Code and Cooperative Name columns.',
                        'error'
                    );
                    return;
                }

                const get = (record, ...names) => {
                    const index = findIndex(...names);

                    return index >= 0
                        ? String(record[index] ?? '').trim()
                        : '';
                };

                let added = 0;
                let skipped = 0;

                for (const row of rows.slice(1)) {
                    const id = String(row[idIndex] ?? '').trim();
                    const name = String(row[nameIndex] ?? '').trim();

                    const duplicate = this.cooperatives.some(c =>
                        String(c.id).toLowerCase() === id.toLowerCase()
                    );

                    if (!id || !name || duplicate) {
                        skipped++;
                        continue;
                    }

                    const yearText = get(row, 'Year Enrolled');

                    const year = /^\d{4}$/.test(yearText)
                        ? Number(yearText)
                        : '';

                    const record = {
                        cda_registration_no: get(
                            row,
                            'CDA Registration No.',
                            'CDA Registration Number'
                        ),

                        id,
                        name,

                        accreditation_status: get(
                            row,
                            'Accreditation Status',
                            'Accreditation'
                        ),

                        fda_status: get(row, 'FDA'),
                        nda_lto_status: get(row, 'NDA - LTO', 'NDA-LTO'),
                        ncisa_status: get(row, 'NCISA'),
                        philgeps_status: get(row, 'PhilGEPS', 'PHIL GEPS'),
                        ireb_status: get(row, 'iREB', 'IREB'),

                        milk_testing_status: get(
                            row,
                            'Milk Testing',
                            'Milk Testing Status'
                        ),

                        coop_type: get(
                            row,
                            'Coop Type',
                            'Cooperative Type',
                            'Type'
                        ),

                        category: get(row, 'Category'),
                        date_established: get(row, 'Date Established'),
                        region: get(row, 'Region'),
                        province: get(row, 'Province'),

                        municipality: get(
                            row,
                            'City/Town/Municipality',
                            'Municipality',
                            'City',
                            'Town'
                        ),

                        barangay: get(row, 'Barangay'),
                        year_enrolled: year
                    };

                    try {
                        await this.sendCooperativeRequest(
                            this.apiUrls.store,
                            'POST',
                            record
                        );

                        added++;
                    } catch (error) {
                        console.error(
                            `Unable to import cooperative ${id}:`,
                            error
                        );

                        skipped++;
                    }
                }

                await this.loadCooperatives();

                this.page = 1;

                this.notify(
                    `Import complete: ${added} saved to database, ${skipped} skipped.`
                );
            } catch (error) {
                console.error('CSV import failed:', error);

                this.notify('Could not read the CSV file.', 'error');
            } finally {
                event.target.value = '';
            }
        }
    }));
    
});
</script>
@endpush
