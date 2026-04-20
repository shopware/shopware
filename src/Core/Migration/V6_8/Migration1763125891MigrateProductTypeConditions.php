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
class Migration1763125891MigrateProductTypeConditions extends MigrationStep
{
    private const LEGACY_PRODUCT_STATE_TO_TYPE_MAP = [
        'is-download' => 'digital',
        'is-physical' => 'physical',
    ];

    public function getCreationTimestamp(): int
    {
        return 1763125891;
    }

    public function update(Connection $connection): void
    {
        // This migration was originally intended to run during the 6.7 upgrade in
        // V6_7\Migration1773829000 and V6_7\Migration1773829001. Those migrations were
        // made no-ops to preserve blue-green compatibility (6.6 code cannot evaluate the
        // new cartLineItemProductType conditions or product.type stream filters introduced
        // in 6.7). The conversion is safe here because it runs during the 6.7 -> 6.8 upgrade,
        // after the 6.6/6.7 blue-green window has definitively closed, and before
        // LineItemProductStatesRule is removed from the codebase in 6.8.

        $this->migrateRuleConditions($connection);
        $this->migrateProductStreamFilters($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function migrateRuleConditions(Connection $connection): void
    {
        $conditions = $connection->fetchAllAssociative(
            "SELECT `id`, `value` FROM `rule_condition` WHERE `type` = 'cartLineItemProductStates'"
        );

        if ($conditions === []) {
            return;
        }

        foreach ($conditions as $condition) {
            $payload = $this->conditionPayload($condition['value']);

            if ($payload === null) {
                continue;
            }

            $connection->update(
                'rule_condition',
                [
                    'type' => 'cartLineItemProductType',
                    'value' => $payload,
                ],
                ['id' => $condition['id']]
            );
        }

        $this->registerIndexer($connection, 'rule.indexer');
    }

    private function migrateProductStreamFilters(Connection $connection): void
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

    private function conditionPayload(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        if (!\is_array($decoded)) {
            return null;
        }

        $productState = $decoded['productState'] ?? null;

        if (!\is_string($productState) || !isset(self::LEGACY_PRODUCT_STATE_TO_TYPE_MAP[$productState])) {
            return null;
        }

        $operator = \is_string($decoded['operator'] ?? null) ? $decoded['operator'] : '=';
        $productType = self::LEGACY_PRODUCT_STATE_TO_TYPE_MAP[$productState];

        return json_encode(['operator' => $operator, 'productType' => $productType], \JSON_THROW_ON_ERROR);
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
