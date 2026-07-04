<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\OAuth\Verifier;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\AdminAuth\MethodSettingsService;
use Shopware\Core\Framework\AdminAuth\Mfa\MfaChallenge;
use Shopware\Core\Framework\AdminAuth\WebAuthn\WebAuthnChallengeStore;
use Shopware\Core\Framework\AdminAuth\WebAuthn\WebAuthnService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Webauthn\CredentialRecord;

/**
 * WebAuthn / passkey verifier, usable both as a primary (discoverable / usernameless) login and as a
 * second factor.
 *
 * The browser-issued assertion JSON is supplied in the request payload together with the signed
 * challenge token issued by the `/webauthn/login-options` endpoint (see
 * {@see WebAuthnChallengeStore} for why the challenge travels as a signed token). The credential is
 * matched by its raw id against the stored {@see CredentialRecord} rows; on a valid
 * assertion the signature counter is persisted back. For the second-factor leg the credential must
 * belong to the challenged user.
 *
 * @internal
 */
#[Package('framework')]
class WebAuthnVerifier implements PrimaryVerifierInterface, SecondFactorVerifierInterface
{
    final public const METHOD = 'webauthn';

    private readonly string $rpId;

    public function __construct(
        private readonly Connection $connection,
        private readonly WebAuthnService $webAuthnService,
        private readonly WebAuthnChallengeStore $challengeStore,
        private readonly MethodSettingsService $methodSettings,
        private readonly ClockInterface $clock,
        string $appUrl,
    ) {
        $this->rpId = parse_url($appUrl, \PHP_URL_HOST) ?: 'localhost';
    }

    public function supports(string $method): bool
    {
        return $method === self::METHOD;
    }

    public function verifyPrimary(array $payload): string
    {
        if (!$this->methodSettings->isPrimary(self::METHOD)) {
            throw OAuthServerException::accessDenied('Passkey login is disabled.');
        }

        return $this->verifyAssertion($payload, null);
    }

    public function verifySecondFactor(string $userId, array $payload, MfaChallenge $challenge): void
    {
        $this->verifyAssertion($payload, $userId);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return string the verified user's id (hex)
     */
    private function verifyAssertion(array $payload, ?string $expectedUserId): string
    {
        $assertion = $payload['assertion'] ?? null;
        if (!\is_string($assertion) || $assertion === '') {
            throw OAuthServerException::invalidRequest('assertion', 'Missing WebAuthn assertion.');
        }

        $challengeToken = $payload['challengeToken'] ?? null;
        $optionsJson = $this->challengeStore->consume(
            \is_string($challengeToken) ? $challengeToken : null,
            WebAuthnChallengeStore::PURPOSE_LOGIN
        );
        if ($optionsJson === null) {
            throw OAuthServerException::accessDenied('No active WebAuthn login challenge.');
        }

        $rawId = $this->extractRawId($assertion);
        $row = $this->findCredential($rawId, $expectedUserId);
        if ($row === null) {
            throw OAuthServerException::invalidCredentials();
        }

        $userId = Uuid::fromBytesToHex($row['user_id']);

        try {
            $options = $this->webAuthnService->deserializeRequestOptions($optionsJson);
            $storedRecord = $this->webAuthnService->deserializeRecord((string) $row['credential']);

            $updated = $this->webAuthnService->verifyAssertion(
                $assertion,
                $storedRecord,
                $options,
                $this->rpId,
                $userId
            );
        } catch (\Throwable $exception) {
            throw OAuthServerException::accessDenied('WebAuthn assertion failed.', null, $exception);
        }

        // Persist the updated signature counter and usage timestamp.
        $this->connection->executeStatement(
            'UPDATE admin_auth_user_method SET credential = :cred, last_used_at = :now WHERE id = :id',
            [
                'cred' => $this->webAuthnService->serializeRecord($updated),
                'now' => $this->clock->now()->format('Y-m-d H:i:s.v'),
                'id' => $row['id'],
            ]
        );

        return $userId;
    }

    /**
     * @return array{id: string, user_id: string, credential: string}|null
     */
    private function findCredential(string $rawId, ?string $expectedUserId): ?array
    {
        $sql = 'SELECT id, user_id, credential FROM admin_auth_user_method
                WHERE type = :type AND active = 1 AND JSON_UNQUOTE(JSON_EXTRACT(credential, "$.publicKeyCredentialId")) = :cid';
        $params = ['type' => self::METHOD, 'cid' => $rawId];

        if ($expectedUserId !== null) {
            $sql .= ' AND user_id = :uid';
            $params['uid'] = Uuid::fromHexToBytes($expectedUserId);
        }

        $row = $this->connection->fetchAssociative($sql, $params);

        if ($row === false) {
            return null;
        }

        return [
            'id' => (string) $row['id'],
            'user_id' => (string) $row['user_id'],
            'credential' => (string) $row['credential'],
        ];
    }

    /**
     * The stored credential id in webauthn-lib JSON is base64url; the assertion's `rawId`/`id` is the
     * same base64url string. Extract it from the assertion JSON for the DB lookup.
     */
    private function extractRawId(string $assertionJson): string
    {
        $data = json_decode($assertionJson, true);
        if (!\is_array($data)) {
            throw OAuthServerException::invalidRequest('assertion', 'Malformed WebAuthn assertion.');
        }

        $id = $data['rawId'] ?? $data['id'] ?? null;
        if (!\is_string($id) || $id === '') {
            throw OAuthServerException::invalidRequest('assertion', 'WebAuthn assertion has no credential id.');
        }

        // Normalise base64 to base64url to match the stored publicKeyCredentialId.
        return rtrim(strtr($id, '+/', '-_'), '=');
    }
}
