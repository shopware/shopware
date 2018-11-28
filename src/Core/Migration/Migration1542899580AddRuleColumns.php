<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1542899580AddRuleColumns extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1542899580;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
ALTER TABLE `rule`
ADD `type` VARCHAR(256) NULL,
ADD `description` LONGTEXT NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}
