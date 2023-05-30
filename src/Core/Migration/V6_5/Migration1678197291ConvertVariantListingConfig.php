<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1678197291ConvertVariantListingConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1678197291;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `product_tmp` (
                 `id` BINARY(16) NOT NULL,
                 `version_id` BINARY(16) NOT NULL,
                 `variant_listing_config` JSON NULL DEFAULT NULL,
                 PRIMARY KEY (`id`, `version_id`)
               ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $connection->executeStatement('INSERT INTO `product_tmp` (SELECT `id`, `version_id`, `variant_listing_config` FROM `product` WHERE variant_listing_config IS NOT NULL)');
        $connection->executeStatement('ALTER TABLE `product` DROP COLUMN `variant_listing_config`');
        $connection->executeStatement('ALTER TABLE `product` ADD COLUMN `variant_listing_config` JSON NULL DEFAULT NULL');

        do {
            $result = $connection->executeStatement(
                'UPDATE `product`
                    SET product.variant_listing_config = (SELECT variant_listing_config FROM product_tmp WHERE product.id = product_tmp.id AND product.version_id = product_tmp.version_id)
                    WHERE product.variant_listing_config IS NULL AND EXISTS (SELECT variant_listing_config FROM product_tmp WHERE product.id = product_tmp.id AND product.version_id = product_tmp.version_id)
                    LIMIT 1000'
            );
        } while ($result > 0);

        $connection->executeStatement('DROP TABLE `product_tmp`');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
