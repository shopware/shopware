<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1776803396RegisterProductIndexer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1776803396;
    }

    public function update(Connection $connection): void
    {
        // Catch-up full re-index for shops that ran
        // Migration1691662140MigrateAvailableStock or
        // Migration1726049442UpdateVariantListingConfigInProductTable without
        // a follow-up product.indexer refresh in the same major.
        $this->registerIndexer($connection, 'product.indexer');
    }
}
