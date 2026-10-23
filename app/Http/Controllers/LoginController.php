<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Nova\Http\Controllers\LoginController as ControllersLoginController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

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
        // 1. Find the user by email first
        $user = User::where($this->username(), $request->input($this->username()))->first();

        // 2. If user exists, check if they are DISACTIVATE or ARCHIVE
        if ($user && in_array($user->status, ['ARCHIVE', 'DISACTIVATE'])) {
            return false; // Reject login immediately without checking password or logging them in
        }

        // 3. Otherwise, proceed with normal Nova/Laravel login attempt
        return parent::attemptLogin($request);
    }
}