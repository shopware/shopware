<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1773900000AddProductSearchStrictness extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1773900000;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'product_search_config', 'strictness')) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `product_search_config`
            ADD COLUMN `strictness` SMALLINT NOT NULL DEFAULT 0 AFTER `and_logic`
        ');

        $connection->executeStatement('
            UPDATE `product_search_config`
            SET `strictness` = IF(`and_logic` = 1, 100, 0)
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
