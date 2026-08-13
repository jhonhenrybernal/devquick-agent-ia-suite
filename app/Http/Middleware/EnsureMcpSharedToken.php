<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMcpSharedToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $sharedToken = (string) config('mcp.shared_token', '');

        if ($sharedToken === '') {
            return response()->json([
                'error' => 'MCP shared token is not configured.',
            ], 503);
        }

        $providedToken = $this->providedToken($request);

        if ($providedToken === null || ! hash_equals($sharedToken, $providedToken)) {
            return response()->json([
                'error' => 'Unauthorized.',
            ], 401)->header('WWW-Authenticate', 'Bearer');
        }

        return $next($request);
    }

    private function providedToken(Request $request): ?string
    {
        $authorization = trim((string) $request->bearerToken());

        if ($authorization !== '') {
            return $authorization;
        }

        $headerToken = trim((string) $request->header('X-MCP-Token', ''));

        if ($headerToken !== '') {
            return $headerToken;
        }

        $queryToken = trim((string) $request->query('token', ''));

        return $queryToken !== '' ? $queryToken : null;
    }
}
