<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\WebAuthn;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\AdminAuth\Oidc\StateService;
use Shopware\Core\Framework\Log\Package;

/**
 * Transports the in-flight WebAuthn options (the server-issued challenge) between the "options"
 * request and the "verify"/login request.
 *
 * The challenge must be server-trusted — it can never be accepted from the client as-is. The
 * FroshAdminAuth plugin kept it in the PHP session, but the Admin API is stateless (there is no
 * session on `/api/oauth/token`), so — like the OIDC {@see StateService} — the options travel in an
 * HMAC-signed, short-lived token instead of server-side state. Unlike the OIDC state the token is
 * returned in the JSON response body (not a cookie) and echoed back in the request payload, because
 * the assertion is verified inside an OAuth grant whose verifiers only see the parsed request body.
 * (The `admin_auth_mfa_challenge.webauthn_challenge` column was considered as server-side storage,
 * but a challenge row only exists for the second-factor leg — a discoverable primary passkey login
 * has none.)
 *
 * The token is tamper-proof and expires quickly, but is not single-use. That is acceptable for the
 * same reason it is for the OIDC state cookie: replaying it requires a fresh, valid authenticator
 * response for the embedded challenge, which only the credential owner can produce.
 *
 * @internal
 */
#[Package('framework')]
class WebAuthnChallengeStore
{
    final public const PURPOSE_LOGIN = 'login';
    final public const PURPOSE_REGISTER = 'register';

    private const TTL = 300;

    public function __construct(
        private readonly string $appSecret,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Wrap the serialized WebAuthn options in a signed challenge token. Registration tokens are
     * additionally bound to the enrolling user.
     */
    public function issue(string $optionsJson, string $purpose, ?string $userId = null): string
    {
        $payload = base64_encode(json_encode([
            'options' => $optionsJson,
            'purpose' => $purpose,
            'userId' => $userId,
            'exp' => $this->clock->now()->getTimestamp() + self::TTL,
        ], \JSON_THROW_ON_ERROR));

        return $payload . '.' . $this->sign($payload);
    }

    /**
     * Validate a challenge token and return the embedded options JSON, or null when the token is
     * missing, tampered with, expired or bound to a different purpose/user.
     */
    public function consume(?string $token, string $purpose, ?string $userId = null): ?string
    {
        if (!\is_string($token) || substr_count($token, '.') !== 1) {
            return null;
        }

        [$payload, $signature] = explode('.', $token, 2);

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
            || !\is_string($data['options'] ?? null)
            || !\is_int($data['exp'] ?? null)
            || ($data['purpose'] ?? null) !== $purpose
            || ($data['userId'] ?? null) !== $userId
            || $data['exp'] < $this->clock->now()->getTimestamp()
        ) {
            return null;
        }

        return $data['options'];
    }

    private function sign(string $payload): string
    {
        // Domain-separated so tokens can never be confused with other kernel.secret HMAC payloads.
        return hash_hmac('sha256', 'admin-auth-webauthn:' . $payload, $this->appSecret);
    }
}
