
{{-- resources/views/partials/sidebar.blade.php --}}

<aside
    x-data="{ mobileSidebarOpen: false }"
    class="w-0 shrink-0 lg:w-64"
>
    {{-- Mobile Sidebar Toggle --}}
    <button
        type="button"
        @click="mobileSidebarOpen = !mobileSidebarOpen"
        class="fixed bottom-5 left-5 z-[80] flex h-12 w-12 items-center justify-center rounded-full bg-[#0b1f3a] text-white shadow-lg lg:hidden"
    >
        <i class="fa-solid fa-bars text-lg"></i>
    </button>

    {{-- Mobile Overlay --}}
    <div
        x-show="mobileSidebarOpen"
        x-cloak
        @click="mobileSidebarOpen = false"
        class="fixed inset-0 z-[85] bg-black/50 lg:hidden"
    ></div>

    {{-- Sidebar Navigation --}}
    <aside
        :class="mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        class="fixed inset-y-0 left-0 z-[90] flex w-64 flex-col bg-[#0b1f3a] text-white shadow-xl transition-transform duration-300 lg:sticky lg:inset-y-auto lg:left-auto lg:top-0 lg:h-screen lg:translate-x-0"
    >
        {{-- Brand --}}
        <div class="border-b border-white/10 px-5 py-6">
            <a href="{{ url('/dashboard') }}" class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/10">
                    <i class="fa-solid fa-water text-xl text-blue-200"></i>
                </div>

                <div>
                    <h2 class="text-base font-bold tracking-wide">CBED DATA</h2>
                    <p class="mt-1 text-xs text-slate-300">Management System</p>
                </div>
            </a>
        </div>

        {{-- Navigation Links --}}
        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-5">

            <p class="mb-3 px-3 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">
                Main Menu
            </p>

            <a href="{{ url('/dashboard') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition hover:bg-white/10
                {{ request()->is('dashboard') ? 'bg-white/10 text-white' : 'text-slate-300' }}">
                <i class="fa-solid fa-chart-pie w-5 text-center"></i>
                Dashboard
            </a>

            <a href="{{ url('/farmers') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition hover:bg-white/10
                {{ request()->is('farmers') ? 'bg-white/10 text-white' : 'text-slate-300' }}">
                <i class="fa-solid fa-users w-5 text-center"></i>
                Farmers
            </a>

            <a href="{{ url('/cooperatives') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition hover:bg-white/10
                {{ request()->is('cooperatives') ? 'bg-white/10 text-white' : 'text-slate-300' }}">
                <i class="fa-solid fa-people-group w-5 text-center"></i>
                Cooperatives
            </a>

            <a href="{{ url('/carabaos') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition hover:bg-white/10
                {{ request()->is('carabaos') ? 'bg-white/10 text-white' : 'text-slate-300' }}">
                <i class="fa-solid fa-cow w-5 text-center"></i>
                Carabaos
            </a>

            <div class="px-3 pb-2 pt-5">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">
                    Animal Records
                </p>
            </div>

            <a href="{{ url('/mating') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition hover:bg-white/10
                {{ request()->is('mating') ? 'bg-white/10 text-white' : 'text-slate-300' }}">
                <i class="fa-solid fa-dna w-5 text-center"></i>
                Mating Records
            </a>

            <a href="{{ url('/calves') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition hover:bg-white/10
                {{ request()->is('calves*', 'calf-drop*') ? 'bg-white/10 text-white' : 'text-slate-300' }}">
                <i class="fa-solid fa-baby w-5 text-center"></i>
                Calf Drop
            </a>

            <a href="{{ url('/health') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition hover:bg-white/10
                {{ request()->is('health', 'health-nutrition') ? 'bg-white/10 text-white' : 'text-slate-300' }}">
                <i class="fa-solid fa-heart-pulse w-5 text-center"></i>
                Health & Nutrition
            </a>

            <a href="{{ url('/milk-production') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition hover:bg-white/10
                {{ request()->is('milk-production') ? 'bg-white/10 text-white' : 'text-slate-300' }}">
                <i class="fa-solid fa-bottle-water w-5 text-center"></i>
                Milk Production
            </a>

            <a href="{{ url('/mil-test') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition hover:bg-white/10
                {{ request()->is('mil-test') ? 'bg-white/10 text-white' : 'text-slate-300' }}">
                <i class="fa-solid fa-flask w-5 text-center"></i>
                Milk Test
            </a>

            <a href="{{ url('/nature-of-exit') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition hover:bg-white/10
                {{ request()->is('nature-of-exit') ? 'bg-white/10 text-white' : 'text-slate-300' }}">
                <i class="fa-solid fa-right-from-bracket w-5 text-center"></i>
                Nature of Exit
            </a>

            <div class="px-3 pb-2 pt-5">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">
                    Location
                </p>
            </div>

            <a href="{{ url('/mapping') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition hover:bg-white/10
                {{ request()->is('mapping') ? 'bg-white/10 text-white' : 'text-slate-300' }}">
                <i class="fa-solid fa-map-location-dot w-5 text-center"></i>
                Mapping
            </a>
        </nav>

        {{-- Footer --}}
        <div class="border-t border-white/10 p-4">
            <div class="flex items-center gap-3 rounded-lg bg-white/5 p-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-200 font-bold text-[#0b1f3a]">
                    A
                </div>

                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold">Administrator</p>
                    <p class="mt-0.5 text-xs text-slate-400">CBED Data System</p>
                </div>
            </div>
        </div>
    </aside>
</aside>
