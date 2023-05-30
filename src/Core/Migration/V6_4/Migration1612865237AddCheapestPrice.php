<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1612865237AddCheapestPrice extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1612865237;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `product` ADD `cheapest_price` longtext NULL;');
        $connection->executeStatement('ALTER TABLE `product` ADD `cheapest_price_accessor` longtext NULL;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}
