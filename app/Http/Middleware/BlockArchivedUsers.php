<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BlockArchivedUsers
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && in_array(Auth::user()->status, ['ARCHIVE', 'DISACTIVATE'])) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Flash session error so it persists across redirects
            session()->flash('error', 'Your account has been blocked. Please contact the administrator.');

            return redirect('/nova/login')->withErrors([
                'email' => 'Your account has been blocked. Please contact the administrator.',
            ]);
        }

        return $next($request);
    }
}