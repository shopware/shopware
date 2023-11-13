<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1674200008UpdateOrderViewerRolePrivileges extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'order.viewer' => [
            'media_default_folder:read',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1674200008;
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
