<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1584437191ImportExportLogAddConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1584437191;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate(
            'ALTER TABLE import_export_log 
            ADD COLUMN config JSON,
            ADD CONSTRAINT `json.import_export_log.config` CHECK (JSON_VALID(`config`))'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
