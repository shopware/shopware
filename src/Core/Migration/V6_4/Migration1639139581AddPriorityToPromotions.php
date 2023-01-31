<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1639139581AddPriorityToPromotions extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1639139581;
    }

    public function update(Connection $connection): void
    {
        $columns = array_column($connection->fetchAllAssociative('SHOW COLUMNS FROM promotion'), 'Field');

        // Column already exist?
        if (\in_array('priority', $columns, true)) {
            return;
        }

        $sql = <<<'SQL'
ALTER TABLE `promotion` ADD COLUMN `priority` INT(11) NOT NULL DEFAULT 1 AFTER `max_redemptions_per_customer`;
SQL;

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
