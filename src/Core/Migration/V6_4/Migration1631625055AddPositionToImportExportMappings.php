<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1631625055AddPositionToImportExportMappings extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1631625055;
    }

    public function update(Connection $connection): void
    {
        $profiles = $connection->fetchAllAssociative('SELECT * FROM `import_export_profile`');

        foreach ($profiles as $profile) {
            $mappings = \json_decode($profile['mapping'], true);

            foreach ($mappings as $index => &$mapping) {
                $mapping['position'] = $index;
            }

            $connection->update(
                'import_export_profile',
                ['mapping' => \json_encode($mappings)],
                ['id' => $profile['id']]
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
