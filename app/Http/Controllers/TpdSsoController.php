<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TpdUserResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class TpdSsoController extends Controller
{
    public function login(Request $request, TpdUserResolver $resolver)
    {
        $secret = (string) config('tpd.sso_secret');
        abort_if($secret === '', 503, 'TPD SSO is not configured.');

        $rawToken = (string) $request->query('token', '');
        $sig = strtolower(trim((string) $request->query('sig', '')));

        if ($rawToken === '' || ! preg_match('/^[a-f0-9]{64}$/', $sig)) {
            abort(403, 'Invalid SSO signature.');
        }

        $expectedSig = hash_hmac('sha256', $rawToken, $secret);
        if (! hash_equals($expectedSig, $sig)) {
            abort(403, 'Invalid SSO signature.');
        }

        $payload = $this->decodeToken($rawToken);
        if (! is_array($payload)) {
            abort(403, 'Malformed SSO payload.');
        }

        $tpdUserId = isset($payload['tpd_user_id']) ? (int) $payload['tpd_user_id'] : 0;
        $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
        $name = isset($payload['name']) ? trim((string) $payload['name']) : '';
        $role = isset($payload['role']) ? (string) $payload['role'] : null;
        $iat = isset($payload['iat']) ? (int) $payload['iat'] : 0;
        $exp = isset($payload['exp']) ? (int) $payload['exp'] : 0;
        $nonce = isset($payload['nonce']) ? trim((string) $payload['nonce']) : '';

        if ($tpdUserId <= 0 || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($nonce) < 16) {
            abort(403, 'Incomplete SSO payload.');
        }

        $now = time();
        $maxTtl = (int) config('tpd.sso_ttl', 120);
        if ($exp < $now || $iat > $now + 60 || ($exp - $iat) > ($maxTtl + 60)) {
            abort(403, 'Expired SSO token.');
        }

        $nonceKey = 'tpd_sso_nonce:'.hash('sha256', $nonce);
        if (! Cache::add($nonceKey, true, max(60, ($exp - $now) + 60))) {
            abort(403, 'SSO token has already been used.');
        }

        $user = $resolver->resolveOrCreate($tpdUserId, $email, $name !== '' ? $name : $email, $role);

        Auth::login($user);
        $request->session()->regenerate();

        $target = $this->safeRedirect($payload['redirect'] ?? null) ?? $this->defaultHomeFor($user);

        return redirect($target);
    }

    private function decodeToken(string $rawToken): ?array
    {
        $b64 = strtr($rawToken, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }

        $json = base64_decode($b64, true);
        if ($json === false) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function safeRedirect(mixed $candidate): ?string
    {
        if (! is_string($candidate)) {
            return null;
        }

        $path = trim($candidate);
        if (
            $path === ''
            || ! str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || str_contains($path, "\r")
            || str_contains($path, "\n")
            || str_contains($path, '\\')
        ) {
            return null;
        }

        if (parse_url($path, PHP_URL_HOST) !== null || parse_url($path, PHP_URL_SCHEME) !== null) {
            return null;
        }

        return $path;
    }

    private function defaultHomeFor(User $user): string
    {
        return match ($user->role) {
            'admin', 'super_admin' => route('admin.dashboard', [], false),
            'instructor' => route('studio.dashboard', [], false),
            default => route('dashboard', [], false),
        };
    }
}
