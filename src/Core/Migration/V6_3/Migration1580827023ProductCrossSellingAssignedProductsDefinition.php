<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1580827023ProductCrossSellingAssignedProductsDefinition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1580827023;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `product_cross_selling_assigned_products` (
  `id` binary(16) NOT NULL,
  `cross_selling_id` binary(16) NOT NULL,
  `product_id` binary(16) NOT NULL,
  `product_version_id` binary(16) NOT NULL,
  `position` int DEFAULT 0 NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk.product_cross_selling_assigned_products.cross_selling_id` (`cross_selling_id`),
  KEY `product_id` (`product_id`,`product_version_id`),
  CONSTRAINT `fk.product_cross_selling_assigned_products.cross_selling_id` FOREIGN KEY (`cross_selling_id`) REFERENCES `product_cross_selling` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `product_cross_selling_assigned_products_ibfk_2` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
');

        $connection->executeStatement('ALTER TABLE `product_cross_selling`
ADD `type` varchar(255) NOT NULL AFTER `id`;');

        $connection->executeStatement('ALTER TABLE `product_cross_selling`
CHANGE `product_stream_id` `product_stream_id` binary(16) NULL AFTER `product_version_id`;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
