<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1773829000MigrateLineItemProductStatesRuleCondition extends MigrationStep
{
    private const LEGACY_PRODUCT_STATE_TO_TYPE_MAP = [
        'is-download' => 'digital',
        'is-physical' => 'physical',
    ];

    public function getCreationTimestamp(): int
    {
        return 1773829000;
    }

    public function update(Connection $connection): void
    {
        // Intentionally empty.
        //
        // This migration originally converted cartLineItemProductStates rule conditions
        // to cartLineItemProductType during the 6.7 upgrade. However, 6.6 code cannot
        // evaluate cartLineItemProductType (LineItemProductTypeRule was introduced in 6.7),
        // so running this conversion in update() breaks blue-green deployments where 6.6
        // pods are still running while the 6.7 DB migration has already been applied.
        //
        // The conversion is performed in updateDestructive(), which runs after the 6.6/6.7
        // blue-green window has definitively closed.
    }

    public function updateDestructive(Connection $connection): void
    {
        $conditions = $connection->fetchAllAssociative(
            'SELECT `id`, `value` FROM `rule_condition` WHERE `type` = :legacyType',
            ['legacyType' => 'cartLineItemProductStates']
        );

        if ($conditions === []) {
            return;
        }

        $migrated = false;

        foreach ($conditions as $condition) {
            $newValue = $this->conditionPayload($condition['value']);

            if ($newValue === null) {
                continue;
            }

            $connection->update(
                'rule_condition',
                [
                    'type' => 'cartLineItemProductType',
                    'value' => $newValue,
                ],
                ['id' => $condition['id']]
            );

            $migrated = true;
        }

        if ($migrated) {
            $this->registerIndexer($connection, 'rule.indexer');
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
}
