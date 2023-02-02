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
class Migration1636971615AddImportExportPromotionDiscountProfile extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1636971615;
    }

    public function update(Connection $connection): void
    {
        $id = Uuid::randomBytes();

        $connection->insert('import_export_profile', [
            'id' => $id,
            'name' => 'Default promotion discounts',
            'system_default' => 1,
            'source_entity' => 'promotion_discount',
            'file_type' => 'text/csv',
            'delimiter' => ';',
            'enclosure' => '"',
            'type' => 'import-export',
            'mapping' => json_encode([
                ['key' => 'id', 'mappedKey' => 'id', 'position' => 0],
                ['key' => 'promotionId', 'mappedKey' => 'promotion_id', 'position' => 1],
                ['key' => 'scope', 'mappedKey' => 'scope', 'position' => 2],
                ['key' => 'type', 'mappedKey' => 'type', 'position' => 3],
                ['key' => 'value', 'mappedKey' => 'value', 'position' => 4],
                ['key' => 'considerAdvancedRules', 'mappedKey' => 'consider_advanced_rules', 'position' => 5],
                ['key' => 'maxValue', 'mappedKey' => 'max_value', 'position' => 6],
                ['key' => 'sorterKey', 'mappedKey' => 'sorter_key', 'position' => 7, 'useDefaultValue' => true, 'defaultValue' => 'PRICE_ASC'],
                ['key' => 'applierKey', 'mappedKey' => 'applier_key', 'position' => 8, 'useDefaultValue' => true, 'defaultValue' => 'ALL'],
                ['key' => 'usageKey', 'mappedKey' => 'usage_key', 'position' => 9, 'useDefaultValue' => true, 'defaultValue' => 'ALL'],
                ['key' => 'pickerKey', 'mappedKey' => 'picker_key', 'position' => 10],
                ['key' => 'discountRules', 'mappedKey' => 'discount_rules', 'position' => 11],
            ]),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $translations = new Translations(
            [
                'import_export_profile_id' => $id,
                'label' => 'Standardprofil Aktionsrabatte',
            ],
            [
                'import_export_profile_id' => $id,
                'label' => 'Default promotion discounts',
            ]
        );

        $this->importTranslation(ImportExportProfileTranslationDefinition::ENTITY_NAME, $translations, $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
