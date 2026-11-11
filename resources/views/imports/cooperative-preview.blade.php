@extends('layouts.app')
@section('title', 'Review cooperative import')
@section('content')
<div class="space-y-5">
    <div><a href="{{ route('imports.cooperative.create') }}" class="text-sm text-blue-700">← Choose another file</a><h1 class="mt-3 text-2xl font-bold">Review import</h1><p class="mt-1 text-slate-600">{{ $cooperative->cooperative_name }} · {{ number_format($totalRows) }} inventory rows · {{ number_format($farmerCount) }} distinct farmer names</p></div>
    <div class="rounded-2xl border bg-white p-5"><h2 class="font-semibold">Sample rows</h2><p class="mb-3 text-xs text-slate-500">Showing up to 20 rows. Farmers are matched by normalized name within the selected cooperative. Animals are matched by registry number, tag number, or bolus ID.</p><div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50"><tr><th class="p-3 text-left">Farmer</th><th class="p-3 text-left">Animal</th><th class="p-3 text-left">Registry</th><th class="p-3 text-left">Tag</th><th class="p-3 text-left">Status</th></tr></thead><tbody>@foreach($rows as $row)<tr class="border-t"><td class="p-3">{{ $row['farmer'] }}</td><td class="p-3">{{ $row['animal'] }}</td><td class="p-3">{{ $row['registry'] }}</td><td class="p-3">{{ $row['tag'] }}</td><td class="p-3">{{ $row['inventory_status'] }}</td></tr>@endforeach</tbody></table></div></div>
    <form method="POST" action="{{ route('imports.cooperative.import') }}" class="flex flex-wrap gap-3">@csrf<button class="rounded-lg bg-emerald-700 px-5 py-3 font-semibold text-white">Import {{ number_format($totalRows) }} rows</button><a href="{{ route('imports.cooperative.create') }}" class="rounded-lg border bg-white px-5 py-3 font-semibold">Cancel</a></form>
</div>
@endsection
