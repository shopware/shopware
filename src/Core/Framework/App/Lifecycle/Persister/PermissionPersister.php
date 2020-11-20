<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\Manifest\Xml\Permissions;
use Shopware\Core\Framework\Uuid\Uuid;

class PermissionPersister
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @internal only for use by the app-system
     */
    public function updatePrivileges(?Permissions $permissions, string $roleId): void
    {
        $privileges = $permissions ? $permissions->asParsedPrivileges() : [];

        $this->addPrivileges($privileges, $roleId);
    }

    /**
     * @internal only for use by the app-system
     */
    public function removeRole(string $roleId): void
    {
        $this->connection->executeUpdate(
            'DELETE FROM `acl_role` WHERE id = :id',
            [
                'id' => Uuid::fromHexToBytes($roleId),
            ]
        );
    }

    private function addPrivileges(array $privileges, string $roleId): void
    {
        $this->connection->executeUpdate(
            'UPDATE `acl_role` SET `privileges` = :privileges WHERE id = :id',
            [
                'privileges' => json_encode($privileges),
                'id' => Uuid::fromHexToBytes($roleId),
            ]
        );
    }
}
