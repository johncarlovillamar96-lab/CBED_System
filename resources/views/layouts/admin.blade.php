<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') | CBED Data System</title>

    {{-- Tailwind CSS --}}
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- Font Awesome --}}
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    {{-- Tailwind Theme --}}
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        cbed: {
                            navy: '#0b1f3a',
                            hover: '#122b4d',
                            blue: '#2563eb'
                        }
                    }
                }
            }
        };
    </script>

    {{-- Alpine.js --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="/css/table-drag-scroll.css">
    <link rel="stylesheet" href="/css/cbed-buttons.css">
    <link rel="stylesheet" href="/css/cbed-mobile.css?v=3">
    <script defer src="/js/table-drag-scroll.js"></script>
    <script defer src="/js/cbed-mobile.js?v=3"></script>

    <style>
        :root {
            --cbed-navy: #0b1f3a;
            --cbed-hover: #122b4d;
            --page-bg: #f4f7fb;
            --sidebar-width: 13rem;
        }

        [x-cloak] {
            display: none !important;
        }

        html {
            font-size: 90%;
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            background: var(--page-bg);
            color: #1e293b;
        }
        

        .cbed-sidebar {
            width: var(--sidebar-width);
            background: var(--cbed-navy);
            transition: transform .25s ease;
        }

        .cbed-main {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            min-width: 0;
        }

        .cbed-nav-link {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .72rem .85rem;
            border-radius: .6rem;
            color: #cbd5e1;
            font-size: .9rem;
            font-weight: 500;
            transition: background .2s, color .2s;
        }

        .cbed-nav-link:hover {
            background: var(--cbed-hover);
            color: white;
        }

        .cbed-nav-link.active {
            background: #2563eb;
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, .2);
        }

        .cbed-nav-link i {
            width: 1.1rem;
            flex-shrink: 0;
            text-align: center;
        }

        .cbed-section-label {
            color: #94a3b8;
            font-size: .66rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            padding: .6rem .85rem .45rem;
        }

        .cbed-account-icon {
            background: #475569;
            color: #cbd5e1;
            transition: box-shadow .2s, background-color .2s, color .2s;
        }

        .cbed-account-icon.is-online {
            background: #123d35;
            color: #a7f3d0;
            box-shadow: 0 0 0 2px #34d399, 0 0 12px #34d39988;
        }

        .cbed-scrollbar::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }

        .cbed-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        .cbed-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        @media (max-width: 1023px) {
            .cbed-sidebar {
                transform: translateX(-100%);
                z-index: 60;
            }

            .cbed-sidebar.mobile-open {
                transform: translateX(0);
            }

            .cbed-main {
                margin-left: 0;
            }
        }

        @media (min-width: 1024px) {
            .cbed-sidebar {
                transform: translateX(0) !important;
            }
        }

    </style>

    @stack('styles')
    @include('partials.typography')
</head>

<body x-data="{ sidebarOpen: false, connected: navigator.onLine }"
      @keydown.escape.window="sidebarOpen = false"
      @online.window="connected = true" @offline.window="connected = false"
      @cbed-monitoring-connection.window="connected = $event.detail.online">

    @include('partials.connection-toast', ['dataSource' => $__env->yieldContent('dataSource')])

    {{-- MOBILE OVERLAY --}}
    <div
        x-cloak
        x-show="sidebarOpen"
        x-transition.opacity
        @click="sidebarOpen = false"
        class="fixed inset-0 z-50 bg-slate-950/50 lg:hidden">
    </div>

    {{-- SINGLE SIDEBAR --}}
    <aside
        id="cbed-sidebar"
        class="cbed-sidebar fixed inset-y-0 left-0 flex flex-col text-white"
        :class="{ 'mobile-open': sidebarOpen }">

        {{-- BRAND --}}
        <button type="button" @click="sidebarOpen = false" aria-label="Close menu"
            class="cbed-mobile-menu-close absolute right-1 top-1 z-10 rounded-lg text-white">×</button>
        <div class="flex min-h-[4.5rem] shrink-0 items-center gap-3 border-b border-white/10 bg-gradient-to-r from-[#16375a] to-transparent px-4 py-3">
            <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-[#ffe09a] bg-[#ffd05b] text-[#0b1f3a] shadow-sm">
                <i class="fa-solid fa-cow text-lg" aria-hidden="true"></i>
            </div>

            <div>
                <p class="text-xl font-extrabold leading-none tracking-wide text-white">CBED</p>
                <p class="mt-1 text-xs font-medium leading-tight text-slate-300">Data System</p>
            </div>
        </div>

        {{-- NAVIGATION --}}
        <nav class="cbed-scrollbar flex-1 space-y-1 overflow-y-auto px-3 py-4">

            @if(auth()->user()->role !== \App\Models\User::TECHNICIAN)
            <p class="cbed-section-label">Workspace</p>

            <a href="{{ url('/dashboard') }}"
               class="cbed-nav-link {{ request()->is('dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>

            <a href="{{ url('/farmers') }}"
               class="cbed-nav-link {{ request()->is('farmers*') ? 'active' : '' }}">
                <i class="fa-solid fa-users"></i>
                <span>Farmers</span>
            </a>

            <a href="{{ url('/cooperatives') }}"
               class="cbed-nav-link {{ request()->is('cooperatives*') ? 'active' : '' }}">
                <i class="fa-solid fa-people-group"></i>
                <span>Cooperatives</span>
            </a>

            <p class="cbed-section-label mt-5">Animal Management</p>

            <a href="{{ route('admin.carabao-inventory') }}"
               class="cbed-nav-link {{ request()->routeIs('admin.carabao-inventory') ? 'active' : '' }}">
                <i class="fa-solid fa-cow"></i>
                <span>Carabao Inventory</span>
            </a>

            <a href="{{ url('/mating') }}"
               class="cbed-nav-link {{ request()->is('mating*') ? 'active' : '' }}">
                <i class="fa-solid fa-dna"></i>
                <span>Mating</span>
            </a>

            <a href="{{ url('/fhpr') }}" class="cbed-nav-link {{ request()->is('fhpr*') ? 'active' : '' }}">
                <i class="fa-solid fa-heart-pulse"></i><span>Breeding Summary</span>
            </a>

            <a href="{{ url('/calves') }}"
               class="cbed-nav-link {{ request()->is('calves*', 'calf-drop*') ? 'active' : '' }}">
                <i class="fa-solid fa-baby"></i>
                <span>Calf Drop</span>
            </a>

            <a href="{{ url('/health') }}"
               class="cbed-nav-link {{ request()->is('health*') ? 'active' : '' }}">
                <i class="fa-solid fa-heart-pulse"></i>
                <span>Health &amp; Nutrition</span>
            </a>

            <a href="{{ route('ui.bcs') }}"
               class="cbed-nav-link {{ request()->is('bcs*') ? 'active' : '' }}">
                <i class="fa-solid fa-scale-balanced"></i>
                <span>Body Condition Score (BCS)</span>
            </a>

            <a href="{{ url('/milk') }}"
               class="cbed-nav-link {{ request()->is('milk*') ? 'active' : '' }}">
                <i class="fa-solid fa-droplet"></i>
                <span>Milk Production</span>
            </a>

            <a href="{{ url('/meat') }}"
               class="cbed-nav-link {{ request()->is('meat*') ? 'active' : '' }}">
                <i class="fa-solid fa-drumstick-bite"></i>
                <span>Meat Production</span>
            </a>

            <a href="{{ url('/manure') }}"
               class="cbed-nav-link {{ request()->is('manure*') ? 'active' : '' }}">
                <i class="fa-solid fa-leaf"></i>
                <span>Manure-Based Products</span>
            </a>

            <a href="{{ url('/mtd') }}"
               class="cbed-nav-link {{ request()->is('mtd*') ? 'active' : '' }}">
                <i class="fa-solid fa-flask"></i>
                <span>Milk Test Day (MTD)</span>
            </a>

            <a href="{{ url('/exits') }}"
               class="cbed-nav-link {{ request()->is('exits*') ? 'active' : '' }}">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Nature of Exit</span>
            </a>

            <a href="{{ url('/admin/semen-bulls') }}"
                class="cbed-nav-link {{ request()->is('admin/semen-bulls*') ? 'active' : '' }}">
                <i class="fa-solid fa-vials"></i>
                <span>Semen Bull</span>
            </a>

            @endif
            <p class="cbed-section-label mt-5">System</p>

            <a href="{{ route('ui.monitoring') }}"
               class="cbed-nav-link {{ request()->is('monitoring*') ? 'active' : '' }}">
                <i class="fa-solid fa-notes-medical"></i>
                <span>Monitoring</span>
            </a>
            <a href="{{ route('ui.activity-logs') }}" class="cbed-nav-link {{ request()->routeIs('ui.activity-logs', 'ui.activity-logs.*') ? 'active' : '' }}">
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                <span>Activity Logs</span>
            </a>

            @if(auth()->user()->isAdmin())
            <a href="{{ route('imports.cooperative-workbook.create') }}"
               class="cbed-nav-link {{ request()->is('imports/cooperative-workbook*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-import"></i>
                <span>Import Cooperative Excel</span>
            </a>
            <p class="cbed-section-label mt-5">Account</p>
            <a href="{{ route('signup') }}" class="cbed-nav-link {{ request()->routeIs('signup') ? 'active' : '' }}">
                <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                <span>Accounts</span>
            </a>
            @endif
        </nav>

        {{-- SIDEBAR FOOTER --}}
        <div class="shrink-0 border-t border-white/10 p-3">
            <div class="flex items-center gap-2 rounded-xl bg-white/5 p-2">
                <div class="cbed-account-icon flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                    :class="{ 'is-online': connected }" :title="connected ? 'Online' : 'Offline'">
                    <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                    <span class="sr-only" role="status" x-text="connected ? 'Online' : 'Offline'"></span>
                </div>

                <div class="min-w-0 flex-1">
                    @php
                        $account = auth()->user();
                        $accountNameParts = array_filter([$account->first_name, $account->last_name], fn ($part) => filled($part));
                        if (!$accountNameParts) {
                            $accountNameParts = preg_split('/\s+(?=\S+$)/u', trim($account->name), 2);
                        }
                    @endphp
                    <p class="whitespace-normal break-words text-xs font-semibold leading-snug">
                        @foreach ($accountNameParts as $part)
                            <span class="{{ $loop->first ? 'block truncate' : 'block' }}" title="{{ $part }}">{{ $part }}</span>
                        @endforeach
                    </p>
                    <p class="mt-1 text-[.68rem] text-slate-400">{{ \App\Models\User::ROLES[auth()->user()->role] }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout', absolute: false) }}" class="mt-2">
                @csrf
                <button type="submit" class="cbed-nav-link w-full text-left">
                    <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                    <span>Sign out</span>
                </button>
            </form>

            <p class="mt-3 text-center text-[.65rem] text-slate-500">
                CBED Data System &copy; {{ date('Y') }}
            </p>
        </div>
    </aside>

    {{-- MAIN APPLICATION --}}
    <div class="cbed-main cbed-mobile-frame flex min-h-screen flex-col">
        <div class="cbed-mobile-top border-b border-slate-200 bg-white px-4 py-3 lg:hidden print:hidden">
            <button type="button" @click="sidebarOpen = true" :aria-expanded="sidebarOpen.toString()"
                    aria-controls="cbed-sidebar" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
                Menu
            </button>
        </div>

        {{-- PAGE CONTENT --}}
        <main class="min-w-0 flex-1 px-4 pb-4 sm:px-6 sm:pb-6 lg:px-8 lg:pb-8 {{ request()->routeIs('ui.mating', 'ui.calves') ? 'pt-3' : 'pt-4 sm:pt-6 lg:pt-8' }}">

            @if (session('login_welcome'))
                <div x-data="{ visible: true }" x-init="setTimeout(() => visible = false, 2000)" x-show="visible" x-transition.opacity
                    class="pointer-events-none fixed inset-0 z-[80] grid place-items-center p-4 print:hidden">
                    <div class="w-full max-w-sm rounded-2xl border border-emerald-200 bg-white px-10 py-5 text-center shadow-2xl">
                        <span class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-emerald-700" aria-hidden="true">
                            <i class="fa-solid fa-circle-check"></i>
                        </span>
                        <div role="status" class="break-words">
                            <p class="text-lg font-bold text-slate-900">Welcome</p>
                            @if (session('login_welcome.name'))
                                <p class="mt-1 text-base font-medium text-slate-700">{{ session('login_welcome.name') }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            @unless(in_array($module ?? null, ['fhpr', 'health', 'milk', 'meat', 'manure', 'mtd', 'bcs', 'exits'], true) || request()->routeIs('admin.carabao-inventory', 'admin.semen-bulls', 'dashboard', 'dashboard.page', 'ui.farmers', 'ui.cooperatives', 'ui.monitoring', 'ui.monitoring.*', 'ui.activity-logs', 'ui.activity-logs.*', 'imports.*', 'signup') || View::hasSection('inlineCalfDropNotifications'))
                @include('partials.calf-drop-notifications')
            @endunless

            @if (session('success') && !request()->routeIs('signup') && !in_array($module ?? null, ['mtd', 'exits'], true))
                <div class="mb-5 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                    <i class="fa-solid fa-circle-check text-lg"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="mb-5 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <i class="fa-solid fa-circle-exclamation text-lg"></i>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @yield('content')
        </main>

        {{-- APPLICATION FOOTER --}}
        <footer class="shrink-0 border-t border-slate-200 bg-white px-6 py-4">
            <div class="flex flex-col justify-between gap-2 text-xs text-slate-500 sm:flex-row">
                <p>CBED Data System</p>
                <p>Carabao-Based Enterprise Development</p>
            </div>
        </footer>
    </div>

    @if(auth()->user()->isAdmin())
        @include('partials.athena')
    @endif

    {{-- PAGE SCRIPTS --}}
    @include('partials.table-pagination-assets')
    <script src="{{ asset('js/auto-filters.js') }}" defer></script>
    @stack('scripts')
    @include('partials.mobile-navigation')
</body>
</html>
