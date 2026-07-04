<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\OAuth\Verifier;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Exception\OAuthServerException;
use OTPHP\TOTP;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\AdminAuth\Mfa\MfaChallenge;
use Shopware\Core\Framework\AdminAuth\SecretEncryptor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Verifies a TOTP (authenticator app) second factor.
 *
 * Checks the submitted 6-digit code against each of the user's active TOTP enrollments, using the
 * decrypted secret. A small verification window tolerates clock drift.
 *
 * @internal
 */
#[Package('framework')]
class TotpVerifier implements SecondFactorVerifierInterface
{
    private const VERIFY_WINDOW = 1;

    public function __construct(
        private readonly Connection $connection,
        private readonly SecretEncryptor $secretEncryptor,
        private readonly ClockInterface $clock,
    ) {
    }

    public function supports(string $method): bool
    {
        return $method === 'totp';
    }

    public function verifySecondFactor(string $userId, array $payload, MfaChallenge $challenge): void
    {
        $code = $payload['code'] ?? null;
        if (!\is_string($code) || $code === '' || !preg_match('/^\d{6}$/', $code)) {
            throw OAuthServerException::invalidRequest('code', 'A 6-digit TOTP code is required.');
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, secret FROM admin_auth_user_method WHERE user_id = :uid AND type = :type AND active = 1',
            ['uid' => Uuid::fromHexToBytes($userId), 'type' => 'totp']
        );

        foreach ($rows as $row) {
            if (!\is_string($row['secret']) || $row['secret'] === '') {
                continue;
            }

            try {
                $secret = $this->secretEncryptor->decrypt($row['secret']);
            } catch (\Throwable) {
                continue;
            }

            if ($secret === '') {
                continue;
            }

            $totp = TOTP::createFromSecret($secret);

            if ($totp->verify($code, null, self::VERIFY_WINDOW)) {
                $this->connection->executeStatement(
                    'UPDATE admin_auth_user_method SET last_used_at = :now WHERE id = :id',
                    ['now' => $this->clock->now()->format('Y-m-d H:i:s.v'), 'id' => $row['id']]
                );

                return;
            }
        }

        throw OAuthServerException::invalidCredentials();
    }
}
