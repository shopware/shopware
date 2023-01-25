<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1670090989AddIndexOrderOrderNumber extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1670090989;
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function update(Connection $connection): void
    {
        $key = $connection->executeQuery(
            'SHOW KEYS FROM `order` WHERE Column_name="order_number" AND Key_name="idx.order_number"'
        )->fetchAssociative();

        if (!empty($key)) {
            return;
        }

        $connection->executeStatement(
            'ALTER TABLE `order` ADD KEY `idx.order_number` (`order_number`)'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
