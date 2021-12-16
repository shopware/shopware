<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1639139581AddPriorityToPromotions extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1639139581;
    }

    public function update(Connection $connection): void
    {
        $columns = $connection->getSchemaManager()->listTableColumns('promotion');

        // Column already exist?
        if (\array_key_exists('priority', $columns)) {
            return;
        }

        $sql = <<<SQL
ALTER TABLE `promotion` ADD COLUMN `priority` INT(11) NOT NULL DEFAULT 1 AFTER `max_redemptions_per_customer`;
SQL;

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
