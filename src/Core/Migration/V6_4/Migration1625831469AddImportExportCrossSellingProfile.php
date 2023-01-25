<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\ImportExport\ImportExportProfileTranslationDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\Translations;

/**
 * @internal
 */
#[Package('core')]
class Migration1625831469AddImportExportCrossSellingProfile extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1625831469;
    }

    public function update(Connection $connection): void
    {
        $id = Uuid::randomBytes();

        $connection->insert('import_export_profile', [
            'id' => $id,
            'name' => 'Default cross-selling',
            'system_default' => 1,
            'source_entity' => 'product_cross_selling',
            'file_type' => 'text/csv',
            'delimiter' => ';',
            'enclosure' => '"',
            'mapping' => json_encode([
                ['key' => 'id', 'mappedKey' => 'id'],
                ['key' => 'translations.DEFAULT.name', 'mappedKey' => 'name'],
                ['key' => 'productId', 'mappedKey' => 'product_id'],
                ['key' => 'active', 'mappedKey' => 'active'],
                ['key' => 'position', 'mappedKey' => 'position'],
                ['key' => 'limit', 'mappedKey' => 'limit'],
                ['key' => 'type', 'mappedKey' => 'type'],
                ['key' => 'sortBy', 'mappedKey' => 'sort_by'],
                ['key' => 'sortDirection', 'mappedKey' => 'sort_direction'],
                ['key' => 'assignedProducts', 'mappedKey' => 'assigned_products'],
            ]),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $translations = new Translations(
            [
                'import_export_profile_id' => $id,
                'label' => 'Standardprofil Cross-Selling',
            ],
            [
                'import_export_profile_id' => $id,
                'label' => 'Default cross-selling',
            ]
        );

        $this->importTranslation(ImportExportProfileTranslationDefinition::ENTITY_NAME, $translations, $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
