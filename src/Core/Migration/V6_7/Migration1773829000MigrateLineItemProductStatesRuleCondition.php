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

    public function updateDestructive(Connection $connection): void
    {
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
