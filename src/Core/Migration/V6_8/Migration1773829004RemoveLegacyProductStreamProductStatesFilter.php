<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1773829004RemoveLegacyProductStreamProductStatesFilter extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1773829004;
    }

    public function update(Connection $connection): void
    {
        $deleted = $connection->executeStatement(
            'DELETE FROM `product_stream_filter` WHERE `field` IN (:fields)',
            ['fields' => ['states', 'product.states']],
            ['fields' => ArrayParameterType::STRING]
        );

        if ($deleted > 0) {
            $this->registerIndexer($connection, 'product_stream.indexer');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

