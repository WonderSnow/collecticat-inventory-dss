<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class ForgotPasswordController extends Controller
{
    use ValidatesRequests;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('nova.guest:'.config('nova.guard'));
    }

    /**
     * Display the form to request a password reset email.
     *
     * @return \Illuminate\Http\Response
     */
    public function showLinkRequestForm()
    {
        return view('nova::auth.passwords.email');
    }

    /**
     * Proceed to verification form after confirming email exists.
     * (No automatic code is generated/sent here anymore).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return back()->withErrors(['email' => __('We can\'t find a user with that email address.')]);
        }

        // Store email in session for the next step
        session(['email_for_password_reset' => $user->email]);

        // Redirect to the code entry form without generating a code or setting a "sent" status message
        return redirect()->route('password.code.verify.form');
    }

    /**
     * Show the form to enter the verification code and new password.
     */
    public function showCodeVerificationForm(Request $request)
    {
        $email = session('email_for_password_reset');
        return view('auth.verify-password-code', compact('email'));
    }

    /**
     * Verify the code against the admin-created code and update the password.
     */
    public function verifyCodeAndResetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|digits:6|numeric',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::where('email', $request->email)->first();

        // Checks against the code manually created by the admin in the database
        $verificationCode = VerificationCode::where('user_id', $user->id)
            ->where('code', $request->code)
            ->where('expires_at', '>', now())
            ->first();

        if (!$verificationCode) {
            return back()->withErrors(['code' => 'Invalid or expired verification code.']);
        }

        // Update password
        $user->password = Hash::make($request->password);
        $user->save();

        // Clear used verification codes and session
        VerificationCode::where('user_id', $user->id)->delete();
        session()->forget('email_for_password_reset');

        // Log the user in directly
        Auth::login($user);

        return redirect()->to(config('nova.path'))
            ->with('status', 'Your password has been successfully reset!');
    }
}