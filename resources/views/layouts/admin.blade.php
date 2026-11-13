<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            font-size: 70%;
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
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
</head>

<body x-data="{ sidebarOpen: false }"
      @keydown.escape.window="sidebarOpen = false">

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
        <div class="flex h-[4.5rem] shrink-0 items-center gap-3 border-b border-white/10 px-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-500 text-white">
                <i class="fa-solid fa-cow text-xl"></i>
            </div>

            <div class="min-w-0">
                <h1 class="text-sm font-bold tracking-wide">CBED</h1>
                <p class="mt-0.5 text-[.68rem] text-slate-400">Data System</p>
            </div>
        </div>

        {{-- NAVIGATION --}}
        <nav class="cbed-scrollbar flex-1 space-y-1 overflow-y-auto px-3 py-4">

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
               class="cbed-nav-link {{ request()->is('admin/carabao-inventory*') ? 'active' : '' }}">
                <i class="fa-solid fa-cow"></i>
                <span>Carabao Inventory</span>
            </a>

            <a href="{{ url('/mating') }}"
               class="cbed-nav-link {{ request()->is('mating*') ? 'active' : '' }}">
                <i class="fa-solid fa-dna"></i>
                <span>Mating</span>
            </a>

            <a href="{{ url('/fhpr') }}" class="cbed-nav-link {{ request()->is('fhpr*') ? 'active' : '' }}">
                <i class="fa-solid fa-heart-pulse"></i><span>Breeding Summary (FHPR)</span>
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

            <p class="cbed-section-label mt-5">System</p>

            <a href="{{ route('ui.monitoring') }}"
               class="cbed-nav-link {{ request()->is('monitoring*') ? 'active' : '' }}">
                <i class="fa-solid fa-notes-medical"></i>
                <span>Monitoring</span>
            </a>

            <a href="{{ route('imports.cooperative-workbook.create') }}"
               class="cbed-nav-link {{ request()->is('imports/cooperative-workbook*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-import"></i>
                <span>Import Cooperative Excel</span>
            </a>
        </nav>

        {{-- SIDEBAR FOOTER --}}
        <div class="shrink-0 border-t border-white/10 p-3">
            <div class="flex items-center gap-3 rounded-xl bg-white/5 p-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-500/20 text-blue-300">
                    <i class="fa-solid fa-user-shield"></i>
                </div>

                <div class="min-w-0">
                    <p class="truncate text-xs font-semibold">Administrator</p>
                    <p class="mt-1 text-[.68rem] text-slate-400">System Admin</p>
                </div>
            </div>

            <p class="mt-3 text-center text-[.65rem] text-slate-500">
                CBED Data System &copy; {{ date('Y') }}
            </p>
        </div>
    </aside>

    {{-- MAIN APPLICATION --}}
    <div class="cbed-main flex min-h-screen flex-col">

        {{-- PAGE CONTENT --}}
        <main class="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">

            @if (session('success'))
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

    {{-- Athena temporarily disabled for local development --}}

    {{-- PAGE SCRIPTS --}}
    @stack('scripts')
</body>
</html>
