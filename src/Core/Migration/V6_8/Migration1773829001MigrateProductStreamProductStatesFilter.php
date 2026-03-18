<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

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

        foreach ($filters as $filter) {
            $field = (string) $filter['field'];
            $targetField = $field === 'product.states' ? 'product.type' : 'type';
            $value = $filter['value'];
            $targetValue = \is_string($value) ? $this->mapLegacyStateValues($value) : $value;

            $connection->update(
                'product_stream_filter',
                [
                    'field' => $targetField,
                    'value' => $targetValue,
                ],
                ['id' => $filter['id']]
            );
        }

        $this->registerIndexer($connection, 'product_stream.indexer');
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function mapLegacyStateValues(string $value): string
    {
        $values = explode('|', $value);

        $mappedValues = [];

        foreach ($values as $state) {
            // In case of an unknown state, we want to default to physical to prevent products from being excluded from the stream.
            $mappedValues[] = self::LEGACY_PRODUCT_STATE_TO_TYPE_MAP[$state] ?? 'physical';
        }

        return implode('|', array_values(array_unique($mappedValues)));
    }
}
