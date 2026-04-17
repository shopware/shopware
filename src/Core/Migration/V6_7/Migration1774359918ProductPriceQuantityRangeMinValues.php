<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1774359918ProductPriceQuantityRangeMinValues extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1774359918;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE `product_price` SET `quantity_start` = 1 WHERE `quantity_start` < 1');
        $connection->executeStatement('UPDATE `product_price` SET `quantity_end` = 1 WHERE `quantity_end` < 1');
    }
}
