<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Entity\UcpRefreshTokenEntity;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * @internal
 */
#[Package('framework')]
class UcpRefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getNewRefreshToken(): ?RefreshTokenEntityInterface
    {
        return new UcpRefreshTokenEntity();
    }

    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        try {
            $this->connection->insert('ucp_oauth_refresh_token', [
                'identifier' => $refreshTokenEntity->getIdentifier(),
                'access_token_identifier' => $refreshTokenEntity->getAccessToken()->getIdentifier(),
                'revoked' => 0,
                'expires_at' => $refreshTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s.v'),
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw OAuthServerException::accessDenied('Refresh token already exists');
        }
    }

    public function revokeRefreshToken(string $tokenId): void
    {
        $this->connection->executeStatement(
            'UPDATE ucp_oauth_refresh_token SET revoked = 1 WHERE identifier = ?',
            [$tokenId]
        );
    }

    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        $row = $this->connection->fetchAssociative(
            'SELECT revoked FROM ucp_oauth_refresh_token WHERE identifier = ? LIMIT 1',
            [$tokenId]
        );
        if (!\is_array($row)) {
            return true;
        }

        return (bool) $row['revoked'];
    }
}
