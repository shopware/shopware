<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1551360812RuleInvalidDefault extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1551360812;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `rule`
            MODIFY `invalid` TINYINT(1) NOT NULL DEFAULT 0
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
