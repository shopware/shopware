<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1763125891AddProductTypeColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1763125891;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'product', 'type')) {
            $this->addColumn(
                $connection,
                'product',
                'type',
                'VARCHAR(32)',
                false,
                '\'physical\''
            );

            $connection->executeStatement('CREATE INDEX `idx.product.type` ON `product` (`type`)');
        }

        $batchSize = 5000;

        do {
            $affected = $connection->executeStatement(
                "UPDATE `product`
                 SET `product`.`type` = 'digital'
                 WHERE JSON_CONTAINS(states, '\"is-download\"')
                 LIMIT {$batchSize};"
            );
        } while ($affected > 0);
    }
}
