<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\OAuth\Verifier;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\AdminAuth\Mfa\MfaChallenge;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Verifies a one-time recovery code as a fallback second factor.
 *
 * Codes are stored hashed (never in clear) inside the `recovery_codes` user-method row's credential
 * JSON as `{ codes: [{ hash, usedAt }] }`. A successful match marks that single code as used so it
 * cannot be replayed.
 *
 * @internal
 */
#[Package('framework')]
class RecoveryCodeVerifier implements SecondFactorVerifierInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function supports(string $method): bool
    {
        return $method === 'recovery_codes';
    }

    public function verifySecondFactor(string $userId, array $payload, MfaChallenge $challenge): void
    {
        $code = $this->normalize($payload['code'] ?? null);
        if ($code === null) {
            throw OAuthServerException::invalidRequest('code', 'A recovery code is required.');
        }

        $row = $this->connection->fetchAssociative(
            'SELECT id, credential FROM admin_auth_user_method WHERE user_id = :uid AND type = :type AND active = 1',
            ['uid' => Uuid::fromHexToBytes($userId), 'type' => 'recovery_codes']
        );

        if ($row === false) {
            throw OAuthServerException::invalidCredentials();
        }

        $credential = json_decode((string) $row['credential'], true);
        $codes = \is_array($credential['codes'] ?? null) ? $credential['codes'] : [];

        foreach ($codes as $index => $entry) {
            if (!\is_array($entry) || ($entry['usedAt'] ?? null) !== null || !\is_string($entry['hash'] ?? null)) {
                continue;
            }

            if (password_verify($code, $entry['hash'])) {
                $now = $this->clock->now();
                $codes[$index]['usedAt'] = $now->format(\DateTimeInterface::ATOM);
                $this->connection->executeStatement(
                    'UPDATE admin_auth_user_method SET credential = :cred, last_used_at = :now WHERE id = :id',
                    [
                        'cred' => json_encode(['codes' => array_values($codes)], \JSON_THROW_ON_ERROR),
                        'now' => $now->format('Y-m-d H:i:s.v'),
                        'id' => $row['id'],
                    ]
                );

                return;
            }
        }

        throw OAuthServerException::invalidCredentials();
    }

    private function normalize(mixed $code): ?string
    {
        if (!\is_string($code)) {
            return null;
        }

        // Codes are presented grouped (e.g. "ABCD-EFGH"); compare on the bare alphanumerics.
        $normalized = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $code) ?? '');

        return $normalized === '' ? null : $normalized;
    }
}
