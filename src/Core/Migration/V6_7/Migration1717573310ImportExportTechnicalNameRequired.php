<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\ImportExport\ImportExportProfileDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('services-settings')]
class Migration1717573310ImportExportTechnicalNameRequired extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1717573310;
    }

    public function update(Connection $connection): void
    {
        $manager = $connection->createSchemaManager();
        $columns = $manager->listTableColumns(ImportExportProfileDefinition::ENTITY_NAME);

        if (\array_key_exists('technical_name', $columns) && !$columns['technical_name']->getNotnull()) {
            $connection->executeStatement('ALTER TABLE `import_export_profile` MODIFY COLUMN `technical_name` VARCHAR(255) NOT NULL');
        }
    }
}
