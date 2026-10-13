<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('services.worker.token');

        if ($token === '' || ! hash_equals($token, (string) $request->bearerToken())) {
            abort(401, 'Invalid worker token.');
        }

        return $next($request);
    }
}
