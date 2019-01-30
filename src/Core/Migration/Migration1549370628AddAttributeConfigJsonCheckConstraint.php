<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1549370628AddAttributeConfigJsonCheckConstraint extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1549370628;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `attribute`
            ADD CONSTRAINT `json.config` CHECK(JSON_VALID(`config`))'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
