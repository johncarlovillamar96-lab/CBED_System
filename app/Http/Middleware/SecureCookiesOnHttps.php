<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecureCookiesOnHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isSecure()) {
            // Session and CSRF cookies are added after the router prepares the response.
            foreach ($response->headers->getCookies() as $cookie) {
                $cookie->setSecureDefault(true);
            }
        }

        return $response;
    }
}
