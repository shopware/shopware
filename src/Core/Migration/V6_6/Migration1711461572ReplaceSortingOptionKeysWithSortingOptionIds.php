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
class Migration1711461572ReplaceSortingOptionKeysWithSortingOptionIds extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1711461572;
    }

    public function update(Connection $connection): void
    {
        $this->migrateCategoryConfig($connection);
    }

    private function migrateCategoryConfig(Connection $connection): void
    {
        $categoryEntries = $connection->fetchAllAssociative(<<<'SQL'
            SELECT category_id, category_version_id, language_id, slot_config
            FROM category_translation
            WHERE slot_config IS NOT NULL ;
        SQL);

        $productListingSlotIds = Uuid::fromBytesToHexList($connection->fetchFirstColumn(<<<'SQL'
            SELECT id FROM cms_slot WHERE type = 'product-listing';
        SQL));

        /** @var array<string, string> $productSortings */
        $productSortings = $connection->fetchAllKeyValue(
            'SELECT url_key, id FROM product_sorting;'
        );

        foreach ($categoryEntries as $entry) {
            $oldConfig = $slotConfig = json_decode($entry['slot_config'], true);

            if ($slotConfig === null) {
                continue;
            }

            foreach ($slotConfig as $productListingSlotId => $sortingConfig) {
                if (!\in_array($productListingSlotId, $productListingSlotIds, true)) {
                    continue;
                }

                $slotConfig[$productListingSlotId] = $this->migrateDefaultSortingSlotConfig($sortingConfig, $productSortings);

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
            }

            if ($oldConfig === $slotConfig) {
                continue;
            }

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
     * @param array<string, string> $productSortings
     *
     * @return array<string, mixed>
     */
    private function migrateDefaultSortingSlotConfig(array $sortingConfig, array $productSortings): array
    {
        if (!\array_key_exists('defaultSorting', $sortingConfig) || !\array_key_exists('value', $sortingConfig['defaultSorting']) || empty($sortingConfig['defaultSorting']['value'])) {
            return $sortingConfig;
        }

        $defaultSortingId = $productSortings[$sortingConfig['defaultSorting']['value']] ?? false;

        if ($defaultSortingId === false) {
            return $sortingConfig;
        }

        $sortingConfig['defaultSorting']['value']
            = Uuid::fromBytesToHex($defaultSortingId);

        return $sortingConfig;
    }
}
