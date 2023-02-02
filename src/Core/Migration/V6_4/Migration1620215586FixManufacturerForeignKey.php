<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1620215586FixManufacturerForeignKey extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1620215586;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `product` DROP FOREIGN KEY `fk.product.product_manufacturer_id`;');
        $connection->executeStatement('ALTER TABLE `product` ADD FOREIGN KEY (`product_manufacturer_id`, `product_manufacturer_version_id`) REFERENCES `product_manufacturer` (`id`, `version_id`) ON DELETE SET NULL ON UPDATE CASCADE;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
