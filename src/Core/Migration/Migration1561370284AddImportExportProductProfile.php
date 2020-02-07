<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1561370284AddImportExportProductProfile extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1561370284;
    }

    public function update(Connection $connection): void
    {
        $mapping = [];

        $fields = [
            'name.de-DE',
            'name.en-GB',
            'stock',
            'manufacturer.id',
            'tax.id',
            'price.net',
            'price.gross',
            'price.linked',
            'productNumber',
            'releaseDate',
            'categories.id',
        ];

        foreach ($fields as $fieldName) {
            $mapping[] = [
                'fileField' => $fieldName,
                'entityField' => $fieldName,
                'valueSubstitutions' => [],
            ];
        }

        $connection->insert('import_export_profile', [
            'id' => Uuid::randomBytes(),
            'name' => 'Default product',
            'system_default' => 1,
            'source_entity' => 'product',
            'file_type' => 'text/csv',
            'delimiter' => ';',
            'enclosure' => '"',
            'mapping' => json_encode($mapping),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
