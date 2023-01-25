<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('system-settings')]
class Migration1673001912AddUserPermissionRolePrivilege extends MigrationStep
{
    public const NEW_PRIVILEGES = [
        'users_and_permissions.viewer' => [
            'currency:read',
            'system_config:read',
        ],
        'users_and_permissions.editor' => [
            'system_config:create',
            'system_config:update',
            'system_config:delete',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1673001912;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
