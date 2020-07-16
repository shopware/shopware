<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1594887171TempImportExportProfileTranslationPK extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1594887171;
    }

    public function update(Connection $connection): void
    {
        try {
            $connection->executeUpdate('
                ALTER TABLE `temp_import_export_profile_translation`
                ADD PRIMARY KEY (`id`);
            ');
        } catch (DBALException $e) {
            // PK already exists
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing
    }
}
