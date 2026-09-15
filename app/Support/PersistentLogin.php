<?php

namespace App\Support;

use App\Models\CyberKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Throwable;

class PersistentLogin
{
    public const COOKIE = 'mbs_klaten';

    public static function lifetimeMinutes(): int
    {
        $minutes = (int) config('session.lifetime', 5256000);

        return $minutes > 0 ? $minutes : 5256000;
    }

    public static function bind(?CyberKey $user): void
    {
        if (!$user) {
            return;
        }

        $guard = Auth::guard();
        if (method_exists($guard, 'getName') && session()->isStarted()) {
            session()->put($guard->getName(), $user->getAuthIdentifier());
        }

        $guard->setUser($user);
    }

    public static function queue(?CyberKey $user): void
    {
        if (!$user) {
            return;
        }

        Cookie::queue(cookie(
            self::COOKIE,
            self::encodePayload($user),
            self::lifetimeMinutes(),
            config('session.path', '/'),
            config('session.domain'),
            self::secure(),
            true,
            false,
            config('session.same_site', 'lax')
        ));
    }

    public static function forget(): void
    {
        Cookie::queue(cookie(
            self::COOKIE,
            '',
            -2628000,
            config('session.path', '/'),
            config('session.domain'),
            self::secure(),
            true,
            false,
            config('session.same_site', 'lax')
        ));
    }

    public static function userFromRequest(Request $request): ?CyberKey
    {
        $raw = $request->cookie(self::COOKIE);
        if (!is_string($raw) || trim($raw) === '') {
            $raw = $request->cookies->get(self::COOKIE);
        }
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            $data = self::decodePayload($raw);
            if (!is_array($data) || blank($data['id'] ?? null)) {
                return null;
            }

            $user = CyberKey::query()->where('urut', $data['id'])->first();
            if (!$user) {
                return null;
            }

            $cookieUser = trim((string) ($data['u'] ?? ''));
            if ($cookieUser !== '' && strcasecmp($cookieUser, (string) $user->users) !== 0) {
                return null;
            }

            return $user;
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function encodePayload(CyberKey $user): string
    {
        $encoded = rtrim(strtr(base64_encode(json_encode([
            'id' => $user->getAuthIdentifier(),
            'u' => (string) $user->users,
        ], JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');

        return $encoded . '.' . self::signature($encoded);
    }

    private static function decodePayload(string $raw): ?array
    {
        $raw = trim($raw);

        if (str_contains($raw, '.')) {
            [$encoded, $signature] = explode('.', $raw, 2);
            if ($encoded !== '' && $signature !== '' && hash_equals(self::signature($encoded), $signature)) {
                $json = base64_decode(strtr($encoded, '-_', '+/'), true);
                $data = is_string($json) ? json_decode($json, true) : null;

                return is_array($data) ? $data : null;
            }
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    private static function signature(string $encoded): string
    {
        return hash_hmac('sha256', $encoded, (string) config('app.key'));
    }

    private static function secure(): bool
    {
        $configured = config('session.secure');
        if ($configured !== null && $configured !== '') {
            return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) request()?->isSecure();
    }
}
