<?php

use App\Http\Middleware\FrameDevServerResponse;
use App\Http\Middleware\SecureCookiesOnHttps;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(FrameDevServerResponse::class);
        // VS Code forwards the tunnel connection to the local listener.
        $middleware->trustProxies(
            at: ['127.0.0.1', '::1'],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
        $middleware->web(prepend: [SecureCookiesOnHttps::class]);
        $middleware->alias(['workspace' => \App\Http\Middleware\AuthorizeWorkspace::class]);
        $middleware->web(append: [\App\Http\Middleware\RecordActivity::class]);
        $middleware->redirectUsersTo(fn (\Illuminate\Http\Request $request) => route(
            $request->user()->role === \App\Models\User::TECHNICIAN ? 'ui.monitoring' : 'dashboard'
        ));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
