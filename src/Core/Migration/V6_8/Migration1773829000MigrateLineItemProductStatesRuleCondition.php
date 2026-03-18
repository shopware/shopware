<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

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
        $conditions = $connection->fetchAllAssociative(
            'SELECT `id`, `rule_id`, `value` FROM `rule_condition` WHERE `type` = :legacyType',
            ['legacyType' => 'cartLineItemProductStates']
        );

        if ($conditions === []) {
            return;
        }

        foreach ($conditions as $condition) {
            $newValue = $this->conditionPayload($condition['value']);

            $connection->update(
                'rule_condition',
                [
                    'type' => 'cartLineItemProductType',
                    'value' => $newValue,
                ],
                ['id' => $condition['id']]
            );
        }

        $this->registerIndexer($connection, 'rule.indexer');
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function conditionPayload(mixed $value): string
    {
        $defaultOperator = '=';
        $defaultType = 'physical';

        if (!\is_string($value)) {
            return json_encode(['operator' => $defaultOperator, 'productType' => $defaultType], \JSON_THROW_ON_ERROR);
        }

        $decoded = json_decode($value, true);

        if (!\is_array($decoded)) {
            return json_encode(['operator' => $defaultOperator, 'productType' => $defaultType], \JSON_THROW_ON_ERROR);
        }

        $operator = \is_string($decoded['operator'] ?? null) ? $decoded['operator'] : $defaultOperator;
        $productState = $decoded['productState'] ?? null;
        $productType = $defaultType;

        if (\is_string($productState) && isset(self::LEGACY_PRODUCT_STATE_TO_TYPE_MAP[$productState])) {
            $productType = self::LEGACY_PRODUCT_STATE_TO_TYPE_MAP[$productState];
        }

        return json_encode(['operator' => $operator, 'productType' => $productType], \JSON_THROW_ON_ERROR);
    }
}
