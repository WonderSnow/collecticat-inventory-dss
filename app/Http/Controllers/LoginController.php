<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Nova\Http\Controllers\LoginController as ControllersLoginController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends ControllersLoginController
{
    /**
     * Attempt to log the user into the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function attemptLogin(Request $request)
    {
        // First, let Nova/Laravel attempt the standard login
        $loggedos = parent::attemptLogin($request);

        if ($loggedos) {
            $user = Auth::user();

            // If the user is archived, log them out immediately and fail login
            if ($user && $user->status === 'ARCHIVE') {
                Auth::logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw ValidationException::withMessages([
                    username() => [trans('auth.failed')],
                ]);
            }
        }

        return $loggedos;
    }
}