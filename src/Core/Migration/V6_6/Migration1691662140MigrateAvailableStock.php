<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1691662140MigrateAvailableStock extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1691662140;
    }

    public function update(Connection $connection): void
    {
        do {
            $ids = $connection->fetchFirstColumn(
                <<<'SQL'
                    SELECT id
                    FROM product
                    WHERE stock != available_stock
                    LIMIT 1000
                SQL,
            );

            $connection->executeStatement(
                'UPDATE product SET stock = available_stock WHERE id IN (:ids)',
                ['ids' => $ids],
                ['ids' => ArrayParameterType::BINARY]
            );
        } while (!empty($ids));
    }
}
