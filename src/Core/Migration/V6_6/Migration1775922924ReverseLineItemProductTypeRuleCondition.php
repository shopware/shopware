<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * Reverse migration for blue-green compatibility: converts cartLineItemProductType
 * back to cartLineItemProductStates so 6.6 code can evaluate these rule conditions.
 */
#[Package('inventory')]
class Migration1775922924ReverseLineItemProductTypeRuleCondition extends MigrationStep
{
    private const PRODUCT_TYPE_TO_LEGACY_STATE_MAP = [
        'digital' => 'is-download',
        'physical' => 'is-physical',
    ];

    public function getCreationTimestamp(): int
    {
        return 1775922924;
    }

    public function update(Connection $connection): void
    {
        $conditions = $connection->fetchAllAssociative(
            'SELECT `id`, `value` FROM `rule_condition` WHERE `type` = :type',
            ['type' => 'cartLineItemProductType']
        );

        if ($conditions === []) {
            return;
        }

        $migrated = false;

        foreach ($conditions as $condition) {
            $newValue = $this->reverseConditionPayload($condition['value']);

            if ($newValue === null) {
                continue;
            }

            $connection->update(
                'rule_condition',
                [
                    'type' => 'cartLineItemProductStates',
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

    public function updateDestructive(Connection $connection): void
    {
    }

    private function reverseConditionPayload(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        if (!\is_array($decoded)) {
            return null;
        }

        $productType = $decoded['productType'] ?? null;

        if (!\is_string($productType) || !isset(self::PRODUCT_TYPE_TO_LEGACY_STATE_MAP[$productType])) {
            return null;
        }

        $operator = \is_string($decoded['operator'] ?? null) ? $decoded['operator'] : '=';
        $productState = self::PRODUCT_TYPE_TO_LEGACY_STATE_MAP[$productType];

        return json_encode(['operator' => $operator, 'productState' => $productState], \JSON_THROW_ON_ERROR);
    }
}
