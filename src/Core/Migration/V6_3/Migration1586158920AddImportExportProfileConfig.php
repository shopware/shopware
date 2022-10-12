<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Migrations will be internal in v6.5.0
 */
class Migration1586158920AddImportExportProfileConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1586158920;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
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
