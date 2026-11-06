<?php

namespace App\Http\Controllers;

use App\Mail\Welcome;
use App\Models\User;
use App\Support\SafeMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin() { return view('auth.login'); }
    public function showRegister() { return view('auth.register'); }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required']);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'That email and password don’t match an account.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        return redirect()->intended($this->home($request->user()));
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $user = User::create($data + ['role' => 'student']);
        SafeMail::send($user->email, new Welcome($user));
        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }

    private function home(User $user): string
    {
        return match ($user->role) {
            'admin', 'super_admin' => route('admin.dashboard'),
            'instructor' => route('studio.dashboard'),
            default => route('dashboard'),
        };
    }
}
