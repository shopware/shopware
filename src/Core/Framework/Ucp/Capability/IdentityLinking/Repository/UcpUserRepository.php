<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Repository;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Entity\UcpUserEntity;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Bridges League OAuth2 user-credentials grant to the Shopware
 * `customer` table. UCP does not use password-grant in production, but the
 * interface is required by the grant infrastructure.
 *
 * For the authorization-code grant, user resolution happens via the
 * storefront session — the `userIdentifier` comes from the active
 * customer in the storefront and is set on the AuthorizationRequest by the
 * authorize controller before delegating to League.
 *
 * @internal
 */
#[Package('framework')]
class UcpUserRepository implements UserRepositoryInterface
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

    public function getUserEntityByUserCredentials(
        string $username,
        string $password,
        string $grantType,
        ClientEntityInterface $clientEntity
    ): ?UserEntityInterface {
        // Look up customer by email within this sales channel
        $row = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(id)) as id, password FROM customer
             WHERE bound_sales_channel_id = :sc AND email = :email AND active = 1 LIMIT 1',
            ['sc' => Uuid::fromHexToBytes($this->salesChannelId), 'email' => $username]
        );

        if (!\is_array($row) || !\is_string($row['password'])) {
            return null;
        }

        if (!password_verify($password, $row['password'])) {
            return null;
        }

        $id = (string) $row['id'];
        if ($id === '') {
            return null;
        }

        return new UcpUserEntity($id);
    }
}
