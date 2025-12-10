<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1763125892RemoveProductStatesColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1763125892;
    }

    public function update(Connection $connection): void
    {
        // Re-register the rule indexer to ensure rule which might depend on the removed column are re-indexed properly
        $this->registerIndexer($connection, 'rule.indexer');
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'product', 'states');
    }
}
