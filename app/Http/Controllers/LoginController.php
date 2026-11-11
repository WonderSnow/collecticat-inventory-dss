<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Nova\Http\Controllers\LoginController as ControllersLoginController;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class LoginController extends ControllersLoginController
{
    /**
     * Attempt to log the user into the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function attemptLogin(Request $request)
    {
        // 1. Find the user attempting to log in
        $user = User::where($this->username(), $request->input($this->username()))->first();

        // 2. If user exists and is blocked, throw validation exception immediately
        if ($user && in_array($user->status, ['ARCHIVE', 'DISACTIVATE'])) {
            throw ValidationException::withMessages([
                $this->username() => ['Your account has been blocked. Please contact the administrator.'],
            ]);
        }

        // 3. Otherwise, proceed with normal login attempt
        return parent::attemptLogin($request);
    }

    /**
     * Get the failed login response instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function sendFailedLoginResponse(Request $request)
    {
        throw ValidationException::withMessages([
            $this->username() => [trans('auth.failed')],
        ]);
    }
}