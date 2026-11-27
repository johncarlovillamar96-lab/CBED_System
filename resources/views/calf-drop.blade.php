{{-- resources/views/calf-drop.blade.php --}}
@extends('layouts.admin')
@section('inlineCalfDropNotifications', '1')
@section('title', 'Calf Records | CBED')
@section('page-heading', 'Calf Records')

@section('content')
<div x-data="calfDropPage()" x-cloak class="calf-records-page management-page min-w-0 bg-slate-50 text-slate-800">
    <x-admin-page-header title="Calf Records" description="Review births and manage calf records linked to Carabao Inventory." section="Animal Management">
        <button type="button" @click="openCalfHistory()" aria-haspopup="dialog" class="inline-flex items-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-semibold shadow-sm"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> View Calf Drop History</button>
        <button type="button" @click="openCooperativeCalfPrint()" aria-haspopup="dialog" :disabled="inventoryLoading || !calfCooperatives.length" class="inline-flex items-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-semibold shadow-sm disabled:opacity-50"><i class="fa-solid fa-print" aria-hidden="true"></i> Print</button>
        <button type="button" @click="exportCSV()" class="inline-flex items-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-semibold shadow-sm"><i class="fa-solid fa-file-export" aria-hidden="true"></i> Export</button>
        <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-semibold shadow-sm"><i class="fa-solid fa-file-import"></i> Import<input type="file" accept=".csv,text/csv" class="hidden" @change="importCSV($event)"></label>
        @if(auth()->user()->isAdmin())<button type="button" @click="openAdd()" class="cbed-add-button inline-flex items-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-semibold shadow-sm"><i class="fa-solid fa-plus"></i> Add Calf Record</button>@endif
    </x-admin-page-header>

    {{-- Summary cards --}}
    <div class="mb-7 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <template x-for="card in summaryCards" :key="card.title"><div class="cbed-card dashboard-stat-card management-summary-card p-5"><div class="flex items-center justify-between"><div><p class="text-sm font-medium text-slate-500" x-text="card.title"></p><h3 class="mt-3 text-3xl font-bold text-slate-800" x-text="card.value"></h3><p class="mt-2 text-xs text-slate-400" x-text="card.description"></p></div><div class="dashboard-stat-icon flex h-12 w-12 items-center justify-center rounded-xl text-xl"><i :class="card.icon"></i></div></div></div></template>
    </div>

    <section class="dashboard-status-section mb-7" aria-labelledby="calves-by-cooperative-title" x-data="{ showTable: false }">
        <div class="mb-3">
            <h2 id="calves-by-cooperative-title" class="text-lg font-bold text-[#0b1f3a]">Calf summary by cooperative and birth year</h2>
            <p class="text-sm text-slate-500">Live, dead, and transferred female and male calves by cooperative and birth year</p>
        </div>

        <button type="button" class="mb-3 text-sm font-semibold text-slate-700" x-show="cooperativeBirthYearSummaries.length"
            aria-controls="calves-by-cooperative-table" :aria-expanded="showTable" @click="showTable = !showTable"
            x-text="showTable ? 'Hide table' : 'Show table'"></button>
        <div id="calves-by-cooperative-table" class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm" role="region" aria-label="Calf counts by cooperative and birth year" tabindex="0" x-show="showTable && cooperativeBirthYearSummaries.length">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-800">
                    <tr>
                        <th scope="col" class="px-4 py-3">Cooperative</th>
                        <template x-for="item in birthYearSummary" :key="item.year">
                            <th scope="col" class="border-l border-slate-100 px-4 py-3 text-center" x-text="item.year"></th>
                        </template>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 border-t border-slate-100">
                    <template x-for="group in cooperativeBirthYearSummaries" :key="group.name">
                        <tr>
                            <th scope="row" class="px-4 py-3 align-top font-semibold text-slate-800" x-text="group.name"></th>
                            <template x-for="year in birthYearSummary" :key="year.year">
                                <td class="min-w-[12rem] border-l border-slate-100 px-4 py-3 align-top" x-data="{ get item() { return group.years[year.year]; } }">
                                    <template x-if="item">
                                        <div>
                                            <dl class="grid grid-cols-[repeat(2,minmax(max-content,1fr))] gap-2 text-sm text-slate-700">
                                                <div>
                                                    <dt class="font-medium">Female</dt>
                                                    <dd class="mt-1 grid gap-y-1 text-xs">
                                                        <span class="font-semibold text-emerald-700" x-text="'Live - ' + item.female.live"></span>
                                                        <span class="font-semibold text-rose-700" x-text="'Dead - ' + item.female.dead"></span>
                                                        <span class="whitespace-nowrap font-semibold text-indigo-700" x-text="'Transferred - ' + item.female.transferred"></span>
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt class="font-medium">Male</dt>
                                                    <dd class="mt-1 grid gap-y-1 text-xs">
                                                        <span class="font-semibold text-emerald-700" x-text="'Live - ' + item.male.live"></span>
                                                        <span class="font-semibold text-rose-700" x-text="'Dead - ' + item.male.dead"></span>
                                                        <span class="whitespace-nowrap font-semibold text-indigo-700" x-text="'Transferred - ' + item.male.transferred"></span>
                                                    </dd>
                                                </div>
                                            </dl>
                                            <p class="mt-2 text-xs text-slate-500" x-text="'Total records ' + item.count"></p>
                                        </div>
                                    </template>
                                    <span x-show="!item" class="block text-center text-slate-500" aria-label="No records">—</span>
                                </td>
                            </template>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <p x-show="!cooperativeBirthYearSummaries.length" class="text-sm text-slate-500">No matching calf records have a birth date and a cooperative listed in the directory.</p>
    </section>

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
                    <p class="mt-1 text-xs text-slate-500">Total uses the worksheet when available. With Calf ID follows your filters. Not Reported uses the full ID count.</p>
                </div>
            </header>
            <div class="max-h-72 overflow-y-auto p-5">
                <div class="mb-2 mr-7 grid grid-cols-4 gap-2 px-3 text-xs font-semibold text-slate-600">
                    <span>Year</span>
                    <span class="text-right">Total</span>
                    <span class="text-right">With Calf ID</span>
                    <span class="text-right">Not Reported</span>
                </div>
                <div x-show="calfHistoryError" class="mb-2 flex items-center gap-2 text-xs text-rose-700">
                    <span role="status" x-text="calfHistoryError"></span>
                    <button type="button" @click="loadCalfHistory()" class="font-semibold underline">Retry</button>
                </div>
                <ol class="space-y-3">
                    <template x-for="item in birthYearReportRows" :key="item.year">
                        <li class="grid grid-cols-[minmax(0,1fr)_1.25rem] items-center gap-2">
                            <div class="grid grid-cols-4 items-center gap-2 rounded-lg border border-slate-200 p-3 text-sm">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="font-medium tabular-nums text-slate-700" x-text="item.year"></span>
                                    <div class="hidden h-2 min-w-0 flex-1 rounded-full bg-slate-100 sm:block" aria-hidden="true"><div class="h-full rounded-full bg-emerald-600" :style="`width:${item.totalPercent}%`"></div></div>
                                </div>
                                <span class="text-right font-semibold tabular-nums text-slate-700"
                                    :title="item.historyTotal === null ? 'Total from calf records with IDs' : 'Total from the history worksheet'"
                                    x-text="item.total"></span>
                                <div class="text-right tabular-nums">
                                    <button type="button" x-show="item.withCalfId" class="rounded font-semibold text-blue-700 underline hover:text-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-600"
                                        aria-haspopup="dialog" :aria-label="'View ' + (item.withCalfId?.count ?? 0) + ' calf records with IDs for ' + item.year"
                                        @click="openBirthYear(item.withCalfId)" x-text="item.withCalfId?.count"></button>
                                    <span x-show="!item.withCalfId" class="text-slate-500">0</span>
                                </div>
                                <span class="text-right font-semibold tabular-nums text-slate-700"
                                    :title="item.historyTotal === null ? 'No history worksheet total for this year' : item.historyTotal + ' in worksheet · ' + item.registeredTotal + ' with calf IDs'"
                                    x-text="item.notReported ?? '—'"></span>
                            </div>
                            <button type="button" x-show="item.withCalfId?.unknownSexCount" class="rounded text-center text-amber-700 hover:text-amber-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-amber-700"
                                aria-haspopup="dialog" @click="openBirthYear(item.withCalfId, true)"
                                :title="'View records with unknown sex (' + (item.withCalfId?.unknownSexCount ?? 0) + ')'"
                                :aria-label="'View ' + (item.withCalfId?.unknownSexCount ?? 0) + ' records with unknown sex for ' + item.year">
                                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                            </button>
                        </li>
                    </template>
                </ol>
                <p x-show="!birthYearReportRows.length && !calfHistoryLoading" class="rounded-lg bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">No matching records have a birth date.</p>
            </div>
        </article>
    </section>

    <dialog x-ref="birthYearDialog" aria-labelledby="calf-birth-year-dialog-title"
        class="m-auto w-11/12 max-w-6xl overflow-hidden rounded-xl bg-white p-0 text-slate-900 shadow-xl backdrop:bg-slate-950/60">
        <div class="grid max-h-[90dvh] grid-rows-[auto_minmax(0,1fr)_auto]">
            <header class="flex items-center justify-between gap-4 border-b border-slate-200 p-5">
                <div>
                    <h2 id="calf-birth-year-dialog-title" class="text-lg font-bold" x-text="'Calf Records for ' + (selectedBirthYear?.year ?? '')"></h2>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                        <span class="text-slate-500" x-text="birthYearDialogRecords.length + (birthYearSex === 'unknown' ? ' with unknown sex' : ' total')"></span>
                        <button type="button" x-show="selectedBirthYear?.unknownSexCount" class="rounded text-amber-700 hover:text-amber-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-amber-700"
                            @click="birthYearSex = birthYearSex === 'unknown' ? '' : 'unknown'" :aria-pressed="birthYearSex === 'unknown'"
                            title="Filter to records with unknown sex" aria-label="Filter to records with unknown sex">
                            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <form method="dialog"><button type="submit" autofocus class="cbed-close-button" aria-label="Close"><span aria-hidden="true">&times;</span></button></form>
            </header>
            <div class="min-h-0 overflow-auto">
                <dl class="grid grid-cols-2 gap-3 border-b border-slate-200 bg-slate-50 p-5 sm:grid-cols-3 lg:grid-cols-6" x-data="{ get stats() { return birthYearDialogStats; } }">
                    <div class="rounded-lg border border-slate-200 bg-white p-3">
                        <dt class="text-xs font-semibold text-slate-600">Total Cooperatives</dt>
                        <dd class="mt-1 text-lg font-bold tabular-nums" x-text="stats.cooperativeTotal"></dd>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white p-3">
                        <dt class="text-xs font-semibold text-slate-600">Total</dt>
                        <dd class="mt-1 text-lg font-bold tabular-nums" x-text="stats.total"></dd>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white p-3">
                        <dt class="text-xs font-semibold text-slate-600">Female</dt>
                        <dd class="mt-1 text-lg font-bold tabular-nums" x-text="stats.female"></dd>
                    <dd class="mt-1 space-y-1 text-xs text-slate-600">
                        <div class="flex justify-between gap-1"><span>Heifer</span><span class="font-semibold tabular-nums" x-text="stats.heifers"></span></div>
                        <div class="flex justify-between gap-1"><span>Cow</span><span class="font-semibold tabular-nums" x-text="stats.cows"></span></div>
                    </dd>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white p-3">
                        <dt class="text-xs font-semibold text-slate-600">Male</dt>
                        <dd class="mt-1 text-lg font-bold tabular-nums" x-text="stats.male"></dd>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white p-3">
                        <dt class="text-xs font-semibold text-slate-600">Parity</dt>
                        <dd class="mt-1 text-lg font-bold tabular-nums" x-text="stats.parityTotal"></dd>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white p-3">
                        <dt class="text-xs font-semibold text-slate-600">Animal Status</dt>
                        <dd class="mt-1">
                            <template x-for="[status, count] in stats.statuses" :key="status">
                                <div class="flex justify-between gap-1 text-xs"><span x-text="status"></span><span class="font-semibold tabular-nums" x-text="count"></span></div>
                            </template>
                        </dd>
                    </div>
                </dl>
                <div class="grid gap-3 border-b border-slate-200 px-5 py-3 sm:grid-cols-3">
                    <div>
                    <label for="birth-year-cooperative-filter" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Cooperative</label>
                    <select id="birth-year-cooperative-filter" class="filter-input" x-model="birthYearCooperative">
                        <option value="">All cooperatives</option>
                        <template x-for="cooperative in birthYearCooperativeOptions" :key="cooperative">
                            <option :value="cooperative" x-text="cooperative"></option>
                        </template>
                    </select>
                    </div>
                    <div>
                        <label for="birth-year-sex-filter" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Sex</label>
                        <select id="birth-year-sex-filter" class="filter-input" x-model="birthYearSex">
                            <option value="">All sexes</option>
                            <option value="female">Female</option>
                            <option value="male">Male</option>
                            <option value="unknown">Unknown sex</option>
                        </select>
                    </div>
                    <div>
                        <label for="birth-year-status-filter" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Animal Status</label>
                        <select id="birth-year-status-filter" class="filter-input" x-model="birthYearStatus">
                            <option value="">All statuses</option>
                            <template x-for="status in birthYearStatusOptions" :key="status">
                                <option :value="status" x-text="status"></option>
                            </template>
                        </select>
                    </div>
                </div>
                <p x-show="!birthYearDialogRecords.length" class="px-5 py-6 text-sm text-slate-500">No calf records match the selected filters.</p>
                <table x-show="birthYearDialogRecords.length" class="calf-records-table w-full text-left text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-4 py-3">Cooperative</th>
                            <th scope="col" class="px-4 py-3">Calf ID</th>
                            <th scope="col" class="px-4 py-3">Date of Birth</th>
                            <th scope="col" class="px-4 py-3">Sex</th>
                            <th scope="col" class="px-4 py-3">Classification</th>
                            <th scope="col" class="px-4 py-3">Parity</th>
                            <th scope="col" class="px-4 py-3">Animal Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="calf in birthYearDialogRecords" :key="String(calf.calf_id || calf.id)">
                            <tr>
                                <td class="px-4 py-3" x-text="calf.cooperative || '—'"></td>
                                <th scope="row" class="px-4 py-3 font-semibold" x-text="calf.calf_id || '—'"></th>
                                <td class="whitespace-nowrap px-4 py-3" x-text="formatBirthDate(calf.date_of_birth)"></td>
                                <td class="px-4 py-3" x-text="calf.sex || '—'"></td>
                                <td class="px-4 py-3" x-text="birthYearCalfClassification(calf)"></td>
                                <td class="px-4 py-3" x-text="birthYearCalfParityDisplay(calf)"></td>
                                <td class="whitespace-nowrap px-4 py-3" x-text="birthYearCalfStatus(calf) || '—'"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <form method="dialog" class="flex justify-end gap-2 border-t border-slate-200 p-4">
                <button type="button" @click="printBirthYear()" :disabled="!birthYearPrintSummary.length"
                    class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold hover:bg-slate-50 disabled:opacity-50">
                    <i class="fa-solid fa-print mr-1" aria-hidden="true"></i> Print
                </button>
                <button type="submit" class="rounded-lg bg-[#0b1f3a] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#122b4d]">Close</button>
            </form>
        </div>
    </dialog>

    <dialog x-ref="calfPrintRangeDialog" aria-labelledby="calf-print-range-title"
        class="m-auto w-11/12 max-w-md rounded-xl bg-white p-0 text-slate-900 shadow-xl backdrop:bg-slate-950/60">
        <header class="flex items-center justify-between gap-4 border-b border-slate-200 p-5">
            <h2 id="calf-print-range-title" class="text-lg font-bold">Print Calf Drop History</h2>
            <form method="dialog"><button type="submit" class="cbed-close-button" aria-label="Close"><span aria-hidden="true">&times;</span></button></form>
        </header>
        <form @submit.prevent="printCooperativeCalfHistory()">
            <div class="grid grid-cols-2 gap-3 p-5">
                <div>
                    <label for="calf-print-from-year" class="mb-1 block text-sm font-semibold">From year</label>
                    <select id="calf-print-from-year" class="filter-input" x-model="calfPrintFromYear" autofocus required>
                        <option value="" disabled>Select year</option>
                        <template x-for="year in calfPrintYears" :key="year"><option :value="year" x-text="year"></option></template>
                    </select>
                </div>
                <div>
                    <label for="calf-print-to-year" class="mb-1 block text-sm font-semibold">To year</label>
                    <select id="calf-print-to-year" class="filter-input" x-model="calfPrintToYear">
                        <template x-for="year in calfPrintYears" :key="year"><option :value="year" x-text="year"></option></template>
                    </select>
                </div>
                <p x-show="calfPrintFromYear > calfPrintToYear" class="col-span-2 text-sm text-rose-700" role="status">From year must be earlier than or equal to To year.</p>
            </div>
            <div class="flex justify-end border-t border-slate-200 p-4">
                <button type="submit" :disabled="!calfPrintFromYear || !calfPrintToYear || calfPrintFromYear > calfPrintToYear"
                    class="rounded-lg bg-[#0b1f3a] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#122b4d] disabled:opacity-50">Print</button>
            </div>
        </form>
    </dialog>

    <dialog x-ref="calfHistoryDialog" aria-labelledby="calf-history-title"
        class="m-auto w-11/12 max-w-screen-2xl overflow-hidden rounded-xl bg-white p-0 text-slate-900 shadow-xl backdrop:bg-slate-950/60">
        <div class="grid max-h-[90dvh] grid-rows-[auto_minmax(0,1fr)_auto]">
            <header class="flex items-center justify-between gap-4 border-b border-slate-200 p-5">
                <div>
                    <h2 id="calf-history-title" class="text-lg font-bold">Calf Drop History</h2>
                    <p x-show="calfHistory" class="mt-1 text-sm text-slate-500"
                        x-text="calfHistory ? calfHistory.sheet + ' · ' + calfHistory.rows.length + ' rows · ' + calfHistory.columnCount + ' columns' : ''"></p>
                </div>
                <form method="dialog"><button type="submit" autofocus class="cbed-close-button" aria-label="Close"><span aria-hidden="true">&times;</span></button></form>
            </header>
            <div class="min-h-0 overflow-auto" tabindex="0" role="region" aria-label="Calf Drop History worksheet">
                <p x-show="calfHistoryLoading" class="p-5 text-sm text-slate-500" role="status">Loading calf history…</p>
                <div x-show="calfHistoryError" class="flex items-center gap-3 p-5 text-sm">
                    <p role="alert" class="text-rose-700" x-text="calfHistoryError"></p>
                    <button type="button" @click="loadCalfHistory()" class="font-semibold text-blue-700 underline">Retry</button>
                </div>
                <table x-show="calfHistory" class="calf-history-table w-max text-sm">
                    <thead>
                        <template x-for="(row, rowIndex) in (calfHistory?.rows.slice(0, 4) ?? [])" :key="rowIndex">
                            <tr>
                                <template x-for="([value, columns, rows], cellIndex) in row" :key="cellIndex">
                                    <th :colspan="columns" :rowspan="rows" x-text="value"></th>
                                </template>
                            </tr>
                        </template>
                    </thead>
                    <tbody>
                        <template x-for="(row, rowIndex) in (calfHistory?.rows.slice(4) ?? [])" :key="rowIndex">
                            <tr>
                                <template x-for="([value, columns, rows], cellIndex) in row" :key="cellIndex">
                                    <td :colspan="columns" :rowspan="rows" x-text="value"></td>
                                </template>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <form method="dialog" class="flex justify-end border-t border-slate-200 p-4">
                <button type="submit" class="rounded-lg bg-[#0b1f3a] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#122b4d]">Close</button>
            </form>
        </div>
    </dialog>

    @include('partials.calf-drop-notifications')

    {{-- Directory and filters --}}
    <section class="cbed-card dashboard-panel overflow-hidden rounded-xl border">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-lg font-bold text-slate-900">Calf Records</h2><p class="mt-1 text-sm text-slate-500">Search, filter, view, import, export, or add calf records.</p></div><div class="flex flex-wrap items-center gap-2"><span class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700"><span x-text="filteredRecords.length"></span> records</span><x-reset-filters x-on:click="resetFilters()" /></div></div>
        <div data-mobile-filters class="grid grid-cols-1 gap-3 border-b border-slate-200 bg-slate-50 p-5 sm:grid-cols-2 xl:grid-cols-7">
            <div class="min-w-0 xl:col-span-2">
                <label for="calf-record-search" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search Records</label>
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                    <input id="calf-record-search" x-ref="recordSearch" type="search" x-model="search" @input="page=1" @keydown.escape="search='';page=1" placeholder="Calf ID, farmer, or location" class="filter-input calf-record-search">
                    <button x-show="search" type="button" @click="search='';page=1;$refs.recordSearch.focus()" aria-label="Clear search" class="cbed-close-button absolute inset-y-1 right-1">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
            <div class="min-w-0">
                <label for="calf-cooperative-filter" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Cooperative</label>
                <select id="calf-cooperative-filter" x-model="filterCooperative" @change="page=1" class="filter-input">
                    <option value="">All Cooperatives</option>
                    <template x-for="cooperative in cooperativeFilterOptions" :key="cooperative">
                        <option :value="cooperative" x-text="cooperative"></option>
                    </template>
                </select>
            </div>
            <div><label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Province</label><select x-model="filterProvince" @change="page=1" class="filter-input"><option value="">All Provinces</option><template x-for="v in options('province')" :key="v"><option :value="v" x-text="v"></option></template></select></div>
            <div><label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Breed</label><select x-model="filterBreed" @change="page=1" class="filter-input"><option value="">All Breeds</option><template x-for="v in options('breed')" :key="v"><option :value="v" x-text="v"></option></template></select></div>
            <div><label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Sex</label><select x-model="filterSex" @change="page=1" class="filter-input"><option value="">All Calf Sexes</option><option value="Male">Male</option><option value="Female">Female</option></select></div>
            <div>
                <label for="calf-reported-year" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Reported Year</label>
                <select id="calf-reported-year" x-model="filterReportedYear" @change="changeReportedYear($event.target.value)" class="filter-input">
                    <option value="">All Reported Years</option>
                    <template x-for="year in reportedYearOptions" :key="year"><option :value="year" x-text="year"></option></template>
                </select>
            </div>
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
            <template x-for="(r, i) in paginatedRecords()" :key="r.id"><tr class="hover:bg-blue-50/50"><td class="px-4 py-4 text-slate-500" x-text="(page-1)*perPage+i+1"></td><td class="px-4 py-4 font-bold text-slate-900"><span x-text="r.calf_id || '—'"></span><span x-show="r.needs_review" class="mt-1 block text-xs font-semibold text-amber-800">Needs review</span></td><td class="px-4 py-4" x-text="r.mother_id || '—'"></td><td class="px-4 py-4" x-text="r.farmer || '—'"></td><td class="px-4 py-4" x-text="r.province || '—'"></td><td class="px-4 py-4" x-text="r.cooperative || '—'"></td><td class="px-4 py-4" x-text="formatBirthDate(r.date_of_birth || r.monitored_date)"></td><td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="r.sex==='Male'?'bg-blue-50 text-blue-700':r.sex==='Female'?'bg-pink-50 text-pink-700':'bg-slate-100 text-slate-600'" x-text="r.sex || '—'"></span></td><td class="px-4 py-4" x-text="r.breed || '—'"></td><td class="px-4 py-4" x-text="r.sire_id || '—'"></td><td class="px-4 py-4" x-text="r.reported_year || '—'"></td><td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="r.birth_outcome==='Stillbirth'?'bg-rose-50 text-rose-700':r.birth_outcome==='Abortion'?'bg-amber-50 text-amber-700':'bg-emerald-50 text-emerald-700'" x-text="r.birth_outcome || 'Live Birth'"></span></td><td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="r.animal_status==='Live'?'bg-emerald-50 text-emerald-700':r.animal_status==='Out'?'bg-slate-100 text-slate-700':r.animal_status==='Dead'?'bg-rose-50 text-rose-700':'bg-amber-50 text-amber-700'" x-text="r.animal_status || '—'"></span></td><td class="whitespace-nowrap px-4 py-4"><button type="button" @click="view(r)" class="cbed-record-action cbed-record-action--view" title="View details" aria-label="View details"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>@if(auth()->user()->isAdmin())<button type="button" @click="startEdit(r)" class="cbed-record-action cbed-record-action--edit" title="Edit calf record" aria-label="Edit calf record"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></button><button type="button" @click="remove(r)" :disabled="deletingCalf" aria-label="Delete calf record" class="cbed-record-action cbed-record-action--delete" title="Delete record"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>@endif</td></tr></template>
            <tr x-show="filteredRecords.length===0"><td colspan="14" class="px-5 py-12 text-center text-slate-500"><i class="fa-solid fa-folder-open mb-2 block text-2xl text-slate-300"></i>No calf drop records found. Change your filters or add records.</td></tr>
                </tbody>
            </table>
        </div>
        @include('partials.table-pagination', ['count' => 'filteredRecords.length', 'page' => 'page', 'size' => 'perPage', 'pages' => 'totalPages()', 'unfilteredCount' => 'allRecords.length'])
    </section>
    <footer class="py-5 text-center text-xs text-slate-400">CBED Data System · Calf Records</footer>

    @include('partials.calf-entry-modal')

    {{-- View modal --}}
    <div x-cloak x-show="showView" x-transition.opacity class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/60 p-4"><div @click.outside="showView = false" class="w-full max-w-3xl overflow-hidden rounded-xl bg-white shadow-xl"><div class="flex items-center justify-between border-b border-slate-200 p-5"><div><h3 class="text-lg font-bold">Calf Drop Record Details</h3><p class="mt-1 text-sm text-slate-500">Full calf birth record</p></div><button @click="showView = false" type="button" class="cbed-close-button" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3"><template x-for="f in fields" :key="f"><div class="rounded-lg border border-slate-200 p-3"><p class="text-xs text-slate-500" x-text="label(f)"></p><p class="mt-1 break-words font-semibold text-slate-800" x-text="selected && selected[f] != null && selected[f] !== '' ? selected[f] : '-' "></p></div></template></div><div class="flex justify-end border-t border-slate-200 p-4"><button @click="showView = false" type="button" class="rounded-lg bg-[#0b1f3a] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#122b4d]">Close</button></div></div></div>
    <div x-cloak x-show="showEdit" x-transition.opacity class="fixed inset-0 z-[65] flex items-center justify-center bg-slate-950/60 p-4"><div @click.outside="closeEdit()" class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-xl bg-white shadow-xl"><form @submit.prevent="saveEdit()"><div class="flex items-center justify-between border-b border-slate-200 p-5"><div><h3 class="text-lg font-bold">Edit Calf Record</h3><p class="mt-1 text-sm text-slate-500">Calf ID and linked mother ID are protected from changes.</p></div><button @click="closeEdit()" type="button" class="cbed-close-button" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2"><div><label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Calf ID (locked)</label><input :value="editForm?.calf_id||''" readonly class="form-input bg-slate-100"></div><div><label class="mb-1 block text-xs font-semibold uppercase text-slate-500" x-text="editForm.dam_unlinked ? 'Mother ID to link' : 'Mother ID (locked)'"></label><input x-show="editForm.dam_unlinked" x-model="editForm.resolve_dam" :placeholder="editForm.mother_id || 'Enter an inventory Mother ID'" class="form-input"><input x-show="!editForm.dam_unlinked" :value="editForm.mother_id || ''" readonly class="form-input bg-slate-100"></div><div><label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Date of Birth</label><input type="date" x-model="editForm.date_of_birth" :required="!editForm.needs_review" class="form-input"></div><div><label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Sex</label><select x-model="editForm.sex" :required="!editForm.needs_review && editForm.birth_outcome === &quot;Live Birth&quot;" class="form-input"><option value=""></option><option value="Male">Male</option><option value="Female">Female</option></select></div><div x-show="editForm?.source!=='Calf Drop'"><label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Breed</label><input x-model="editForm.breed" class="form-input"></div><div><label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Sire / Bull ID</label><input :value="editForm.sire_id" @input="editForm.sire_id = uppercaseSireInput($event.target)" class="form-input"></div><div><label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Animal Status</label><select x-model="editForm.animal_status" class="form-input"><option value="Live">Live</option><option value="Out">Out</option><option value="Dead">Dead</option><option value="Not Applicable">Not Applicable</option></select></div><div x-show="editForm?.source!=='Inventory'"><label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Monitored Date</label><input type="date" x-model="editForm.monitored_date" class="form-input"></div><div x-show="editForm?.source!=='Inventory'"><label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Birth Outcome</label><select x-model="editForm.birth_outcome" class="form-input"><option value="Live Birth">Live Birth</option><option value="Stillbirth">Stillbirth</option><option value="Abortion">Abortion</option></select></div><div x-show="editForm?.source!=='Inventory'" class="sm:col-span-2"><label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Remarks</label><textarea x-model="editForm.remarks" rows="3" class="form-input"></textarea></div></div><div class="flex justify-end gap-2 border-t border-slate-200 p-4"><button @click="closeEdit()" type="button" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold">Cancel</button><button type="submit" :disabled="savingEdit" class="cbed-save-button"><span x-text="savingEdit?'Saving&hellip;':'Update Record'"></span></button></div></form></div></div>
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
    .calf-history-table { border-collapse: separate; border-spacing: 0; }
    .calf-history-table thead { position: sticky; top: 0; z-index: 1; background: #103c60; color: #fff; }
    .calf-history-table th, .calf-history-table td { border: solid #cbd5e1; border-width: 0 1px 1px 0; padding: .35rem .55rem; white-space: pre-wrap; }
    .calf-history-table td { min-width: 3rem; }
    .calf-history-table tbody tr:nth-child(even) { background: #f8fafc; }
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

@include('partials.calf-entry-script')
