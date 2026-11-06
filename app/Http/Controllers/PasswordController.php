<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * "Forgot password?" and choosing a password from an emailed link.
 * The same reset page serves invite links from Studio enrollment (?invite=1), which last longer.
 */
class PasswordController extends Controller
{
    public function showForgot() { return view('auth.forgot-password'); }

    public function sendLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        try {
            Password::broker('users')->sendResetLink($request->only('email'));
        } catch (\Throwable $e) {
            report($e);
        }
        // Same answer whether or not the account exists, so the form can't be used to find accounts.
        return back()->with('status', 'If an account uses that email, we’ve sent a link to reset the password. Check your inbox and spam folder.');
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email'),
            'invite' => $request->boolean('invite'),
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $broker = $request->boolean('invite') ? 'invites' : 'users';
        $user = null;
        $status = Password::broker($broker)->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $u, string $password) use (&$user) {
                $u->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($u));
                $user = $u;
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => match ($status) {
                Password::INVALID_TOKEN => 'This link has expired or was already used. Ask for a new one below.',
                default => 'We couldn’t find an account with that email.',
            }]);
        }

        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->route($user->canTeach() ? 'studio.dashboard' : 'dashboard')->with('status', 'Your password is set. You’re logged in.');
    }
}
