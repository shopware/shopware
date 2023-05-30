<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1612442686AddDeleteCascadeToCustomerTagTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1612442686;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `customer_tag` DROP FOREIGN KEY `fk.customer_tag.customer_id`;');
        $connection->executeStatement('ALTER TABLE `customer_tag` ADD CONSTRAINT `fk.customer_tag.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
