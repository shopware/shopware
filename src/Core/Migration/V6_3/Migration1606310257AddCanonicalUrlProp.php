<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1606310257AddCanonicalUrlProp extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1606310257;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `product`
            ADD `canonical_product_id` BINARY(16) NULL,
            ADD `canonicalProduct` BINARY(16) NULL,
            ADD CONSTRAINT `fk.product.canonical_product_id`
                FOREIGN KEY (`canonical_product_id`)
                REFERENCES `product` (`id`)
                ON DELETE SET NULL

        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
