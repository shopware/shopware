<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1547455252AddRuleConditionSorting extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1547455252;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('ALTER TABLE `rule_condition` add column `sort` INT(11) DEFAULT 0 NOT NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
