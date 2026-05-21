<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Entity\UcpAuthCodeEntity;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * @internal
 */
#[Package('framework')]
class UcpAuthCodeRepository implements AuthCodeRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection,
        private string $salesChannelId,
    ) {
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getNewAuthCode(): AuthCodeEntityInterface
    {
        return new UcpAuthCodeEntity();
    }

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        $scopes = array_map(
            static fn (ScopeEntityInterface $s): string => $s->getIdentifier(),
            $authCodeEntity->getScopes()
        );

        try {
            $this->connection->insert('ucp_oauth_auth_code', [
                'identifier' => $authCodeEntity->getIdentifier(),
                'sales_channel_id' => Uuid::fromHexToBytes($this->salesChannelId),
                'client_id' => $authCodeEntity->getClient()->getIdentifier(),
                'user_identifier' => (string) $authCodeEntity->getUserIdentifier(),
                'scopes' => json_encode($scopes, \JSON_THROW_ON_ERROR),
                'redirect_uri' => $authCodeEntity->getRedirectUri() ?? '',
                'revoked' => 0,
                'expires_at' => $authCodeEntity->getExpiryDateTime()->format('Y-m-d H:i:s.v'),
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw OAuthServerException::accessDenied('Authorization code already exists');
        }
    }

    public function revokeAuthCode(string $codeId): void
    {
        $this->connection->executeStatement(
            'UPDATE ucp_oauth_auth_code SET revoked = 1 WHERE identifier = ?',
            [$codeId]
        );
    }

    public function isAuthCodeRevoked(string $codeId): bool
    {
        $row = $this->connection->fetchAssociative(
            'SELECT revoked FROM ucp_oauth_auth_code WHERE identifier = ? LIMIT 1',
            [$codeId]
        );
        if (!\is_array($row)) {
            return true;
        }

        return (bool) $row['revoked'];
    }
}
