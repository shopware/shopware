<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1780645634AddProductDescriptionTeaser extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780645634;
    }

    public function update(Connection $connection): void
    {
        if ($this->columnExists($connection, 'product_translation', 'description_teaser')) {
            return;
        }

        $this->addColumn($connection, 'product_translation', 'description_teaser', 'VARCHAR(512)');

        /**
         * Backfilling synchronously does not scale (a one-update-per-row loop already exceeds the
         * 10s migration budget at ~50k translation rows), so existing rows are reconciled
         * asynchronously by the registered indexer after the update.
         */
        $this->registerIndexer($connection, 'product.description_teaser.indexer');
    }
}
