<?php

namespace app\Http\Controllers;

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
     * Send a verification code to the given user.
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

        $code = rand(100000, 999999);

        VerificationCode::create([
            'user_id' => $user->id,
            'code' => $code,
            'expires_at' => now()->addMinutes(15),
        ]);

        session(['email_for_password_reset' => $user->email]);

        return redirect()->route('password.code.verify.form')
            ->with('status', __('We have sent a verification code to your email address.'));
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
     * Verify the code and update the user's password.
     */
    public function verifyCodeAndResetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|digits:6',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::where('email', $request->email)->first();

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