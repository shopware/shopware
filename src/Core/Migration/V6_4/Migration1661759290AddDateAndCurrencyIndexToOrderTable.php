<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @package core
 *
 * @internal
 */
class Migration1661759290AddDateAndCurrencyIndexToOrderTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1661759290;
    }

    public function update(Connection $connection): void
    {
        if ($this->indexExists($connection)) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `order` ADD INDEX `idx.order_date_currency_id` (`order_date`, `currency_id`)
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function indexExists(Connection $connection): bool
    {
        $index = $connection->executeQuery(
            'SHOW INDEXES FROM `order` WHERE key_name = :indexName',
            ['indexName' => 'idx.order_date_currency_id']
        )->fetchOne();

        return $index !== false;
    }
}
