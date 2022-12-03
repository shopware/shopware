<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @package core
 *
 * @internal
 */
class Migration1670090989AddIndexOrderOrderNumber extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1670090989;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'order', 'order_number')) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `order` ADD KEY `idx.order_number` (`order_number`)');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
