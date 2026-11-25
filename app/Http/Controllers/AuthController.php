<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    /** The login boxes live in the top bar of the landing page, so /login just sends people there. */
    public function show(): RedirectResponse
    {
        return redirect()->route('files.landing')->with('status', 'Log in with your username and password to continue.');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        $username = Str::lower(trim($credentials['username']));
        $throttleKey = 'login:'.$username.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'username' => "Too many attempts. Try again in {$seconds} seconds.",
            ]);
        }

        // Deleted accounts are soft-deleted, so the user provider never finds them and they cannot log in.
        if (! Auth::attempt(['username' => $username, 'password' => $credentials['password']])) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages(['username' => 'Wrong username or password.']);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->intended(route('files.landing'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('files.landing');
    }
}
