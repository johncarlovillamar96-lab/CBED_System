<style>
    .cbed-page-button { border: 1px solid #cbd5e1; border-radius: .5rem; background: white; padding: .5rem .75rem; font-size: .875rem; font-weight: 600; color: #334155; }
    .cbed-page-button:hover:not(:disabled):not([aria-disabled="true"]) { background: #f1f5f9; }
    .cbed-page-button:disabled, .cbed-page-button[aria-disabled="true"] { opacity: .4; cursor: not-allowed; }
    @media screen { .cbed-row-off-page { display: none !important; } }
    @media print { [data-table-pager] { display: none !important; } }
</style>
<template id="cbed-table-pager">
    <div data-table-pager class="flex flex-col justify-between gap-3 border-t border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <p data-page-range class="text-sm text-slate-500" role="status"></p>
            <label class="flex items-center gap-2 text-sm text-slate-500">
                Rows per page
                <select class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm">
                    @foreach (\App\Support\TablePagination::SIZES as $option)
                        <option value="{{ $option }}" @selected($option === \App\Support\TablePagination::DEFAULT_SIZE)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="flex items-center gap-2">
            <button data-prev-page type="button" class="cbed-page-button">Previous</button>
            <span data-page-number class="whitespace-nowrap text-sm"></span>
            <button data-next-page type="button" class="cbed-page-button">Next</button>
        </div>
    </div>
</template>
<script defer src="{{ asset('js/table-pagination.js') }}"></script>
