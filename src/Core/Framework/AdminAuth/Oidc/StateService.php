<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Oidc;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\AdminAuth\AdminAuthException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\String\ByteString;

/**
 * Per-login CSRF state and OIDC nonce for the authorization code flow.
 *
 * The Admin API is stateless, so state and nonce travel in an HMAC-signed, short-lived cookie
 * scoped to the admin-auth action routes instead of a server-side session. The authorize redirect
 * sets the cookie bound to the chosen provider; the callback verifies the returned `state` against
 * it and hands the nonce to the id_token validator. SameSite=Lax keeps the cookie available on the
 * top-level redirect back from the identity provider.
 *
 * @internal
 */
#[Package('framework')]
class StateService
{
    final public const COOKIE_NAME = 'admin-auth-state';

    private const COOKIE_PATH = '/api/_action/admin-auth';
    private const TTL = 600;
    private const RANDOM_LENGTH = 64;

    public function __construct(
        private readonly string $appSecret,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return array{state: string, nonce: string, cookie: Cookie}
     */
    public function create(Request $request, string $providerId): array
    {
        $state = ByteString::fromRandom(self::RANDOM_LENGTH)->toString();
        $nonce = ByteString::fromRandom(self::RANDOM_LENGTH)->toString();
        $expiresAt = $this->clock->now()->getTimestamp() + self::TTL;

        $payload = $this->encode([
            'state' => $state,
            'nonce' => $nonce,
            'providerId' => $providerId,
            'exp' => $expiresAt,
        ]);

        $cookie = Cookie::create(self::COOKIE_NAME, $payload . '.' . $this->sign($payload))
            ->withExpires($expiresAt)
            ->withPath(self::COOKIE_PATH)
            ->withSecure($request->isSecure())
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);

        return ['state' => $state, 'nonce' => $nonce, 'cookie' => $cookie];
    }

    /**
     * Validate the callback's `state` against the cookie and return the stored nonce + providerId.
     *
     * @return array{nonce: string, providerId: string}
     */
    public function consume(Request $request, ?string $state): array
    {
        $stored = $this->decode($request->cookies->get(self::COOKIE_NAME));

        if (
            $stored === null
            || !\is_string($state)
            || $state === ''
            || (int) $stored['exp'] < $this->clock->now()->getTimestamp()
            || !hash_equals($stored['state'], $state)
        ) {
            throw AdminAuthException::invalidOauthState();
        }

        return ['nonce' => $stored['nonce'], 'providerId' => $stored['providerId']];
    }

    /**
     * An expired cookie clearing the state; set it on every callback response so the state is
     * single-use.
     */
    public function clearCookie(): Cookie
    {
        return Cookie::create(self::COOKIE_NAME, null)->withPath(self::COOKIE_PATH);
    }

    /**
     * @param array<string, string|int> $data
     */
    private function encode(array $data): string
    {
        // Symfony url-encodes cookie values, so plain base64 is safe here.
        return base64_encode(json_encode($data, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{state: string, nonce: string, providerId: string, exp: int}|null
     */
    private function decode(?string $cookieValue): ?array
    {
        if (!\is_string($cookieValue) || substr_count($cookieValue, '.') !== 1) {
            return null;
        }

        [$payload, $signature] = explode('.', $cookieValue, 2);

        if (!hash_equals($this->sign($payload), $signature)) {
            return null;
        }

        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return null;
        }

        $data = json_decode($decoded, true);

        if (
            !\is_array($data)
            || !\is_string($data['state'] ?? null)
            || !\is_string($data['nonce'] ?? null)
            || !\is_string($data['providerId'] ?? null)
            || !\is_int($data['exp'] ?? null)
        ) {
            return null;
        }

        return [
            'state' => $data['state'],
            'nonce' => $data['nonce'],
            'providerId' => $data['providerId'],
            'exp' => $data['exp'],
        ];
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->appSecret);
    }
}
