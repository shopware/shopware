<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\ImportExport\ImportExportProfileDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1584002637AddImportExportMediaFolder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1584002637;
    }

    public function update(Connection $connection): void
    {
        $defaultFolderId = Uuid::randomBytes();

        $connection->insert('media_default_folder', [
            'id' => $defaultFolderId,
            'entity' => 'import_export_profile',
            'association_fields' => '[]',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $connection->insert('media_folder', [
            'id' => Uuid::randomBytes(),
            'default_folder_id' => $defaultFolderId,
            'name' => 'Import Media',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
