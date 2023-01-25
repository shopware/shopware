<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1572957455AddAffiliateTrackingColumns extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1572957455;
    }

    public function update(Connection $connection): void
    {
        $this->addCustomerColumns($connection);

        $this->addOrderColumns($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function addCustomerColumns(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `customer`
            ADD COLUMN `affiliate_code` varchar(255) NULL AFTER `custom_fields`,
            ADD COLUMN `campaign_code` varchar(255) NULL AFTER `affiliate_code`
        ');
    }

    private function addOrderColumns(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `order`
            ADD COLUMN `affiliate_code` varchar(255) NULL AFTER `custom_fields`,
            ADD COLUMN `campaign_code` varchar(255) NULL AFTER `affiliate_code`
        ');
    }
}
