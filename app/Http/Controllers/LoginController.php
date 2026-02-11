<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class LoginController extends Controller
{
    public function show()
    {
        return view('login');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $guard = 'web';
        $table = 'users';
        $loginValue = $validated['login'];
        $password = $validated['password'];
        $remember = (bool) ($validated['remember'] ?? false);

        $fields = [];
        if (Schema::hasColumn($table, 'email')) {
            $fields[] = 'email';
        }
        if (Schema::hasColumn($table, 'username')) {
            $fields[] = 'username';
        }
        if (Schema::hasColumn($table, 'login')) {
            $fields[] = 'login';
        }

        if (empty($fields)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'No login fields are available in the users table.',
                ], 422);
            }

            return back()->withErrors([
                'login' => 'No login fields are available in the users table.',
            ])->withInput();
        }

        $orderedFields = $fields;
        if (str_contains($loginValue, '@') && in_array('email', $fields, true)) {
            $orderedFields = array_values(array_unique(array_merge(['email'], $fields)));
        }

        foreach ($orderedFields as $field) {
            $credentials = [
                $field => $loginValue,
                'password' => $password,
            ];

            if (Auth::guard($guard)->attempt($credentials, $remember)) {
                $request->session()->regenerate();

                if ($request->expectsJson()) {
                    return $this->me($request);
                }

                return redirect()->intended('/me');
            }
        }
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'These credentials do not match our records.',
            ], 422);
        }

        return back()->withErrors([
            'login' => 'These credentials do not match our records.',
        ])->withInput();
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return redirect('/login');
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id' => $user?->getAuthIdentifier(),
            'guard' => Auth::getDefaultDriver(),
            'name' => $user?->name,
            'email' => $user?->email,
        ]);
    }
}
