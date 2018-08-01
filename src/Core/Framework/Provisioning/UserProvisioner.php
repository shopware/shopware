<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Provisioning;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Util\Random;

class UserProvisioner
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function provision(string $tenantId, string $username, ?string $password = null): string
    {
        if ($this->userExists($username, $tenantId)) {
            throw new \RuntimeException(sprintf('User with username "%s" already exists.', $username));
        }

        $password = $password ?? Random::getAlphanumericString(8);

        $userPayload = [
            'id' => Uuid::uuid4()->getBytes(),
            'name' => $username,
            'tenant_id' => Uuid::fromHexToBytes($tenantId),
            'email' => 'info@shopware.com',
            'username' => $username,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'locale_id' => Uuid::fromHexToBytes(Defaults::LOCALE),
            'locale_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'locale_tenant_id' => Uuid::fromHexToBytes($tenantId),
            'active' => true,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $this->connection->insert('user', $userPayload);

        return $password;
    }

    private function userExists(string $username, string $tenantId): bool
    {
        $builder = $this->connection->createQueryBuilder();

        return $builder->select(1)
            ->from('user')
            ->where('username = :username')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('username', $username)
            ->setParameter('tenantId', Uuid::fromHexToBytes($tenantId))
            ->execute()
            ->rowCount() > 0;
    }
}
