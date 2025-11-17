<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

#[Package('inventory')]
class Migration1763125891AddProductTypeColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1763125891;
    }

    public function update(Connection $connection): void
    {
        if (!EntityDefinitionQueryHelper::columnExists($connection, 'product', 'type')) {
            $connection->executeStatement(
                "ALTER TABLE `product` ADD `type` VARCHAR(32) NULL"
            );
            $connection->executeStatement('CREATE INDEX `idx.product.type` ON `product` (`type`)');
        }

        $connection->executeStatement(<<<'SQL'
            UPDATE `product`
                LEFT JOIN `product_download` 
                    ON `product`.`id` = `product_download`.`product_id` AND `product`.`version_id` = `product_download`.`product_version_id`
             SET `product`.`type` = IF(`product_download`.`product_id` IS NOT NULL, 'digital', 'physical')
            SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'product', 'states');
    }
}
