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
class Migration1772110917Rule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1772110917;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'rule', 'filter_by_sales_channel')) {
            return;
        }

        $query = <<<'SQL'
            ALTER TABLE `rule` ADD `filter_by_sales_channel` TINYINT DEFAULT 0;
        SQL;

        $connection->executeStatement($query);
    }

    public function updateDestructive(Connection $connection): void
    {
        // Add destructive update if necessary
    }
}
