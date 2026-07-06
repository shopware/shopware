<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1773829001MigrateProductStreamProductStatesFilter extends MigrationStep
{
    private const LEGACY_PRODUCT_STATE_TO_TYPE_MAP = [
        'is-download' => 'digital',
        'is-physical' => 'physical',
    ];

    public function getCreationTimestamp(): int
    {
        return 1773829001;
    }

    public function update(Connection $connection): void
    {
        // Intentionally empty.
        //
        // This migration originally converted product_stream_filter fields from
        // `states`/`product.states` to `type`/`product.type` during the 6.7 upgrade.
        // However, 6.6 code cannot evaluate the new field names (support was introduced
        // in 6.7), so running this conversion in update() breaks blue-green deployments
        // where 6.6 pods are still running while the 6.7 DB migration has already been applied.
        //
        // The conversion is performed in updateDestructive(), which runs after the 6.6/6.7
        // blue-green window has definitively closed.
    }

    public function updateDestructive(Connection $connection): void
    {
        $filters = $connection->fetchAllAssociative(
            'SELECT `id`, `field`, `value` FROM `product_stream_filter` WHERE `field` IN (:fields)',
            ['fields' => ['states', 'product.states']],
            ['fields' => ArrayParameterType::STRING]
        );

        if ($filters === []) {
            return;
        }

        $migrated = false;

        foreach ($filters as $filter) {
            $field = (string) $filter['field'];
            $targetField = $field === 'product.states' ? 'product.type' : 'type';
            $value = $filter['value'];
            $targetValue = \is_string($value) ? $this->mapLegacyStateValues($value) : null;

            if ($targetValue === null) {
                continue;
            }

            $connection->update(
                'product_stream_filter',
                [
                    'field' => $targetField,
                    'value' => $targetValue,
                ],
                ['id' => $filter['id']]
            );

            $migrated = true;
        }

        if ($migrated) {
            $this->registerIndexer($connection, 'product_stream.indexer');
        }
    }

    private function mapLegacyStateValues(string $value): ?string
    {
        $values = explode('|', $value);
        $mappedValues = [];

        foreach ($values as $state) {
            if (!isset(self::LEGACY_PRODUCT_STATE_TO_TYPE_MAP[$state])) {
                return null;
            }

            $mappedValues[] = self::LEGACY_PRODUCT_STATE_TO_TYPE_MAP[$state];
        }

        $mappedValues = array_values(array_unique($mappedValues));

        if ($mappedValues === []) {
            return null;
        }

        return implode('|', $mappedValues);
    }
}
