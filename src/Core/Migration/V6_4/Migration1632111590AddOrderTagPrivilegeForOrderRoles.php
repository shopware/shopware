<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1632111590AddOrderTagPrivilegeForOrderRoles extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'order.viewer' => [
            'order_tag:read',
        ],
        'order.editor' => [
            'order_tag:create',
            'order_tag:update',
            'order_tag:delete',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1632111590;
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
