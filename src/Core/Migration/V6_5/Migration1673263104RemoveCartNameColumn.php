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
class Migration1673263104RemoveCartNameColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1673263104;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'cart', 'name')) {
            return;
        }

        $column = TableHelper::getColumnOfTable($connection, 'cart', 'name');

        if ($column->isNotNull) {
            $connection->executeStatement(
                'ALTER TABLE `cart` CHANGE `name` `name` VARCHAR(500) COLLATE utf8mb4_unicode_ci'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'cart', 'name');
    }
}
