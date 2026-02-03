<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1770131288SetDefaultValueForProductStreamInternal extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770131288;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'product_stream', 'internal')) {
            return;
        }

        $connection->executeStatement('UPDATE `product_stream` SET `internal` = 0 WHERE `internal` IS NULL');
        $connection->executeStatement('ALTER TABLE `product_stream` MODIFY COLUMN `internal` TINYINT(1) NOT NULL DEFAULT 0');
    }
}
