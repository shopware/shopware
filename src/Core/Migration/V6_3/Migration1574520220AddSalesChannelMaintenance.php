<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1574520220AddSalesChannelMaintenance extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1574520220;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `sales_channel` ADD `maintenance` tinyint(1) NOT NULL DEFAULT 0 AFTER `active`
        ');
        $connection->executeStatement('
            ALTER TABLE `sales_channel` ADD `maintenance_ip_whitelist` JSON NULL AFTER `maintenance`
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
