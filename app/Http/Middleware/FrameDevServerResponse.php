<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FrameDevServerResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $content = $response->getContent();

        // Dev Tunnels can leave a close-delimited PHP development-server response pending.
        if (PHP_SAPI === 'cli-server' && $request->isSecure() && ! $request->isMethod('HEAD')
            && ! $response->isInformational() && ! $response->isEmpty() && $response->getStatusCode() !== 205
            && is_string($content) && ! $response->headers->has('Content-Length')
            && ! $response->headers->has('Transfer-Encoding') && ! $response->headers->has('Content-Encoding')
            && ! filter_var(ini_get('zlib.output_compression'), FILTER_VALIDATE_BOOLEAN)) {
            $response->headers->set('Content-Length', (string) strlen($content));
        }

        return $response;
    }
}
