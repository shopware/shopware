<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Stores issued authorization codes so that each code can be redeemed only once.
 * The code payload itself is encrypted by the OAuth server; only its identifier is persisted.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Api\OAuth\AuthCodeRepositoryTest
 */
#[Package('framework')]
class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function getNewAuthCode(): AuthCodeEntityInterface
    {
        return new AuthCode();
    }

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        $userIdentifier = $authCodeEntity->getUserIdentifier();
        if ($userIdentifier === null) {
            return;
        }

        $this->connection->insert('oauth_auth_code', [
            'id' => Uuid::randomBytes(),
            'code_id' => $authCodeEntity->getIdentifier(),
            'user_id' => Uuid::fromHexToBytes($userIdentifier),
            'client_id' => $authCodeEntity->getClient()->getIdentifier(),
            'issued_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'expires_at' => $authCodeEntity->getExpiryDateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->cleanUpExpiredAuthCodes();
    }

    public function revokeAuthCode(string $codeId): void
    {
        $this->connection->delete('oauth_auth_code', ['code_id' => $codeId]);

        $this->cleanUpExpiredAuthCodes();
    }

    public function isAuthCodeRevoked(string $codeId): bool
    {
        $this->cleanUpExpiredAuthCodes();

        $found = $this->connection->fetchOne(
            'SELECT 1 FROM oauth_auth_code WHERE code_id = :codeId FOR UPDATE',
            ['codeId' => $codeId]
        );

        return $found === false;
    }

    public function revokeAuthCodesForUser(string $userId): void
    {
        $this->connection->delete('oauth_auth_code', ['user_id' => Uuid::fromHexToBytes($userId)]);
    }

    private function cleanUpExpiredAuthCodes(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM oauth_auth_code WHERE expires_at < :now',
            ['now' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT)]
        );
    }
}
