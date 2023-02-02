<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1627650101AddUploadPluginRolePrivilege extends MigrationStep
{
    public const NEW_PRIVILEGES = [
        'system.plugin_maintain' => [
            'user_config:read',
            'user_config:create',
            'user_config:update',
            'system.plugin_upload',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1627650101;
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
