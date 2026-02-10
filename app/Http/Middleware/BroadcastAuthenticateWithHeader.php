<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\FrontendUser;
use Symfony\Component\HttpFoundation\Response;

class BroadcastAuthenticateWithHeader
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('key');
        if ($apiKey === null || md5('aiden@virtualdesigns.co.za3d@=kWfmMR') !== $apiKey) {
            return response()->json([
                'code' => 401,
                'message' => 'Wrong API Key',
            ], 401);
        }

        $userId = $request->header('userid')
            ?? $request->header('user_id')
            ?? $request->header('id');

        if ($userId === null || $userId === '') {
            return response()->json([
                'code' => 401,
                'message' => 'Missing user id',
            ], 401);
        }

        $user = FrontendUser::query()->find($userId);
        if ($user === null) {
            return response()->json([
                'code' => 404,
                'message' => 'User not found',
            ], 404);
        }

        Auth::guard('web')->setUser($user);

        return $next($request);
    }
}
