@props(['href' => null])

@php($attributes = $attributes->class('inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50'))

@if($href)
    <a href="{{ $href }}" {{ $attributes }}>
        <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Reset Filters
    </a>
@else
    <button type="button" {{ $attributes }}>
        <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Reset Filters
    </button>
@endif
