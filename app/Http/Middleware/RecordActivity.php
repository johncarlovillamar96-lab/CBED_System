<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RecordActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $name = $route->getName();
        $actor = $request->user();
        $response = $next($request);

        if ($name === 'login.post') {
            $actor = $request->user();
        }

        if (! $actor || $request->isMethod('HEAD')) {
            return $response;
        }

        $action = match (true) {
            $name === 'login.post' => 'Sign in',
            $name === 'logout' => 'Sign out',
            $response->headers->has('Content-Disposition') => 'Download',
            $request->isMethod('GET') => 'View',
            $request->isMethod('DELETE') => 'Delete',
            $request->isMethod('PUT'), $request->isMethod('PATCH') => 'Update',
            $request->isMethod('POST') && is_string($name) && str_ends_with($name, '.store') => 'Create',
            default => 'Submit',
        };

        $target = collect($route->originalParameters())
            ->filter(fn ($value) => is_int($value) || (is_string($value) && ctype_digit($value)))
            ->map(fn ($value, $key) => "$key $value")
            ->implode(', ');

        try {
            DB::table('activity_logs')->insert([
                'actor_id' => $actor->getKey(),
                'actor_name' => $actor->name,
                'actor_role' => $actor->role,
                'action' => $action,
                'route' => $name ?: '/'.ltrim($route->uri(), '/'),
                'target' => $target ?: null,
                'status' => $response->getStatusCode(),
                'validation_failed' => in_array('errors', $request->session()->get('_flash.new', []), true),
                'occurred_at' => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $error) {
            report($error);
        }

        return $response;
    }
}
