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
class Migration1711418838ReplaceSortingOptionKeysWithSortingOptionIdsInCmsSlots extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1711418838;
    }

    public function update(Connection $connection): void
    {
        $this->migrateCmsSlots($connection);
    }

    private function migrateCmsSlots(Connection $connection): void
    {
        /** @var array<string, string> $sortingIds */
        $sortingIds = $connection->fetchAllKeyValue('SELECT url_key, lower(hex(id)) FROM product_sorting');

        $slots = $connection->fetchAllAssociative(<<<'SQL'
            SELECT cms_slot_id, cms_slot_version_id, language_id, config
            FROM cms_slot_translation
            WHERE JSON_CONTAINS_PATH(config, 'one', '$.defaultSorting')
                OR JSON_CONTAINS_PATH(config, 'one', '$.availableSortings');
        SQL);

        foreach ($slots as $slot) {
            $originalConfig = $updatedConfig = json_decode($slot['config'], true);

            $currentConfigValue = $originalConfig['defaultSorting']['value'] ?? false;

            if ($currentConfigValue && isset($sortingIds[$currentConfigValue])) {
                $updatedConfig['defaultSorting']['value'] = $sortingIds[$currentConfigValue];
            }

            $availableSortingKeys = $originalConfig['availableSortings']['value'] ?? [];
            $availableSortingIds = [];

            foreach ($availableSortingKeys as $sortingValueKey => $sortingValue) {
                // check if the sorting value key is already valid uuid
                if (Uuid::isValid($sortingValueKey)) {
                    $availableSortingIds[$sortingValueKey] = $sortingValue;

                    continue;
                }

                if (isset($sortingIds[$sortingValueKey])) {
                    $availableSortingIds[$sortingIds[$sortingValueKey]] = $sortingValue;
                }
            }

            if (!empty($availableSortingIds)) {
                $updatedConfig['availableSortings']['value'] = $availableSortingIds;
            }

            if ($originalConfig === $updatedConfig) {
                continue;
            }

            $connection->executeStatement(
                'UPDATE cms_slot_translation SET config = :config
                 WHERE cms_slot_id = :cms_slot_id
                     AND cms_slot_version_id = :cms_slot_version_id
                     AND language_id = :language_id',
                [
                    'config' => json_encode($updatedConfig),
                    'cms_slot_id' => $slot['cms_slot_id'],
                    'cms_slot_version_id' => $slot['cms_slot_version_id'],
                    'language_id' => $slot['language_id'],
                ]
            );
        }
    }
}
