<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1650981517RemoveShopwareId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1650981517;
    }

    public function update(Connection $connection): void
    {
        // nth
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeStatement(
            'DELETE FROM `system_config` WHERE `configuration_key` = "core.store.shopwareId"'
        );
    }
}
