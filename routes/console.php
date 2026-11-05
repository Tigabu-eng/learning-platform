<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Make an existing account a super admin. Run on the server to set up the first one,
// or to recover if no super admin can sign in.
Artisan::command('user:super-admin {email}', function (string $email) {
    $user = \App\Models\User::where('email', $email)->first();
    if (! $user) {
        $this->error("No account uses $email. They need to register first.");
        return 1;
    }
    $user->update(['role' => 'super_admin']);
    $this->info("{$user->name} ($email) is now a super admin.");
    return 0;
})->purpose('Make an existing account a super admin');
