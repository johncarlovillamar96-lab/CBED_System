<?php

namespace App\Http\Middleware;

use App\Support\TransactionActivity;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RecordActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();
        if (! $actor || $request->isMethodSafe()) {
            return $next($request);
        }

        $route = $request->route()?->getName() ?? $request->route()?->uri() ?? 'application';
        $key = (string) Str::uuid();
        $connection = DB::connection();
        TransactionActivity::setCaptureKey($connection, $key);

        try {
            return $next($request);
        } finally {
            TransactionActivity::setCaptureKey($connection, null);
            $importTotals = $request->attributes->get('activity_import_totals', []);
            $publish = fn () => app(TransactionActivity::class)->publish($connection, $key, $actor, $route, importTotals: $importTotals);
            if ($connection->transactionLevel() > 0) {
                $connection->afterCommit($publish);
            } else {
                $publish();
            }
        }
    }
}
