<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1558443337PromotionSalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1558443337;
    }

    public function update(Connection $connection): void
    {
        foreach ($this->getQueries() as $query) {
            $connection->executeStatement($query);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    /**
     * @return list<string>
     */
    private function getQueries(): array
    {
        return [
            'ALTER TABLE `promotion_sales_channel` DROP FOREIGN KEY `fk.promotion_sales_channel.promotion_id`;',
            'ALTER TABLE `promotion_sales_channel` DROP FOREIGN KEY `fk.promotion_sales_channel.sales_channel_id`;',
            'ALTER TABLE `promotion_sales_channel` ADD CONSTRAINT `fk.promotion_sales_channel.promotion_id` FOREIGN KEY (`promotion_id`) REFERENCES `promotion` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;',
            'ALTER TABLE `promotion_sales_channel` ADD CONSTRAINT `fk.promotion_sales_channel.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;',
        ];
    }
}
