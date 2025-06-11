<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Manifest\Xml\Permission\Permissions;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class PermissionPersister
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Privileges $privileges,
    ) {
    }

    /**
     * @internal only for use by the app-system
     */
    public function updatePrivileges(?Permissions $permissions, string $appId, bool $acceptPermissions, Context $context): void
    {
        $privileges = $permissions ? $permissions->asParsedPrivileges() : [];

        if ($acceptPermissions) {
            $this->privileges->setPrivileges($appId, $privileges, $context);

            return;
        }

        $this->privileges->requestPrivileges($appId, $privileges, $context);
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
}
