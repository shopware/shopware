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

    public function updateDestructive(Connection $connection): void
    {
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
