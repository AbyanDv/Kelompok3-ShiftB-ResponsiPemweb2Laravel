<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isVerified()) {
            return response()->json([
                'success' => false,
                'message' => 'Akun belum terverifikasi admin.',
            ], 403);
        }

        return $next($request);
    }
}
