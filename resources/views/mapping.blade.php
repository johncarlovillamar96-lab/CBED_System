@extends('layouts.admin')

@section('title', 'CBED Mapping')
@section('page-heading', 'CBED Mapping')

@section('content')
<div x-data="{
    search: '',
    status: 'All',
    showAdd: false,
    records: [
        ['LOC-001', 'Juan Dela Cruz', 'Lubao', 'Pampanga', '14.93', '120.60'],
        ['LOC-002', 'Sacobia Farmers Agriculture Cooperative', 'Concepcion', 'Tarlac', '15.33', '120.66'],
        ['LOC-003', 'Maria Santos', 'Guagua', 'Pampanga', '14.97', '120.63']
    ],
    headers: ['Location ID', 'Farmer / Organization', 'Municipality', 'Province', 'Latitude', 'Longitude'],
    newRecord: [],
    get filteredRecords() {
        return this.records.filter(row => {
            const matchesSearch = row.some(value =>
                String(value).toLowerCase().includes(this.search.toLowerCase())
            );
            const matchesStatus = this.status === 'All' ||
                row[row.length - 1] === this.status;
            return matchesSearch && matchesStatus;
        });
    },
    addRecord() {
        if (!this.newRecord.some(value => String(value || '').trim())) return;
        this.records.unshift(this.headers.map((header, i) =>
            String(this.newRecord[i] || '').trim() || 'â€”'
        ));
        this.newRecord = [];
        this.showAdd = false;
    }
}" class="space-y-6">

    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">CBED Mapping</h1>
            <p class="mt-1 text-sm text-slate-500">View recorded farmer, cooperative, and animal locations.</p>
        </div>

        <button @click="showAdd = true"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
            <i class="fa-solid fa-plus"></i> Add Record
        </button>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="cbed-card p-5">
            <p class="text-sm text-slate-500">Total Records</p>
            <p class="mt-2 text-2xl font-bold text-slate-800" x-text="records.length"></p>
        </div>
        <div class="cbed-card p-5">
            <p class="text-sm text-slate-500">Matching Records</p>
            <p class="mt-2 text-2xl font-bold text-blue-600" x-text="filteredRecords.length"></p>
        </div>
        <div class="cbed-card p-5">
            <p class="text-sm text-slate-500">Data Source</p>
            <p class="mt-2 text-lg font-bold text-emerald-600">Sample Data</p>
            <p class="mt-1 text-xs text-slate-400">Not connected to a database</p>
        </div>
    </div>

    <div class="cbed-card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-slate-100 p-5 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="font-bold text-slate-800">Records</h2>
                <p class="mt-1 text-xs text-slate-500">Search and manage this page's sample records.</p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input x-model="search" type="search" placeholder="Search records..."
                        class="w-full rounded-lg border border-slate-200 py-2 pl-9 pr-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 sm:w-64">
                </div>
                <select x-model="status"
                    class="rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500">
                    <option value="All">All records</option>
                    <option value="Active">Active</option>
                    <option value="Live">Live</option>
                    <option value="Pending">Pending</option>
                    <option value="Healthy">Healthy</option>
                    <option value="Positive">Positive</option>
                    <option value="Alive">Alive</option>
                    <option value="Completed">Completed</option>
                </select>
            </div>
        </div>

        <div class="cbed-scrollbar overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <template x-for="header in headers" :key="header">
                            <th class="whitespace-nowrap px-5 py-3.5 text-xs font-bold uppercase tracking-wide text-slate-500" x-text="header"></th>
                        </template>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <template x-for="(row, index) in filteredRecords" :key="index">
                        <tr class="transition hover:bg-slate-50">
                            <template x-for="(value, cellIndex) in row" :key="cellIndex">
                                <td class="whitespace-nowrap px-5 py-4 text-slate-600" x-text="value"></td>
                            </template>
                        </tr>
                    </template>
                    <tr x-show="filteredRecords.length === 0">
                        <td :colspan="headers.length" class="px-5 py-12 text-center text-slate-400">
                            <i class="fa-solid fa-folder-open mb-3 block text-2xl"></i>
                            No matching records found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-col justify-between gap-2 border-t border-slate-100 bg-white px-5 py-4 text-xs text-slate-500 sm:flex-row">
            <span>Showing <strong x-text="filteredRecords.length"></strong> record(s)</span>
            <span>CBED Data System</span>
        </div>
    </div>

    <!-- Add Record Modal -->
    <div x-cloak x-show="showAdd" x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"
        @keydown.escape.window="showAdd = false">

        <div @click.outside="showAdd = false"
            class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5">
                <div>
                    <h2 class="font-bold text-slate-800">Add CBED Mapping Record</h2>
                    <p class="mt-1 text-xs text-slate-500">Enter the sample record details.</p>
                </div>
                <button @click="showAdd = false" class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form @submit.prevent="addRecord()" class="p-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <template x-for="(header, i) in headers" :key="header">
                        <label class="block">
                            <span class="mb-1.5 block text-xs font-semibold text-slate-600" x-text="header"></span>
                            <input x-model="newRecord[i]" :placeholder="'Enter ' + header.toLowerCase()"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        </label>
                    </template>
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-5">
                    <button type="button" @click="showAdd = false"
                        class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="submit"
                        class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                        <i class="fa-solid fa-plus mr-1"></i> Add Record
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
