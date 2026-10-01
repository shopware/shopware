<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1670090989AddIndexOrderOrderNumber extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1670090989;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::indexExists($connection, 'order', 'idx.order_number')) {
            return;
        }

        $connection->executeStatement(
            'ALTER TABLE `order` ADD INDEX `idx.order_number` (`order_number`)'
        );
    }
}
