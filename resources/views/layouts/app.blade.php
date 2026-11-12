
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'CBED Data System')</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        body {
            font-family: Arial, sans-serif;
        }

        .navy {
            background: #0b1f3a;
        }
    </style>
</head>

<body class="bg-slate-100 text-slate-800">

    <div class="min-h-screen lg:flex" x-data="{ cooperativeImportOpen: false }">

        <aside class="navy text-white w-full lg:w-64 p-5">

            <div class="text-xl font-bold">
                CBED Data System
            </div>

            <p class="text-xs text-slate-300 mb-7">
                Carabao-Based Enterprise Development
            </p>

            <nav class="space-y-1">
                <button type="button" @click="cooperativeImportOpen = true" class="block w-full rounded bg-white/10 p-2 text-left hover:bg-white/15">Import cooperative Excel</button>

                <a class="block p-2 rounded hover:bg-white/10"
                   href="{{ route('dashboard') }}">
                    Dashboard
                </a>

                @foreach([
                    'farmers' => 'Farmers',
                    'cooperatives' => 'Cooperatives',
                    'carabaos' => 'Carabao Inventory',
                    'mating' => 'Mating / Breeding',
                    'calves' => 'Calf Records',
                    'milk' => 'Milk / MTD',
                    'health' => 'Health & Nutrition',
                    'bcs' => 'Body Condition Score',
                    'nature-of-exit' => 'Nature of Exit',
                    'semen-bulls' => 'Semen Bulls',
                    'semen-usage' => 'Semen Used'
                ] as $k => $v)

                    <a class="block p-2 rounded hover:bg-white/10"
                       href="{{ route('modules.index', $k) }}">
                        {{ $v }}
                    </a>

                @endforeach

            </nav>

            <form method="POST" action="{{ route('logout') }}" class="mt-8">
                @csrf
                <button type="submit">Sign out</button>
            </form>

        </aside>

        <main class="flex-1 min-w-0 p-5 md:p-8">

            @if(session('success'))
                <div class="p-3 mb-4 bg-green-100 text-green-800 rounded">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="p-3 mb-4 bg-red-100 text-red-800 rounded">
                    {{ $errors->first() }}
                </div>
            @endif

            @yield('content')

        </main>

        <div x-cloak x-show="cooperativeImportOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @keydown.escape.window="cooperativeImportOpen = false" @click.self="cooperativeImportOpen = false">
            <div x-show="cooperativeImportOpen" x-transition class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="coop-import-title">
                <div class="flex items-start justify-between gap-4"><div><h2 id="coop-import-title" class="text-xl font-bold">Import cooperative workbook</h2><p class="mt-1 text-sm text-slate-600">Select the cooperative, then choose the Excel inventory file.</p></div><button type="button" @click="cooperativeImportOpen = false" class="rounded-lg px-2 py-1 text-slate-500 hover:bg-slate-100" aria-label="Close">✕</button></div>
                <form class="mt-5 space-y-4" method="POST" action="{{ route('imports.cooperative.preview') }}" enctype="multipart/form-data">
                    @csrf
                    <div><label for="modal_cooperative_id" class="mb-1 block text-sm font-semibold">Existing cooperative</label><select id="modal_cooperative_id" name="cooperative_id" required class="w-full rounded-lg border-slate-300"><option value="">Select cooperative</option>@foreach($importCooperatives as $cooperative)<option value="{{ $cooperative->id }}">{{ $cooperative->cooperative_name }} (ID {{ $cooperative->id }})</option>@endforeach</select>@if($importCooperatives->isEmpty())<p class="mt-1 text-xs text-amber-700">Add a cooperative first in the Cooperatives module.</p>@endif</div>
                    <div><label for="modal_import_file" class="mb-1 block text-sm font-semibold">Excel or CSV file</label><input id="modal_import_file" name="file" type="file" accept=".xlsx,.xls,.xlsm,.csv,.txt" required class="block w-full rounded-lg border p-2 text-sm"><p class="mt-1 text-xs text-slate-500">Excel workbooks should include the BD_ALL inventory sheet.</p></div>
                    <div class="flex justify-end gap-2"><button type="button" @click="cooperativeImportOpen = false" class="rounded-lg border px-4 py-2">Cancel</button><button @disabled($importCooperatives->isEmpty()) class="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white">Preview import</button></div>
                </form>
            </div>
        </div>
    </div>

    {{-- Athena temporarily disabled for local development --}}

</body>
</html>
