<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1785810496ConvertLineItemProductStatesRuleCondition extends MigrationStep
{
    private const LEGACY_PRODUCT_STATE_TO_TYPE_MAP = [
        'is-download' => 'digital',
        'is-physical' => 'physical',
    ];

    public function getCreationTimestamp(): int
    {
        return 1785810496;
    }

    public function update(Connection $connection): void
    {
        // \Shopware\Core\Migration\V6_7\Migration1773829000MigrateLineItemProductStatesRuleCondition had to defer this
        // conversion to updateDestructive(), because converting cartLineItemProductStates during the 6.7 update
        // breaks 6.6 -> 6.7 blue-green deployments: 6.6 cannot evaluate cartLineItemProductType.
        //
        // The regular safe destructive window does not guarantee that the V6_7 destructive migration ran before shops
        // reach 6.8, so this V6_8 update migration performs the recovery conversion. It is safe here because 6.7, the
        // previous runtime for 6.7 -> 6.8 blue-green deployments, already supports cartLineItemProductType.

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
