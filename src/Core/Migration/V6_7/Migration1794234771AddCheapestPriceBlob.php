<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1794234771AddCheapestPriceBlob extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794234771;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'product', 'cheapest_price_blob')) {
            // Fail instead of rebuilding a potentially large product table when INSTANT is unavailable.
            $this->executeDdlStatement($connection, 'ALTER TABLE `product` ADD COLUMN `cheapest_price_blob` LONGBLOB NULL, ALGORITHM=INSTANT');
        }
    }
}
