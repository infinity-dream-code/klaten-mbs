<?php

namespace App\Support;

use App\Models\CyberKey;
use Illuminate\Http\Request;
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

    public static function queue(?CyberKey $user): void
    {
        if (!$user) {
            return;
        }

        $payload = json_encode([
            'id' => $user->getAuthIdentifier(),
            'u' => (string) $user->users,
        ], JSON_UNESCAPED_UNICODE);

        Cookie::queue(cookie(
            self::COOKIE,
            $payload,
            self::lifetimeMinutes(),
            config('session.path', '/'),
            config('session.domain'),
            (bool) config('session.secure', false),
            true,
            false,
            config('session.same_site', 'lax')
        ));
    }

    public static function forget(): void
    {
        Cookie::queue(Cookie::forget(
            self::COOKIE,
            config('session.path', '/'),
            config('session.domain')
        ));
    }

    public static function userFromRequest(Request $request): ?CyberKey
    {
        $raw = $request->cookie(self::COOKIE);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            $data = json_decode($raw, true);
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
}
