@extends('layouts.app')
@section('title', 'Import cooperative inventory')
@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div><a href="{{ route('admin.carabao-inventory') }}" class="text-sm text-blue-700">← Carabao Inventory</a><h1 class="mt-3 text-2xl font-bold">Import farmers and carabaos</h1><p class="mt-1 text-sm text-slate-600">Choose the cooperative first. The importer reads the BD_ALL inventory sheet, matches farmer names within that cooperative, and updates animals by registry, tag, or bolus ID.</p></div>
    <form method="POST" action="{{ route('imports.cooperative.preview') }}" enctype="multipart/form-data" class="rounded-2xl border bg-white p-6 space-y-5">
        @csrf
        <div><label for="cooperative_id" class="mb-1 block text-sm font-semibold">Cooperative</label><select id="cooperative_id" name="cooperative_id" required class="w-full rounded-lg border-slate-300"><option value="">Select an existing cooperative</option>@foreach($cooperatives as $coop)<option value="{{ $coop->id }}">{{ $coop->cooperative_name }} (ID {{ $coop->id }})</option>@endforeach</select>@if($cooperatives->isEmpty())<p class="mt-2 text-sm text-amber-700">Add a cooperative in the Cooperatives module before importing.</p>@endif</div>
        <div><label for="file" class="mb-1 block text-sm font-semibold">Excel or CSV file</label><input id="file" name="file" type="file" accept=".xlsx,.xls,.xlsm,.csv,.txt" required class="block w-full rounded-lg border p-2 text-sm"><p class="mt-1 text-xs text-slate-500">Excel files (.xlsx, .xls, .xlsm) should contain a BD_ALL sheet with the original inventory headings. Maximum file size: 20 MB.</p></div>
        <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-900">The preview will show a sample before any records are changed. Existing farmer sequences stay in place; new farmers get the next cooperative sequence, for example <b>1-1</b>, <b>1-2</b>.</div>
        <button class="rounded-lg bg-slate-900 px-4 py-2.5 font-semibold text-white disabled:opacity-50" @disabled($cooperatives->isEmpty())>Preview import</button>
    </form>
</div>
@endsection
