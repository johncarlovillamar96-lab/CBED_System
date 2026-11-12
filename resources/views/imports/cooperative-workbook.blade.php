@extends('layouts.admin')

@section('title', 'Import Cooperative Workbook')
@section('page-heading', 'Import Cooperative Workbook')

@section('content')
<div class="mx-auto max-w-none space-y-6">
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Bulk workbook staging</h1>
                <p class="mt-1 max-w-4xl text-sm text-slate-600">Add multiple cooperative workbooks. Files are staged one at a time, checked for duplicate uploads and approved worksheets, then matched by exact workbook cooperative names or codes. Review the preview and confirm before any data is written.</p>
            </div>
        </div>
        @if (! $bulkTablesReady)
            <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Bulk staging is unavailable until its database migration is applied. Run <code>php artisan migrate</code> and reload this page.</p>
        @else
            <div class="mt-5 grid gap-3 md:grid-cols-[1fr_auto]">
                <label id="bulk-dropzone" for="bulk-files" class="flex min-h-28 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 p-5 text-center hover:border-blue-400 hover:bg-blue-50">
                    <i class="fa-solid fa-cloud-arrow-up text-2xl text-slate-500"></i>
                    <span class="mt-2 text-sm font-semibold text-slate-800">Drop workbooks here or choose files</span>
                    <span class="mt-1 text-xs text-slate-500">.xlsx, .xls, .xlsm · 50 MB maximum per workbook</span>
                </label>
                <input id="bulk-files" type="file" multiple accept=".xlsx,.xls,.xlsm" class="sr-only">
                <div class="flex flex-col justify-center gap-2">
                    <button id="bulk-stage-button" type="button" disabled class="rounded-lg bg-[#0b1f3a] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#122b4d] disabled:cursor-not-allowed disabled:opacity-40">Stage selected files</button>
                    <button id="bulk-confirm-button" type="button" disabled class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-40">Confirm and import ready files</button>
                    <button id="bulk-cancel-button" type="button" hidden class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Cancel unconfirmed batch</button>
                </div>
            </div>
            <p id="bulk-message" role="status" class="mt-3 text-sm text-slate-600"></p>
            <div id="bulk-preview" class="mt-4 space-y-3"></div>
            <p class="mt-4 rounded-lg bg-blue-50 p-3 text-xs text-blue-900">Preview counts show detected rows, basic header/required-column validation, and exact duplicate rows within each sheet. Existing-record matching and cross-worksheet reconciliation are applied again during import. Only the 12 approved sheets are read; COMMENTS_JAN2026, Pedigree, Summary, and Semen are excluded.</p>
        @endif
    </section>

    @if ($bulkTablesReady && $batches->isNotEmpty())
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-bold text-slate-900">Bulk batch history</h2>
                <p class="mt-1 text-sm text-slate-500">Each workbook is processed independently. Failed workbooks can be retried without re-importing completed workbooks.</p>
            </div>
            <div class="divide-y divide-slate-200">
                @foreach ($batches as $batch)
                    <details class="group" @if ($loop->first) open @endif>
                        <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-3 px-5 py-3 hover:bg-slate-50">
                            <span class="font-semibold text-slate-800">Batch #{{ $batch->id }} · {{ $batch->created_at }}</span>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ ucfirst($batch->status) }} · {{ $batch->files_completed }} completed · {{ $batch->files_failed }} failed</span>
                        </summary>
                        <div class="overflow-x-auto border-t border-slate-100 px-5 py-3">
                            <table class="min-w-full text-left text-xs">
                                <thead class="text-slate-500"><tr><th class="px-2 py-2">Workbook</th><th class="px-2 py-2">Cooperative</th><th class="px-2 py-2">Status</th><th class="px-2 py-2">Review</th><th class="px-2 py-2">Action</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($batch->files as $batchFile)
                                        <tr>
                                            <td class="px-2 py-2 font-medium text-slate-700">{{ $batchFile->original_filename }}</td>
                                            <td class="px-2 py-2 text-slate-600">{{ $batchFile->cooperative_name ?? 'Needs mapping' }}</td>
                                            <td class="px-2 py-2">{{ ucfirst(str_replace('_', ' ', $batchFile->status)) }}</td>
                                            <td class="max-w-xl px-2 py-2 text-slate-500">
                                                @if ($batchFile->error_message){{ $batchFile->error_message }}@endif
                                                @php($missingSheets = json_decode($batchFile->missing_sheets ?? '[]', true) ?: [])
                                                @if (count($missingSheets) > 0)Missing: {{ implode(', ', $missingSheets) }}@endif
                                                @php($sheetPreview = json_decode($batchFile->sheet_summary ?? '[]', true) ?: [])
                                                @if (count($sheetPreview) > 0)
                                                    <details class="mt-1"><summary class="cursor-pointer font-semibold text-slate-700">Preview counts</summary>
                                                        @foreach ($sheetPreview as $sheetCount)
                                                            <span class="mr-2 inline-block">{{ $sheetCount['label'] ?? 'Worksheet' }}: {{ $sheetCount['rows_detected'] ?? 0 }} rows, {{ $sheetCount['valid_rows'] ?? 0 }} valid, {{ $sheetCount['invalid_rows'] ?? 0 }} invalid, {{ $sheetCount['duplicate_rows'] ?? 0 }} exact duplicates</span>
                                                        @endforeach
                                                    </details>
                                                @endif
                                                @if ($batchFile->workbook_import_id)
                                                    <a class="ml-1 font-semibold text-blue-700 underline" href="#workbook-import-{{ $batchFile->workbook_import_id }}">View import report</a>
                                                @endif
                                            </td>
                                            <td class="px-2 py-2">
                                                @if ($batchFile->retryable)
                                                    <button type="button" data-retry-batch="{{ $batch->id }}" data-retry-file="{{ $batchFile->id }}" class="rounded bg-amber-600 px-2 py-1 font-semibold text-white hover:bg-amber-700">Retry</button>
                                                @elseif ($batchFile->status === 'queued')
                                                    <button type="button" data-import-batch="{{ $batch->id }}" data-import-file="{{ $batchFile->id }}" class="rounded bg-blue-700 px-2 py-1 font-semibold text-white hover:bg-blue-800">Continue</button>
                                                @elseif ($batchFile->status === 'processing')
                                                    <span class="text-slate-400">Processing</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endforeach
            </div>
        </section>
    @endif
    @if ($pendingIdentifiers->isNotEmpty())
        <section class="overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-sm">
            <div class="border-b border-amber-100 bg-amber-50 px-5 py-4">
                <h2 class="font-bold text-amber-950">Cooperative identifiers pending verification</h2>
                <p class="mt-1 text-sm text-amber-900">These records are not included in any cooperative until an administrator confirms their source label. Mapping updates linked records; pending raw workbook rows must be re-imported after mapping.</p>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach ($pendingIdentifiers as $identifier)
                    <div class="flex flex-wrap items-center justify-between gap-4 px-5 py-4">
                        <div>
                            <p class="font-semibold text-slate-800">{{ $identifier->source_label }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ number_format($identifier->record_count) }} stored record(s) · {{ number_format($identifier->row_count) }} raw workbook row(s) awaiting review</p>
                        </div>
                        <form method="POST" action="{{ route('imports.cooperative-workbook.unmapped.map', $identifier->id) }}" class="flex flex-wrap items-center gap-2" onsubmit="return confirm('Map this source label to the selected cooperative and update its stored records?');">
                            @csrf
                            <label class="sr-only" for="pending-cooperative-{{ $identifier->id }}">Map {{ $identifier->source_label }} to</label>
                            <select id="pending-cooperative-{{ $identifier->id }}" name="cooperative_id" required class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                                <option value="">Select verified cooperative</option>
                                @foreach ($cooperatives as $cooperative)
                                    <option value="{{ $cooperative->id }}">{{ $cooperative->cooperative_name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="rounded-lg bg-amber-700 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-800">Confirm mapping</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if (! $historyTablesReady)
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-semibold">Workbook import setup is incomplete.</p>
            <p class="mt-1">The import progress tables have not been created in the database. From the project folder, run:</p>
            <code class="mt-2 inline-block rounded bg-white px-3 py-2 font-mono text-sm">php artisan migrate</code>
            <p class="mt-2">Then reload this page. No workbook has been imported yet.</p>
        </div>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-5">
            <h1 class="text-xl font-bold text-slate-900">Import a cooperative worksheet</h1>
            <p class="mt-1 text-sm text-slate-600">Choose a workbook in the cooperative row and import only the matching worksheet from that cell. New records are added and matches are updated; omitted records and their history are kept.</p>
        </div>

        @if (! $historyTablesReady)
            <p class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600">Import is unavailable until the database migration is applied.</p>
        @elseif ($cooperatives->isEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Create a cooperative before importing a workbook.</div>
        @else
            <p class="mb-3 text-xs text-slate-500">Select the cooperative’s Excel workbook in the cell for the sheet you want to import. Supported files: .xlsx, .xls, .xlsm; maximum 50 MB.</p>
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">
                    @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="min-w-[2200px] w-full text-left text-xs">
                    <thead class="bg-slate-50 uppercase text-slate-500">
                        <tr>
                            <th class="sticky left-0 z-10 min-w-64 bg-slate-50 px-3 py-3 font-semibold">Cooperative</th>
                            @foreach ($sheets as $sheet)
                                <th class="min-w-60 px-3 py-3 font-semibold">{{ $sheet['label'] }}<span class="mt-1 block normal-case font-normal text-slate-400">{{ $sheet['module'] }}</span></th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($cooperatives as $cooperative)
                            <tr class="align-top hover:bg-slate-50/70">
                                <th scope="row" class="sticky left-0 z-10 bg-white px-3 py-4 text-left font-semibold text-slate-800">{{ $cooperative->cooperative_name }}</th>
                                @foreach ($sheets as $sheet)
                                    @php($latest = $latestByCooperative[$cooperative->id][$sheet['key']] ?? null)
                                    <td class="px-3 py-3">
                                        @if ($latest)
                                            <span class="mb-2 inline-flex rounded-full px-2 py-1 text-[10px] font-semibold {{ $latest->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($latest->status === 'failed' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-600') }}">
                                                {{ ucfirst(str_replace('_', ' ', $latest->status)) }} · {{ number_format($latest->rows_imported) }}
                                            </span>
                                        @else
                                            <span class="mb-2 inline-flex rounded-full bg-slate-100 px-2 py-1 text-[10px] font-medium text-slate-500">Not imported</span>
                                        @endif
                                        <form method="POST" action="{{ route('imports.cooperative-workbook.import-sheet', [$cooperative->id, $sheet['key']]) }}" enctype="multipart/form-data" class="space-y-2">
                                            @csrf
                                            <input type="file" name="file" accept=".xlsx,.xls,.xlsm" required aria-label="Select workbook for {{ $sheet['label'] }} and {{ $cooperative->cooperative_name }}" class="block w-full rounded border border-slate-300 bg-white p-1.5 text-[10px]">
                                            <button type="submit" class="inline-flex items-center gap-1 rounded bg-[#0b1f3a] px-2.5 py-1.5 text-[10px] font-semibold text-white hover:bg-[#122b4d]">
                                                <i class="fa-solid fa-file-import"></i> Import {{ $sheet['label'] }}
                                            </button>
                                        </form>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="mt-5 rounded-xl bg-blue-50 p-4">
            <p class="text-xs text-blue-800">Only these 12 worksheets are imported: BD_ALL, BD_Out, BCS, FHPR_Breeding, Mating, Mating (2), Health Nutrition, MILK, MILK (2), Meat, MANURE BASED-PRODUCTS, and MTD. COMMENTS_JAN2026, Pedigree, Summary, and Semen are ignored. Imports are cumulative and do not delete existing animals or historical data.</p>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="font-bold text-slate-900">Workbook import progress</h2>
                <p class="mt-1 text-sm text-slate-500">Recent workbooks and the result for each worksheet. Clearing this history does not remove imported cooperative data.</p>
            </div>
            @if ($historyTablesReady && $imports->isNotEmpty())
                <form method="POST" action="{{ route('imports.cooperative-workbook.clear-history') }}" onsubmit="return confirm('Clear all workbook import progress history? Imported cooperative data will not be deleted.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-lg border border-rose-300 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">
                        <i class="fa-solid fa-trash-can mr-1"></i>Clear history
                    </button>
                </form>
            @endif
        </div>

        @if ($imports->isEmpty())
            <p class="p-8 text-center text-sm text-slate-500">No workbooks have been imported yet.</p>
        @else
            <div class="divide-y divide-slate-200">
                @foreach ($imports as $import)
                    <details id="workbook-import-{{ $import->id }}" class="group" @if ($loop->first) open @endif>
                        <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 px-5 py-4 hover:bg-slate-50">
                            <span class="min-w-0">
                                <span class="block truncate font-semibold text-slate-800">{{ $import->file_name }}</span>
                                <span class="mt-1 block text-xs text-slate-500">{{ $import->cooperative_name ?? 'Cooperative removed' }} · {{ $import->created_at }}</span>
                            </span>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold
                                {{ $import->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($import->status === 'completed_with_errors' || $import->status === 'failed' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">
                                {{ str_replace('_', ' ', ucfirst($import->status)) }}
                            </span>
                        </summary>
                        <div class="overflow-x-auto border-t border-slate-100 px-5 py-4">
                            <table class="min-w-full text-left text-sm">
                                <thead class="text-xs uppercase text-slate-500">
                                    <tr>
                                        <th class="px-3 py-2 font-semibold">Worksheet</th>
                                        <th class="px-3 py-2 font-semibold">Destination</th>
                                        <th class="px-3 py-2 font-semibold">Status</th>
                                        <th class="px-3 py-2 text-right font-semibold">Rows seen</th>
                                        <th class="px-3 py-2 text-right font-semibold">Inserted</th>
                                        <th class="px-3 py-2 text-right font-semibold">Updated</th>
                                        <th class="px-3 py-2 text-right font-semibold">Skipped</th>
                                        <th class="px-3 py-2 text-right font-semibold">Rejected</th>
                                        <th class="px-3 py-2 text-right font-semibold">Pending coop</th>
                                        <th class="px-3 py-2 font-semibold">Details</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($import->sheets as $sheet)
                                        <tr>
                                            <td class="whitespace-nowrap px-3 py-2.5 font-medium text-slate-700">{{ $sheet->sheet_name }}</td>
                                            <td class="whitespace-nowrap px-3 py-2.5 text-slate-600">{{ $sheet->target_module ?? '—' }}</td>
                                            <td class="px-3 py-2.5">
                                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold
                                                    {{ $sheet->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : (in_array($sheet->status, ['failed'], true) ? 'bg-rose-100 text-rose-800' : ($sheet->status === 'processing' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600')) }}">
                                                    {{ ucfirst(str_replace('_', ' ', $sheet->status)) }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2.5 text-right tabular-nums text-slate-600">{{ number_format($sheet->rows_seen) }}</td>
                                            <td class="px-3 py-2.5 text-right tabular-nums text-slate-700">{{ number_format($sheet->rows_inserted ?? 0) }}</td>
                                            <td class="px-3 py-2.5 text-right tabular-nums text-slate-700">{{ number_format($sheet->rows_updated ?? 0) }}</td>
                                            <td class="px-3 py-2.5 text-right tabular-nums text-slate-700">{{ number_format($sheet->rows_skipped ?? 0) }}</td>
                                            <td class="px-3 py-2.5 text-right tabular-nums text-slate-700">{{ number_format($sheet->rows_rejected ?? 0) }}</td>
                                            <td class="px-3 py-2.5 text-right tabular-nums text-amber-700">{{ number_format($sheet->rows_unmapped ?? 0) }}</td>
                                            <td class="min-w-64 px-3 py-2.5 text-xs text-slate-500">{{ $sheet->message }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const fileInput = document.getElementById('bulk-files');
    if (!fileInput) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const cooperatives = @json($cooperatives->map(fn ($cooperative) => ['id' => $cooperative->id, 'name' => $cooperative->cooperative_name])->values());
    const endpoints = {
        create: @json(route('imports.cooperative-workbook.batches.create')),
        stage: @json(route('imports.cooperative-workbook.batches.files.stage', ['batchId' => 'BATCH_ID'])),
        confirm: @json(route('imports.cooperative-workbook.batches.confirm', ['batchId' => 'BATCH_ID'])),
        cancel: @json(route('imports.cooperative-workbook.batches.cancel', ['batchId' => 'BATCH_ID'])),
        remove: @json(route('imports.cooperative-workbook.batches.files.remove', ['batchId' => 'BATCH_ID', 'fileId' => 'FILE_ID'])),
        import: @json(route('imports.cooperative-workbook.batches.files.import', ['batchId' => 'BATCH_ID', 'fileId' => 'FILE_ID'])),
        retry: @json(route('imports.cooperative-workbook.batches.files.retry', ['batchId' => 'BATCH_ID', 'fileId' => 'FILE_ID']))
    };
    const url = (template, batchId, fileId) => template.replace('BATCH_ID', batchId).replace('FILE_ID', fileId || '');
    const preview = document.getElementById('bulk-preview');
    const message = document.getElementById('bulk-message');
    const stageButton = document.getElementById('bulk-stage-button');
    const confirmButton = document.getElementById('bulk-confirm-button');
    const cancelButton = document.getElementById('bulk-cancel-button');
    const dropzone = document.getElementById('bulk-dropzone');
    let batchId = null;
    let stagedFiles = [];
    let stagedUploads = [];

    const setMessage = (text, isError = false) => {
        message.textContent = text;
        message.className = `mt-3 text-sm ${isError ? 'text-rose-700' : 'text-slate-600'}`;
    };
    const escapeText = (value) => String(value ?? '');
    async function request(target, options = {}) {
        const response = await fetch(target, {
            ...options,
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, ...(options.headers || {}) }
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(body.message || `Request failed (${response.status}).`);
        }
        return body;
    }

    function render() {
        preview.replaceChildren();
        stagedUploads.forEach((upload) => {
            const note = document.createElement('p');
            note.className = `rounded-lg p-3 text-sm ${upload.error ? 'bg-rose-50 text-rose-800' : 'bg-amber-50 text-amber-800'}`;
            note.textContent = upload.error || `${upload.filename}: duplicate file already staged.`;
            preview.append(note);
        });
        stagedFiles.forEach((file) => {
            const card = document.createElement('article');
            card.className = 'rounded-xl border border-slate-200 p-4';
            const top = document.createElement('div');
            top.className = 'flex flex-wrap items-start justify-between gap-3';
            const fileInfo = document.createElement('div');
            const title = document.createElement('p');
            title.className = 'font-semibold text-slate-800';
            title.textContent = `${escapeText(file.filename)} · ${(file.size / 1048576).toFixed(2)} MB`;
            const state = document.createElement('p');
            state.className = `mt-1 text-xs ${file.status === 'ready' ? 'text-emerald-700' : 'text-rose-700'}`;
            state.textContent = file.status === 'ready'
                ? (file.match_status === 'matched' ? 'Exact workbook match found; verify it below.' : `Cooperative match: ${file.match_status}. Select the correct cooperative.`)
                : `Not importable: ${file.error || file.status}`;
            fileInfo.append(title, state);
            top.append(fileInfo);

            if (file.status === 'ready') {
                const select = document.createElement('select');
                select.className = 'min-w-64 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm';
                select.setAttribute('aria-label', `Cooperative for ${file.filename}`);
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'Select cooperative';
                select.append(placeholder);
                cooperatives.forEach((coop) => {
                    const option = document.createElement('option');
                    option.value = coop.id;
                    option.textContent = coop.name;
                    option.selected = Number(file.cooperative_id) === Number(coop.id);
                    select.append(option);
                });
                select.addEventListener('change', () => {
                    file.cooperative_id = select.value ? Number(select.value) : null;
                    confirmButton.disabled = !stagedFiles.some((item) => item.status === 'ready')
                        || stagedFiles.some((item) => item.status === 'ready' && !item.cooperative_id);
                });
                top.append(select);
            }
            if (file.id && batchId) {
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'rounded border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700';
                remove.textContent = 'Remove file';
                remove.addEventListener('click', async () => {
                    try {
                        await request(url(endpoints.remove, batchId, file.id), { method: 'DELETE' });
                        stagedFiles = stagedFiles.filter((item) => item.id !== file.id);
                        render();
                    } catch (error) { setMessage(error.message, true); }
                });
                top.append(remove);
            }
            card.append(top);
            if (file.evidence?.workbook_codes?.length || file.evidence?.workbook_names?.length) {
                const evidence = document.createElement('p');
                evidence.className = 'mt-2 text-xs text-slate-500';
                evidence.textContent = `Workbook codes: ${(file.evidence.workbook_codes || []).join(', ') || 'none'} · Names: ${(file.evidence.workbook_names || []).join(', ') || 'none'}`;
                card.append(evidence);
            }
            if (file.missing_sheets?.length) {
                const missing = document.createElement('p');
                missing.className = 'mt-2 text-xs text-amber-800';
                missing.textContent = `Missing approved sheets (${file.missing_sheets.length}): ${file.missing_sheets.join(', ')}`;
                card.append(missing);
            }
            if (file.sheets?.length) {
                const details = document.createElement('details');
                details.className = 'mt-3';
                const summary = document.createElement('summary');
                summary.className = 'cursor-pointer text-xs font-semibold text-slate-700';
                summary.textContent = `Preview ${file.sheets.length} worksheet(s)`;
                const table = document.createElement('div');
                table.className = 'mt-2 overflow-x-auto';
                const tableElement = document.createElement('table');
                tableElement.className = 'min-w-full text-left text-xs';
                const headers = ['Worksheet', 'Detected rows', 'Valid', 'Invalid', 'Exact duplicates'];
                const thead = document.createElement('thead');
                const headerRow = document.createElement('tr');
                headers.forEach((heading) => {
                    const th = document.createElement('th');
                    th.className = 'px-2 py-1 font-semibold text-slate-500';
                    th.textContent = heading;
                    headerRow.append(th);
                });
                thead.append(headerRow);
                const tbody = document.createElement('tbody');
                file.sheets.forEach((sheet) => {
                    const row = document.createElement('tr');
                    [sheet.label, sheet.rows_detected, sheet.valid_rows, sheet.invalid_rows, sheet.duplicate_rows].forEach((value) => {
                        const td = document.createElement('td');
                        td.className = 'px-2 py-1 text-slate-700';
                        td.textContent = escapeText(value);
                        row.append(td);
                    });
                    tbody.append(row);
                });
                tableElement.append(thead, tbody);
                table.append(tableElement);
                details.append(summary, table);
                card.append(details);
            }
            preview.append(card);
        });
        cancelButton.hidden = !batchId;
        confirmButton.disabled = !batchId || !stagedFiles.some((file) => file.status === 'ready')
            || stagedFiles.some((file) => file.status === 'ready' && !file.cooperative_id);
    }

    fileInput.addEventListener('change', () => {
        stageButton.disabled = fileInput.files.length === 0;
        if (fileInput.files.length) setMessage(`${fileInput.files.length} file(s) selected. They will be uploaded one at a time.`);
    });
    dropzone?.addEventListener('dragover', (event) => {
        event.preventDefault();
        dropzone.classList.add('border-blue-500', 'bg-blue-50');
    });
    dropzone?.addEventListener('dragleave', () => dropzone.classList.remove('border-blue-500', 'bg-blue-50'));
    dropzone?.addEventListener('drop', (event) => {
        event.preventDefault();
        dropzone.classList.remove('border-blue-500', 'bg-blue-50');
        fileInput.files = event.dataTransfer.files;
        fileInput.dispatchEvent(new Event('change'));
    });

    stageButton?.addEventListener('click', async () => {
        const files = Array.from(fileInput.files);
        if (!files.length) return;
        stageButton.disabled = true;
        try {
            if (!batchId) batchId = (await request(endpoints.create, { method: 'POST' })).batch_id;
            for (let index = 0; index < files.length; index++) {
                const file = files[index];
                setMessage(`Staging workbook ${index + 1} of ${files.length}: ${file.name}`);
                const formData = new FormData();
                formData.append('file', file);
                try {
                    const result = await request(url(endpoints.stage, batchId), { method: 'POST', body: formData });
                    if (result.duplicate) {
                        stagedUploads.push({ filename: file.name, duplicate: true });
                    } else {
                        stagedFiles.push(result.file);
                    }
                } catch (error) {
                    stagedUploads.push({ filename: file.name, error: `${file.name}: ${error.message}` });
                }
                render();
            }
            fileInput.value = '';
            setMessage(`Staging finished. Review cooperative matches and worksheet previews before confirming.`);
        } catch (error) {
            setMessage(error.message, true);
        } finally {
            stageButton.disabled = fileInput.files.length === 0;
            render();
        }
    });

    async function importOne(id, fileId) {
        return request(url(endpoints.import, id, fileId), { method: 'POST' });
    }
    async function runQueued(id, files) {
        let completed = 0;
        for (const file of files) {
            setMessage(`Importing workbook ${++completed} of ${files.length}: ${file.filename || `#${file.id}`}`);
            try {
                await importOne(id, file.id);
            } catch (error) {
                setMessage(`${file.filename || `Workbook #${file.id}`} failed: ${error.message}`, true);
            }
        }
        setMessage('Batch processing finished. The import report below lists worksheet-level outcomes.');
        window.location.reload();
    }

    confirmButton?.addEventListener('click', async () => {
        const ready = stagedFiles.filter((file) => file.status === 'ready');
        if (!ready.length || ready.some((file) => !file.cooperative_id)) return;
        const cooperativeIds = {};
        ready.forEach((file) => cooperativeIds[file.id] = file.cooperative_id);
        confirmButton.disabled = true;
        try {
            await request(url(endpoints.confirm, batchId), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ cooperative_ids: cooperativeIds })
            });
            await runQueued(batchId, ready.map((file) => ({ id: file.id, filename: file.filename })));
        } catch (error) {
            setMessage(error.message, true);
            confirmButton.disabled = false;
        }
    });

    cancelButton?.addEventListener('click', async () => {
        if (!batchId || !window.confirm('Cancel this unconfirmed batch and remove its staged workbooks?')) return;
        try {
            await request(url(endpoints.cancel, batchId), { method: 'DELETE' });
            window.location.reload();
        } catch (error) { setMessage(error.message, true); }
    });

    document.querySelectorAll('[data-retry-batch]').forEach((button) => {
        button.addEventListener('click', async () => {
            const id = button.dataset.retryBatch;
            const fileId = button.dataset.retryFile;
            button.disabled = true;
            try {
                await request(url(endpoints.retry, id, fileId), { method: 'POST' });
                await runQueued(id, [{ id: fileId, filename: button.closest('tr')?.cells[0]?.textContent }]);
            } catch (error) { setMessage(error.message, true); button.disabled = false; }
        });
    });
    document.querySelectorAll('[data-import-batch]').forEach((button) => {
        button.addEventListener('click', async () => {
            button.disabled = true;
            try {
                await runQueued(button.dataset.importBatch, [{ id: button.dataset.importFile }]);
            } catch (error) { setMessage(error.message, true); button.disabled = false; }
        });
    });
})();
</script>
@endpush
