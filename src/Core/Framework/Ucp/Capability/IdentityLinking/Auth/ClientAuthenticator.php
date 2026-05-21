<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Auth;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Jwt\EcKeyGenerator;
use Shopware\Core\Framework\Ucp\Profile\PlatformProfileFetcher;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Implements the non-trivial OAuth 2.0 client-authentication methods that
 * League/OAuth2-Server v8 does not natively understand:
 *
 *   - `private_key_jwt`  (RFC 7523) — client signs a JWT with its own key
 *                                     and sends it as `client_assertion`
 *   - `tls_client_auth`  (RFC 8705) — mTLS handshake; client cert verified
 *                                     at the webserver layer, then validated
 *                                     against the registered DN
 *
 * Both methods are pre-validated **before** League runs its standard
 * `client_secret_*` checks. On success the request is rewritten to look
 * like a public-client call (so League skips secret validation); the
 * authenticated client id is set on the request attributes for downstream
 * scope/audit logging.
 *
 * @internal
 */
#[Package('framework')]
class ClientAuthenticator
{
    public const ATTR_AUTHENTICATED_CLIENT_ID = '_ucp_authenticated_client_id';
    public const ATTR_AUTH_METHOD = '_ucp_client_auth_method';

    public const ASSERTION_TYPE_JWT = 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';

    public const CLOCK_SKEW_SECONDS = 60;

    public function __construct(
        private readonly Connection $connection,
        private readonly PlatformProfileFetcher $profileFetcher,
    ) {
    }

    /**
     * Authenticate the incoming token request. Returns the resolved client_id
     * (which the controller then exposes to League as a `none` auth), or
     * null if no extended-auth method was attempted and the controller should
     * fall back to League's default secret check.
     *
     * Throws on invalid client_assertion / cert mismatch.
     */
    public function authenticate(Request $request, string $salesChannelId, string $tokenEndpoint, Context $context): ?string
    {
        $assertionType = $request->request->get('client_assertion_type');
        $assertion = $request->request->get('client_assertion');

        if ($assertionType === self::ASSERTION_TYPE_JWT && \is_string($assertion) && $assertion !== '') {
            $clientId = $this->validatePrivateKeyJwt($assertion, $salesChannelId, $tokenEndpoint);
            $request->attributes->set(self::ATTR_AUTHENTICATED_CLIENT_ID, $clientId);
            $request->attributes->set(self::ATTR_AUTH_METHOD, 'private_key_jwt');

            return $clientId;
        }

        // RFC 8705 §2.1: only trust server-injected mTLS identity. Client-
        // supplied X-SSL-* headers are spoofable unless a trusted proxy strips
        // them first, so they are intentionally ignored here.
        $clientCertDn = $request->server->get('SSL_CLIENT_S_DN')
            ?? $request->server->get('tls_client_auth_subject_dn');

        if (\is_string($clientCertDn) && $clientCertDn !== '') {
            $clientId = $request->request->get('client_id');
            if (!\is_string($clientId)) {
                throw UcpException::oauthClientAuthFailed('tls_client_auth requested but client_id missing');
            }
            $this->validateTlsClientAuth($clientId, $clientCertDn, $salesChannelId);
            $request->attributes->set(self::ATTR_AUTHENTICATED_CLIENT_ID, $clientId);
            $request->attributes->set(self::ATTR_AUTH_METHOD, 'tls_client_auth');

            return $clientId;
        }

        return null;
    }

    private function validatePrivateKeyJwt(string $assertion, string $salesChannelId, string $tokenEndpoint): string
    {
        $parts = explode('.', $assertion);
        if (\count($parts) !== 3) {
            throw UcpException::oauthClientAuthFailed('client_assertion is not a well-formed JWS');
        }
        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $header = json_decode(EcKeyGenerator::base64UrlDecode($headerB64), true);
        $claims = json_decode(EcKeyGenerator::base64UrlDecode($payloadB64), true);
        if (!\is_array($header) || !\is_array($claims)) {
            throw UcpException::oauthClientAuthFailed('client_assertion header/payload is not JSON');
        }

        $alg = $header['alg'] ?? null;
        if (!\in_array($alg, ['ES256', 'ES384'], true)) {
            throw UcpException::oauthClientAuthFailed('client_assertion algorithm must be ES256 or ES384');
        }
        $kid = $header['kid'] ?? null;
        if (!\is_string($kid) || $kid === '') {
            throw UcpException::oauthClientAuthFailed('client_assertion is missing the kid header');
        }

        $iss = $claims['iss'] ?? null;
        $sub = $claims['sub'] ?? null;
        $aud = $claims['aud'] ?? null;
        $jti = $claims['jti'] ?? null;
        $exp = $claims['exp'] ?? null;

        if (!\is_string($iss) || !\is_string($sub) || !hash_equals($iss, $sub)) {
            throw UcpException::oauthClientAuthFailed('client_assertion iss and sub must be identical and equal client_id');
        }

        $now = time();
        if (!\is_numeric($exp) || (int) $exp + self::CLOCK_SKEW_SECONDS < $now) {
            throw UcpException::oauthClientAuthFailed('client_assertion is expired or missing exp');
        }

        $audValues = \is_array($aud) ? $aud : [$aud];
        $audMatch = false;
        foreach ($audValues as $audValue) {
            if (\is_string($audValue) && hash_equals($tokenEndpoint, $audValue)) {
                $audMatch = true;
                break;
            }
        }
        if (!$audMatch) {
            throw UcpException::oauthClientAuthFailed('client_assertion aud does not include the token endpoint URL');
        }

        $this->assertJtiNotReplayed($salesChannelId, $iss, $jti, (int) $exp);

        // Resolve the client's JWKS: prefer pinned JWKS on the client row;
        // otherwise dereference the client's published platform profile.
        $row = $this->loadClient($iss, $salesChannelId);
        if ($row === null) {
            throw UcpException::oauthClientNotFound($iss);
        }

        $jwks = $this->resolveJwks($row);
        if ($jwks === []) {
            throw UcpException::oauthClientAuthFailed('Client did not publish a JWKS — cannot verify private_key_jwt');
        }

        $publicKeyPem = $this->jwkMatchingKid($jwks, $kid);
        if ($publicKeyPem === null) {
            throw UcpException::oauthClientAuthFailed('No JWKS entry matches kid "' . $kid . '"');
        }

        $signingInput = $headerB64 . '.' . $payloadB64;
        $sig = EcKeyGenerator::base64UrlDecode($signatureB64);
        $der = EcKeyGenerator::jwsSignatureToDer($sig, $alg);
        $hashAlgo = $alg === 'ES384' ? \OPENSSL_ALGO_SHA384 : \OPENSSL_ALGO_SHA256;

        if (openssl_verify($signingInput, $der, $publicKeyPem, $hashAlgo) !== 1) {
            throw UcpException::oauthClientAuthFailed('client_assertion signature failed verification');
        }

        return $iss;
    }

    private function validateTlsClientAuth(string $clientId, string $presentedDn, string $salesChannelId): void
    {
        $row = $this->loadClient($clientId, $salesChannelId);
        if ($row === null) {
            throw UcpException::oauthClientNotFound($clientId);
        }

        $registeredDn = $row['tls_client_auth_subject_dn'] ?? null;
        if (!\is_string($registeredDn) || $registeredDn === '') {
            throw UcpException::oauthClientAuthFailed('Client "' . $clientId . '" is not configured for tls_client_auth');
        }

        if (!hash_equals($this->normaliseDn($registeredDn), $this->normaliseDn($presentedDn))) {
            throw UcpException::oauthClientAuthFailed('Presented client certificate DN does not match the registered DN');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadClient(string $clientId, string $salesChannelId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ucp_oauth_client WHERE sales_channel_id = ? AND client_id = ? LIMIT 1',
            [Uuid::fromHexToBytes($salesChannelId), $clientId]
        );

        return \is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<array<string, mixed>>
     */
    private function resolveJwks(array $row): array
    {
        $pinned = $row['jwks_json'] ?? null;
        if (\is_string($pinned) && $pinned !== '') {
            $decoded = json_decode($pinned, true);
            if (\is_array($decoded)) {
                $keys = $decoded['keys'] ?? $decoded;

                return $this->coerceJwkList($keys);
            }
        }

        // Dereference platform profile and pick its `signing_keys`.
        $profileUri = $row['platform_profile_uri'] ?? null;
        if (!\is_string($profileUri) || $profileUri === '') {
            return [];
        }

        try {
            $profile = $this->profileFetcher->fetch($profileUri, Context::createDefaultContext());
        } catch (\Throwable) {
            return [];
        }

        return $this->coerceJwkList($profile['signing_keys'] ?? []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function coerceJwkList(mixed $candidate): array
    {
        if (!\is_array($candidate)) {
            return [];
        }

        $out = [];
        foreach ($candidate as $entry) {
            if (\is_array($entry)) {
                $out[] = $entry;
            }
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $jwks
     */
    private function jwkMatchingKid(array $jwks, string $kid): ?string
    {
        foreach ($jwks as $jwk) {
            if (\is_array($jwk) && ($jwk['kid'] ?? null) === $kid) {
                return EcKeyGenerator::jwkToPem($jwk);
            }
        }

        return null;
    }

    private function assertJtiNotReplayed(string $salesChannelId, string $iss, mixed $jti, int $exp): void
    {
        if (!\is_string($jti) || $jti === '') {
            // RFC 7523 says `jti` is OPTIONAL, but without it we cannot
            // protect against replay. UCP identity-linking.md MUST replay-
            // protect client assertions, so we require `jti` strictly.
            throw UcpException::oauthClientAuthFailed('client_assertion is missing the required `jti` claim (replay protection)');
        }

        $exists = $this->connection->fetchOne(
            'SELECT id FROM ucp_oauth_client_assertion WHERE sales_channel_id = ? AND iss = ? AND jti = ? LIMIT 1',
            [Uuid::fromHexToBytes($salesChannelId), $iss, $jti]
        );
        if ($exists !== false) {
            throw UcpException::oauthClientAuthFailed('client_assertion jti has already been used — replay rejected');
        }

        $this->connection->insert('ucp_oauth_client_assertion', [
            'id' => Uuid::randomBytes(),
            'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
            'iss' => $iss,
            'jti' => $jti,
            'expires_at' => (new \DateTimeImmutable('@' . $exp))->format('Y-m-d H:i:s.v'),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);
    }

    /**
     * Normalise an X.509 DN string into a canonical, comparable form.
     * Order-, case-, and whitespace-insensitive across the RDN components.
     */
    private function normaliseDn(string $dn): string
    {
        $parts = preg_split('/[,\/]+/', $dn) ?: [];
        $rdns = [];
        foreach ($parts as $part) {
            $trim = trim($part);
            if ($trim === '') {
                continue;
            }
            if (str_contains($trim, '=')) {
                [$k, $v] = array_map('trim', explode('=', $trim, 2));
                $rdns[] = strtoupper($k) . '=' . $v;
            } else {
                $rdns[] = $trim;
            }
        }
        sort($rdns);

        return implode(',', $rdns);
    }
}
