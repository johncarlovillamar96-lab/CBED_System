@php
    $pageParam = $paginator->getPageName();
    $sizeParam = $sizeParam ?? 'per_page';
    $isHealthPager = $isHealthPager ?? false;
    $filterParams = $filterParams ?? request()->except([$pageParam, $sizeParam]);
@endphp
<div class="flex flex-col justify-between gap-3 border-t border-slate-200 px-5 py-4 sm:flex-row sm:items-center print:hidden">
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
        <p class="text-sm text-slate-500">
            Showing <b>{{ $paginator->firstItem() ?? 0 }}</b>–<b>{{ $paginator->lastItem() ?? 0 }}</b>
            of <b>{{ number_format($paginator->total()) }}</b>
            @isset($unfilteredTotal)
                (<b>{{ number_format($unfilteredTotal) }}</b> total)
            @endisset
        </p>
        <form method="get" action="{{ $paginator->path() }}" @if ($isHealthPager) data-health-page-size @endif>
            @foreach ($filterParams as $name => $value)
                @if (is_scalar($value))
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endif
            @endforeach
            <label class="flex items-center gap-2 text-sm text-slate-500">
                Rows per page
                <select name="{{ $sizeParam }}" @unless ($isHealthPager) onchange="this.form.submit()" @endunless class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm">
                    @foreach (\App\Support\TablePagination::SIZES as $option)
                        <option value="{{ $option }}" @selected($paginator->perPage() === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
            <noscript><button type="submit" class="cbed-page-button">Apply</button></noscript>
        </form>
    </div>
    <div class="flex items-center gap-2">
        @if ($paginator->onFirstPage())
            <button type="button" disabled class="cbed-page-button">Previous</button>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" @if ($isHealthPager) data-health-page="previous" @endif class="cbed-page-button">Previous</a>
        @endif
        <span class="whitespace-nowrap text-sm" @if ($isHealthPager) data-health-page-status tabindex="-1" @endif>Page <b>{{ $paginator->currentPage() }}</b> of <b>{{ $paginator->lastPage() }}</b></span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" @if ($isHealthPager) data-health-page="next" @endif class="cbed-page-button">Next</a>
        @else
            <button type="button" disabled class="cbed-page-button">Next</button>
        @endif
    </div>
</div>
