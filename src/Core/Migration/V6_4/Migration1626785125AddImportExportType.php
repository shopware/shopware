<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1626785125AddImportExportType extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1626785125;
    }

    public function update(Connection $connection): void
    {
        $column = $connection->fetchOne(
            'SHOW COLUMNS FROM `import_export_profile` WHERE `Field` LIKE :column;',
            ['column' => 'type']
        );

        if ($column === false) {
            $connection->executeStatement(
                'ALTER TABLE import_export_profile
            ADD COLUMN type varchar(255) NOT NULL DEFAULT "import-export" AFTER `enclosure`'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
