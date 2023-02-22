<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Manifest\Xml\Permissions;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class PermissionPersister
{
    public function __construct(private readonly Connection $connection)
    {
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
        $this->connection->executeStatement(
            'DELETE FROM `acl_role` WHERE id = :id',
            [
                'id' => Uuid::fromHexToBytes($roleId),
            ]
        );
    }

    public function softDeleteRole(string $roleId): void
    {
        $this->connection->executeStatement(
            'UPDATE `acl_role` SET `deleted_at` = :datetime WHERE id = :id',
            [
                'id' => Uuid::fromHexToBytes($roleId),
                'datetime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    private function addPrivileges(array $privileges, string $roleId): void
    {
        $this->connection->executeStatement(
            'UPDATE `acl_role` SET `privileges` = :privileges WHERE id = :id',
            [
                'privileges' => json_encode($privileges, \JSON_THROW_ON_ERROR),
                'id' => Uuid::fromHexToBytes($roleId),
            ]
        );
    }
}
