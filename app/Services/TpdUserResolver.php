<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TpdUserResolver
{
    private const ROLE_RANK = [
        'student' => 1,
        'instructor' => 2,
        'admin' => 3,
        'super_admin' => 4,
    ];

    public function mapRole(?string $tpdRole): string
    {
        $key = strtolower(trim((string) $tpdRole));
        $mapped = config('tpd.role_map.'.$key, 'student');

        return array_key_exists($mapped, User::ROLES) ? $mapped : 'student';
    }

    /**
     * Find an existing LMS user by WordPress user ID or email, or provision one.
     * Never silently downgrades a privileged LMS account when opening student links.
     */
    public function resolveOrCreate(int $tpdUserId, string $email, string $name, ?string $tpdRole = null): User
    {
        $normalizedEmail = strtolower(trim($email));
        $cleanName = trim($name) !== '' ? trim($name) : $normalizedEmail;
        $targetRole = $this->mapRole($tpdRole);

        $user = User::where('tpd_user_id', $tpdUserId)->first()
            ?? User::whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();

        if (! $user) {
            $user = new User([
                'name' => $cleanName,
                'email' => $normalizedEmail,
                'password' => Hash::make(Str::random(40)),
                'role' => $targetRole,
            ]);
            $user->tpd_user_id = $tpdUserId;
            $user->email_verified_at = now();
            $user->save();

            return $user;
        }

        $dirty = false;

        if (! $user->tpd_user_id) {
            $user->tpd_user_id = $tpdUserId;
            $dirty = true;
        }

        if ($cleanName !== '' && $user->name !== $cleanName) {
            $user->name = $cleanName;
            $dirty = true;
        }

        // Sync email change from TPD only if no other LMS account owns that address.
        if (
            $user->tpd_user_id === $tpdUserId
            && strtolower($user->email) !== $normalizedEmail
            && ! User::whereRaw('LOWER(email) = ?', [$normalizedEmail])->where('id', '!=', $user->id)->exists()
        ) {
            $user->email = $normalizedEmail;
            $dirty = true;
        }

        if ((self::ROLE_RANK[$targetRole] ?? 1) > (self::ROLE_RANK[$user->role] ?? 1)) {
            $user->role = $targetRole;
            $dirty = true;
        }

        if (! $user->email_verified_at) {
            $user->email_verified_at = now();
            $dirty = true;
        }

        if ($dirty) {
            $user->save();
        }

        return $user;
    }

    public function findOnly(?int $tpdUserId, ?string $email): ?User
    {
        if ($tpdUserId) {
            $byTpd = User::where('tpd_user_id', $tpdUserId)->first();
            if ($byTpd) {
                return $byTpd;
            }
        }

        if ($email) {
            return User::whereRaw('LOWER(email) = ?', [strtolower(trim($email))])->first();
        }

        return null;
    }
}
