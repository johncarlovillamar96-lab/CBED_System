@props(['title', 'description' => null, 'section' => null, 'breadcrumb' => null, 'tag' => null, 'tagIcon' => 'fa-solid fa-seedling'])

<header class="cbed-page-header mb-7 flex flex-col gap-5 rounded-2xl p-5 text-white sm:p-6 lg:flex-row lg:items-center lg:justify-between">
    <div class="flex min-w-0 items-center gap-4">
        <div class="cbed-page-header-brand hidden sm:flex" aria-hidden="true">
            <i class="fa-solid fa-cow"></i><span><i class="fa-solid fa-dna"></i></span>
        </div>
        <div class="min-w-0">
            <div class="mb-2 flex flex-wrap items-center gap-2 text-sm text-blue-100">
                <i class="fa-solid fa-house" aria-hidden="true"></i><span aria-hidden="true">/</span>
                <span>CBED Data System</span><span aria-hidden="true">/</span>
                @if($section)<span>{{ $section }}</span><span aria-hidden="true">/</span>@endif
                <b class="text-white">{{ $breadcrumb ?? $title }}</b>
            </div>
            <h1 class="text-2xl font-bold text-white sm:text-3xl">{{ $title }}</h1>
            @if($description)<p class="mt-1 text-sm text-blue-100">{{ $description }}</p>@endif
            @if($tag)<span class="cbed-page-header-tag"><i class="{{ $tagIcon }}" aria-hidden="true"></i> {{ $tag }}</span>@endif
        </div>
    </div>

    @if($slot->isNotEmpty())
        <div class="cbed-page-header-actions flex flex-wrap items-center gap-2 lg:justify-end">{{ $slot }}</div>
    @endif
</header>

@once
    @push('styles')
        <style>
            .cbed-page-header { position:relative; isolation:isolate; overflow:hidden; background:radial-gradient(circle at 90% 18%,rgba(255,196,56,.25),transparent 19%),linear-gradient(118deg,#082d50 0%,#075785 55%,#0786a5 100%); box-shadow:0 16px 32px rgba(7,62,99,.2); }
            .cbed-page-header::after { content:""; position:absolute; z-index:-1; width:19rem; height:19rem; right:11%; top:-14rem; border:1px solid rgba(255,214,92,.45); border-radius:50%; box-shadow:0 0 0 26px rgba(255,214,92,.08),0 0 0 54px rgba(255,255,255,.045); }
            .cbed-page-header-brand { position:relative; flex:none; width:4.35rem; height:4.35rem; align-items:center; justify-content:center; border:1px solid rgba(255,255,255,.34); border-radius:1.35rem; background:linear-gradient(145deg,rgba(255,255,255,.22),rgba(255,255,255,.07)); color:#ffd05b; font-size:2rem; box-shadow:0 10px 22px rgba(0,28,52,.2); }
            .cbed-page-header-brand span { position:absolute; right:-.45rem; bottom:-.4rem; display:grid; width:1.65rem; height:1.65rem; place-items:center; border:2px solid #075785; border-radius:999px; background:#ffd05b; color:#12345a; font-size:.75rem; }
            .cbed-page-header-tag { display:inline-flex; align-items:center; gap:.4rem; margin-top:.7rem; border:1px solid rgba(255,213,91,.35); border-radius:999px; background:rgba(4,37,68,.24); padding:.35rem .7rem; color:#ffe08a; font-size:.7rem; font-weight:700; letter-spacing:.035em; }
            .cbed-page-header-actions > :is(a, button, label) { display:inline-flex; align-items:center; justify-content:center; gap:.5rem; border-style:solid; border-width:1px; border-radius:.5rem; padding:.625rem 1rem; font-size:.875rem; font-weight:600; }
            .cbed-page-header-actions > :is(a, button, label):not(.cbed-add-button) { border-color:rgba(255,255,255,.28); background:rgba(255,255,255,.1); color:#fff; backdrop-filter:blur(8px); }
            .cbed-page-header-actions > :is(a, button, label):not(.cbed-add-button):hover { background:rgba(255,255,255,.2); }
            .cbed-page-header-actions > .action-primary:not(.cbed-add-button) { border-color:transparent; background:var(--cbed-add-bg); color:var(--cbed-add-text); box-shadow:0 7px 16px rgba(4,37,68,.2); }
            .cbed-page-header-actions > .action-primary:not(.cbed-add-button):hover { background:var(--cbed-add-hover); }
            .cbed-page-header-actions > :is(a, button, label) > i { color:inherit; }
        </style>
    @endpush
@endonce
