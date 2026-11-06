<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The "Forgot password?" email, in TARC's words and theme.
        ResetPassword::toMailUsing(function ($user, string $token) {
            $url = route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()]);
            $minutes = config('auth.passwords.users.expire');
            return (new MailMessage)
                ->subject('Reset your '.config('app.name').' password')
                ->greeting('Hi '.$user->name.',')
                ->line('We got a request to reset the password for your account ('.$user->email.').')
                ->action('Choose a new password', $url)
                ->line("This link works once and expires in $minutes minutes.")
                ->line('If you didn’t ask for this, you can ignore this email. Your password won’t change.')
                ->salutation(config('app.name'));
        });
    }
}
