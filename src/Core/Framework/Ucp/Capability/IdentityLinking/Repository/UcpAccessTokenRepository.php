<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Entity\UcpAccessTokenEntity;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * @internal
 */
#[Package('framework')]
class UcpAccessTokenRepository implements AccessTokenRepositoryInterface
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

    public function getNewToken(
        ClientEntityInterface $clientEntity,
        array $scopes,
        string|int|null $userIdentifier = null
    ): AccessTokenEntityInterface {
        $token = new UcpAccessTokenEntity();
        $token->setClient($clientEntity);
        foreach ($scopes as $scope) {
            $token->addScope($scope);
        }
        if ($userIdentifier !== null) {
            $identifier = (string) $userIdentifier;
            if ($identifier !== '') {
                $token->setUserIdentifier($identifier);
            }
        }

        return $token;
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        $scopes = array_map(
            static fn (ScopeEntityInterface $s): string => $s->getIdentifier(),
            $accessTokenEntity->getScopes()
        );

        try {
            $this->connection->insert('ucp_oauth_access_token', [
                'identifier' => $accessTokenEntity->getIdentifier(),
                'sales_channel_id' => Uuid::fromHexToBytes($this->salesChannelId),
                'client_id' => $accessTokenEntity->getClient()->getIdentifier(),
                'user_identifier' => $accessTokenEntity->getUserIdentifier(),
                'scopes' => json_encode($scopes, \JSON_THROW_ON_ERROR),
                'revoked' => 0,
                'expires_at' => $accessTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s.v'),
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw OAuthServerException::accessDenied('Access token already exists');
        }
    }

    public function revokeAccessToken(string $tokenId): void
    {
        $this->connection->executeStatement(
            'UPDATE ucp_oauth_access_token SET revoked = 1 WHERE identifier = ?',
            [$tokenId]
        );
    }

    public function isAccessTokenRevoked(string $tokenId): bool
    {
        $row = $this->connection->fetchAssociative(
            'SELECT revoked FROM ucp_oauth_access_token WHERE identifier = ? LIMIT 1',
            [$tokenId]
        );
        if (!\is_array($row)) {
            return true;
        }

        return (bool) $row['revoked'];
    }
}
