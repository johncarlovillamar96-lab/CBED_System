<div class="flex flex-col justify-between gap-3 border-t border-slate-200 px-5 py-4 sm:flex-row sm:items-center print:hidden">
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
        <p class="text-sm text-slate-500" role="status">
            Showing <b x-text="({{ $count }}) ? ({{ $page }} - 1) * {{ $size }} + 1 : 0"></b>–<b x-text="Math.min({{ $page }} * {{ $size }}, {{ $count }})"></b>
            of <b x-text="{{ $count }}"></b>
            @isset($unfilteredCount)
                (<b x-text="{{ $unfilteredCount }}"></b> total)
            @endisset
        </p>
        <label class="flex items-center gap-2 text-sm text-slate-500">
            Rows per page
            <select x-model.number="{{ $size }}" @change="{{ $page }} = 1" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm">
                @foreach (\App\Support\TablePagination::SIZES as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
        </label>
    </div>
    <div class="flex items-center gap-2">
        <button type="button" @click="{{ $page }} = Math.max(1, {{ $page }} - 1)" :disabled="{{ $page }} <= 1" class="cbed-page-button">Previous</button>
        <span class="whitespace-nowrap text-sm">Page <b x-text="{{ $page }}"></b> of <b x-text="{{ $pages }}"></b></span>
        <button type="button" @click="{{ $page }} = Math.min({{ $pages }}, {{ $page }} + 1)" :disabled="{{ $page }} >= {{ $pages }}" class="cbed-page-button">Next</button>
    </div>
</div>
