<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1586158920AddImportExportProfileConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1586158920;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate(
            'ALTER TABLE import_export_profile
            ADD COLUMN config JSON,
            ADD CONSTRAINT `json.import_export_profile.config` CHECK (JSON_VALID(`config`))'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
