<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1546262017AddRuleInvalidFlag extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1546262017;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('ALTER TABLE `rule` ADD `invalid` TINYINT(1) NULL AFTER `payload`;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
