<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1550589360AddModuleTypesToRule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1550589360;
    }

    public function update(Connection $connection): void
    {
        $connection->query('
            ALTER TABLE rule
            ADD COLUMN `module_types` JSON NULL AFTER `updated_at`,
            ADD CONSTRAINT `json.module_types` CHECK (JSON_VALID(`module_types`));           
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
