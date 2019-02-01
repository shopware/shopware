<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1549016746AddAttributeConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1549016746;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `attribute`
            ADD COLUMN `config` JSON DEFAULT NULL AFTER `type`'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
