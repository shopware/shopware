<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1700746995ReplaceSortingOptionKeysWithSortingOptionIds extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700746995;
    }

    public function update(Connection $connection): void
    {
        $this->migrateSystemConfig($connection);

        $this->migrateCategoryConfig($connection);
    }

    private function migrateSystemConfig(Connection $connection): void
    {
        $systemConfigEntries = $connection->fetchAllKeyValue(
            'SELECT id, configuration_value FROM system_config WHERE configuration_key = "core.listing.defaultSorting";'
        );

        foreach ($systemConfigEntries as $id => $configValue) {
            $config = json_decode($configValue, true);

            if (!\array_key_exists('_value', $config)) {
                continue;
            }

            $urlKey = $config['_value'];

            if (!isset($urlKey)) {
                continue;
            }

            $sortingId = $connection->fetchOne(
                'SELECT id FROM product_sorting WHERE url_key = :urlKey;',
                ['urlKey' => $urlKey]
            );

            // the results of invalid url keys are filtered here
            if (!$sortingId) {
                continue;
            }

            $connection->executeStatement(
                'UPDATE system_config SET configuration_value = :configValue WHERE id = :id;',
                ['configValue' => json_encode(['_value' => Uuid::fromBytesToHex($sortingId)]), 'id' => $id]
            );
        }
    }

    private function migrateCategoryConfig(Connection $connection): void
    {
        $categoryEntries = $connection->fetchAllAssociative(<<<'SQL'
            SELECT category_id, category_version_id, language_id, slot_config
            FROM category_translation
            WHERE slot_config IS NOT NULL ;
        SQL);

        $productListingSlotId = Uuid::fromBytesToHex($connection->fetchOne(<<<'SQL'
            SELECT id FROM cms_slot WHERE type = 'product-listing';
        SQL));

        $productSortings = $connection->fetchAllKeyValue(
            'SELECT url_key, id FROM product_sorting;'
        );

        foreach ($categoryEntries as $entry) {
            $slotConfig = json_decode($entry['slot_config'], true);

            if ($slotConfig === null) {
                continue;
            }

            if (!\array_key_exists($productListingSlotId, $slotConfig)) {
                continue;
            }

            $sortingConfig = $slotConfig[$productListingSlotId];

            $slotConfig[$productListingSlotId] = $this->migrateDefaultSortingSlotConfig($connection, $sortingConfig);

            $availableSortings = [];
            if (\array_key_exists('availableSortings', $sortingConfig) && \array_key_exists('value', $sortingConfig['availableSortings'])) {
                $availableSortings = $sortingConfig['availableSortings']['value'];
            }

            $newAvailableSortings = [];

            foreach ($availableSortings as $sortingKey => $priority) {
                if (!\array_key_exists($sortingKey, $productSortings)) {
                    $newAvailableSortings[$sortingKey] = $priority;

                    continue;
                }
                $newAvailableSortings[Uuid::fromBytesToHex($productSortings[$sortingKey])] = $priority;
            }

            $slotConfig[$productListingSlotId]['availableSortings']['value'] = $newAvailableSortings;

            $connection->executeStatement(
                <<<'SQL'
                    UPDATE category_translation
                    SET slot_config = :slotConfig
                    WHERE category_id = :categoryId
                      AND category_version_id = :categoryVersionId
                      AND language_id = :languageId;
                SQL,
                [
                    'slotConfig' => json_encode($slotConfig),
                    'categoryId' => $entry['category_id'],
                    'categoryVersionId' => $entry['category_version_id'],
                    'languageId' => $entry['language_id'],
                ]
            );
        }
    }

    /**
     * @param array<string, mixed> $sortingConfig
     *
     * @return array<string, mixed>
     */
    private function migrateDefaultSortingSlotConfig(Connection $connection, array $sortingConfig): array
    {
        if (!\array_key_exists('defaultSorting', $sortingConfig) || !\array_key_exists('value', $sortingConfig['defaultSorting']) || empty($sortingConfig['defaultSorting']['value'])) {
            return $sortingConfig;
        }

        $defaultSortingId = $connection->fetchOne(
            'SELECT id FROM product_sorting WHERE url_key = :urlKey;',
            ['urlKey' => $sortingConfig['defaultSorting']['value']]
        );

        if ($defaultSortingId === false) {
            return $sortingConfig;
        }

        $sortingConfig['defaultSorting']['value']
            = Uuid::fromBytesToHex($defaultSortingId);

        return $sortingConfig;
    }
}
