<div x-show="notice" x-cloak x-transition.opacity.duration.200ms
    class="cbed-mobile-floating fixed inset-x-4 bottom-4 {{ ($isConnectionToast ?? false) ? 'z-[65]' : 'z-[70]' }} flex items-center gap-3 rounded-xl border bg-white p-4 shadow-xl sm:left-auto sm:bottom-6 sm:right-6 sm:w-96"
    :class="{ 'border-rose-200': noticeType === 'error', 'border-blue-200': noticeType === 'info', 'border-emerald-200': noticeType === 'success' }">
    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
        :class="{ 'bg-rose-100 text-rose-700': noticeType === 'error', 'bg-blue-100 text-blue-700': noticeType === 'info', 'bg-emerald-100 text-emerald-700': noticeType === 'success' }" aria-hidden="true">
        <i class="fa-solid" :class="{ 'fa-exclamation': noticeType === 'error', 'fa-info': noticeType === 'info', 'fa-check': noticeType === 'success' }"></i>
    </span>
    <div role="status" class="min-w-0 flex-1">
        <p class="text-sm font-semibold text-slate-800" x-text="notice"></p>
        @if(($isConnectionToast ?? false) || ($showDescription ?? false))
            <p class="mt-1 text-xs text-slate-600" x-show="description" x-text="description"></p>
        @endif
    </div>
    <button type="button" @click="notice=''" aria-label="Dismiss notification"
        class="cbed-close-button">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
