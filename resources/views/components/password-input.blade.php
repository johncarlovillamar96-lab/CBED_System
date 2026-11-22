@props(['name' => 'password'])

<span class="relative mt-1 block">
    <input id="{{ $name }}" name="{{ $name }}" type="password" {{ $attributes->class([
        'block w-full rounded-lg border border-slate-300 py-2.5 pl-3 pr-12 font-normal',
    ]) }}>
    <button type="button" data-password-toggle aria-controls="{{ $name }}" aria-label="Show password"
            class="absolute right-1 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-md text-slate-600 hover:bg-slate-100">
        <svg data-password-show width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" />
            <circle cx="12" cy="12" r="2.5" />
        </svg>
        <svg data-password-hide class="hidden" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12ZM4 4l16 16" />
        </svg>
    </button>
</span>
