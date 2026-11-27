<nav class="cbed-mobile-nav" aria-label="Mobile navigation">
    <a href="{{ route(auth()->user()->role === \App\Models\User::TECHNICIAN ? 'ui.monitoring' : 'dashboard', absolute: false) }}"
        @if(request()->routeIs('dashboard', 'dashboard.page')) aria-current="page" @endif>
        <i class="fa-solid fa-house" aria-hidden="true"></i><span>Home</span>
    </a>
    <a href="{{ route('ui.monitoring', absolute: false) }}"
        @if(request()->routeIs('ui.monitoring', 'ui.monitoring.*')) aria-current="page" @endif>
        <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i><span>Monitoring</span>
    </a>
    <a href="{{ route('ui.monitoring', absolute: false) }}#monitoring-record-table" data-mobile-search>
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>Search</span>
    </a>
    <button type="button" @click="sidebarOpen = !sidebarOpen" :aria-expanded="sidebarOpen.toString()" aria-controls="cbed-sidebar">
        <i class="fa-solid fa-bars" aria-hidden="true"></i><span>More</span>
    </button>
</nav>
