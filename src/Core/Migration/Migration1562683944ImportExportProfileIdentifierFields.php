<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1562683944ImportExportProfileIdentifierFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1562683944;
    }

    public function update(Connection $connection): void
    {
        $identifierByEntity = [
            'customer' => 'customerNumber',
            'product' => 'productNumber',
        ];

        $profiles = $connection->fetchAll('SELECT id, source_entity, mapping FROM import_export_profile');
        foreach ($profiles as $profile) {
            $mapping = json_decode($profile['mapping'], true);

            foreach ($mapping as $key => $_value) {
                if (isset($identifierByEntity[$profile['source_entity']])) {
                    $identifierField = $identifierByEntity[$profile['source_entity']];
                    $mapping[$key]['isIdentifier'] = $mapping[$key]['entityField'] === $identifierField;
                }
            }

            $connection->update(
                'import_export_profile',
                [
                    'mapping' => json_encode($mapping),
                    'updated_at' => date(Defaults::STORAGE_DATE_FORMAT),
                ],
                [
                    'id' => $profile['id'],
                ]
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
