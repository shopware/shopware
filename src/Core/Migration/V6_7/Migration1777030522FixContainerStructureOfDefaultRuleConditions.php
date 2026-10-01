<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Checkout\Cart\Rule\CartAmountRule;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1777030522FixContainerStructureOfDefaultRuleConditions extends MigrationStep
{
    private const ALWAYS_VALID_RULE_NAME = 'Always valid (Default)';

    private const CART_FILLED_RULE_NAME = 'Cart >= 0';

    private const DEFAULT_RULE_PRIORITY = 100;

    private const LEGACY_DEFAULT_RULE_NAMES = [
        self::ALWAYS_VALID_RULE_NAME,
        self::CART_FILLED_RULE_NAME,
    ];

    public function getCreationTimestamp(): int
    {
        return 1777030522;
    }

    public function update(Connection $connection): void
    {
        /*
         * Old installations stored these rules with a bare root condition instead of the
         * orContainer > andContainer > condition tree the rule builder now produces. Only exact
         * historical defaults with an unwrapped single condition are migrated; same name customized
         * or already structured rules are intentionally skipped.
         */
        $rulesToWrap = $this->fetchLegacyDefaultRules($connection);

        if ($rulesToWrap === []) {
            return;
        }

        $createdAt = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $connection->transactional(function (Connection $connection) use ($rulesToWrap, $createdAt): void {
            foreach ($rulesToWrap as $rule) {
                $this->wrapRootCondition($connection, $rule, $createdAt);
            }

            $this->resetRulePayloads($connection, array_column($rulesToWrap, 'rule_id'));
            $this->registerIndexer($connection, 'rule.indexer');
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchLegacyDefaultRules(Connection $connection): array
    {
        $rules = $connection->fetchAllAssociative(
            'SELECT `rule`.`id` AS `rule_id`,
                    `rule`.`name`,
                    `rule`.`priority`,
                    root_condition.`id` AS `root_condition_id`,
                    root_condition.`type`,
                    root_condition.`value`,
                    (
                        SELECT COUNT(*)
                        FROM `rule_condition` condition_count
                        WHERE condition_count.`rule_id` = `rule`.`id`
                    ) AS `condition_count`,
                    (
                        SELECT COUNT(*)
                        FROM `rule_condition` root_count
                        WHERE root_count.`rule_id` = `rule`.`id`
                        AND root_count.`parent_id` IS NULL
                    ) AS `root_count`,
                    (
                        SELECT COUNT(*)
                        FROM `rule_condition` child_count
                        WHERE child_count.`parent_id` = root_condition.`id`
                    ) AS `child_count`
             FROM `rule`
             INNER JOIN `rule_condition` root_condition
                ON root_condition.`rule_id` = `rule`.`id`
                AND root_condition.`parent_id` IS NULL
                AND root_condition.`type` != :orContainerType
             WHERE `rule`.`name` IN (:ruleNames)
             AND `rule`.`invalid` = 0',
            [
                'orContainerType' => OrRule::RULE_NAME,
                'ruleNames' => self::LEGACY_DEFAULT_RULE_NAMES,
            ],
            ['ruleNames' => ArrayParameterType::STRING]
        );

        return array_values(array_filter($rules, $this->isLegacyDefaultRule(...)));
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function wrapRootCondition(Connection $connection, array $rule, string $createdAt): void
    {
        $ruleId = (string) $rule['rule_id'];
        $rootConditionId = (string) $rule['root_condition_id'];

        $orContainerId = $this->insertContainer($connection, OrRule::RULE_NAME, $ruleId, null, $createdAt);
        $andContainerId = $this->insertContainer($connection, AndRule::RULE_NAME, $ruleId, $orContainerId, $createdAt);

        /*
         * Reparent the bare root condition under the new andContainer. This keeps the rule logically
         * equivalent while matching the structure the rule builder produces.
         */
        $connection->executeStatement(
            'UPDATE `rule_condition`
             SET `parent_id` = :parentId, `position` = 0
             WHERE `id` = :rootConditionId',
            [
                'parentId' => $andContainerId,
                'rootConditionId' => $rootConditionId,
            ]
        );
    }

    private function insertContainer(Connection $connection, string $type, string $ruleId, ?string $parentId, string $createdAt): string
    {
        $id = Uuid::randomBytes();

        $connection->insert('rule_condition', [
            'id' => $id,
            'type' => $type,
            'rule_id' => $ruleId,
            'parent_id' => $parentId,
            'value' => null,
            'position' => 0,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        return $id;
    }

    /**
     * @param list<mixed> $ruleIds
     */
    private function resetRulePayloads(Connection $connection, array $ruleIds): void
    {
        $connection->executeStatement(
            'UPDATE `rule` SET `payload` = NULL WHERE `id` IN (:ruleIds)',
            ['ruleIds' => $ruleIds],
            ['ruleIds' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function isLegacyDefaultRule(array $rule): bool
    {
        if (!\is_string($rule['type'])) {
            return false;
        }

        if ((int) $rule['condition_count'] !== 1 || (int) $rule['root_count'] !== 1 || (int) $rule['child_count'] !== 0) {
            return false;
        }

        return $this->isLegacyCartFilledRule($rule) || $this->isLegacyAlwaysValidRule($rule);
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function isLegacyCartFilledRule(array $rule): bool
    {
        if ($rule['name'] !== self::CART_FILLED_RULE_NAME) {
            return false;
        }

        if ((int) $rule['priority'] !== self::DEFAULT_RULE_PRIORITY) {
            return false;
        }

        if ($rule['type'] !== CartAmountRule::RULE_NAME) {
            return false;
        }

        return $this->matchesNullableValue($rule['value'], ['operator' => '>=', 'amount' => 0]);
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function isLegacyAlwaysValidRule(array $rule): bool
    {
        if ($rule['name'] !== self::ALWAYS_VALID_RULE_NAME) {
            return false;
        }

        if ((int) $rule['priority'] !== self::DEFAULT_RULE_PRIORITY) {
            return false;
        }

        if ($rule['type'] !== AlwaysValidRule::RULE_NAME) {
            return false;
        }

        if ($rule['value'] === null) {
            return true;
        }

        return $this->matchesNullableValue($rule['value'], ['isAlwaysValid' => true]);
    }

    /**
     * @param array<mixed> $expected
     */
    private function matchesNullableValue(mixed $value, array $expected): bool
    {
        if (!\is_string($value)) {
            return false;
        }

        $value = json_decode($value, true);

        if (!\is_array($value)) {
            return false;
        }

        ksort($value);
        ksort($expected);

        return $value === $expected;
    }
}
