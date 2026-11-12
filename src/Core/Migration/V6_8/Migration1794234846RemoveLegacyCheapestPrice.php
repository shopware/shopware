<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPriceBlobPostUpdater;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Normal migrations schedule reconciliation; destructive migrations remove the legacy column.
 * The updater compares both columns when available, or rebuilds only NULL blobs after removal.
 *
 * @internal
 */
#[Package('framework')]
class Migration1794234846RemoveLegacyCheapestPrice extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794234846;
    }

    public function update(Connection $connection): void
    {
        $this->registerIndexer($connection, CheapestPriceBlobPostUpdater::NAME);
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'product', 'cheapest_price');
    }
}
