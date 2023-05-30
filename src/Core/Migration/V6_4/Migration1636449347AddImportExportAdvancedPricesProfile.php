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
class Migration1636449347AddImportExportAdvancedPricesProfile extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1636449347;
    }

    public function update(Connection $connection): void
    {
        $id = Uuid::randomBytes();

        $connection->insert('import_export_profile', [
            'id' => $id,
            'name' => 'Default advanced prices',
            'system_default' => 1,
            'source_entity' => 'product_price',
            'file_type' => 'text/csv',
            'delimiter' => ';',
            'enclosure' => '"',
            'type' => 'import-export',
            'mapping' => json_encode([
                ['key' => 'id', 'mappedKey' => 'id', 'position' => 0],
                ['key' => 'productId', 'mappedKey' => 'product_id', 'position' => 1],
                ['key' => 'ruleId', 'mappedKey' => 'rule_id', 'position' => 2],
                ['key' => 'price.DEFAULT.net', 'mappedKey' => 'price_net', 'position' => 3],
                ['key' => 'price.DEFAULT.gross', 'mappedKey' => 'price_gross', 'position' => 4],
                ['key' => 'quantityStart', 'mappedKey' => 'quantity_start', 'position' => 5],
                ['key' => 'quantityEnd', 'mappedKey' => 'quantity_end', 'position' => 6],
            ]),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $translations = new Translations(
            [
                'import_export_profile_id' => $id,
                'label' => 'Standardprofil Erweiterte Preise',
            ],
            [
                'import_export_profile_id' => $id,
                'label' => 'Default advanced prices',
            ]
        );

        $this->importTranslation(ImportExportProfileTranslationDefinition::ENTITY_NAME, $translations, $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
