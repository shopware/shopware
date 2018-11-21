<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Struct\Uuid;

class Migration1542891837RuleSerialization extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1542891837;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery(
            '
            CREATE TABLE `rule_condition` ( 
              `id` binary(16) NOT NULL,
              `type` varchar(256) NOT NULL,
              `rule_id` binary(16) NOT NULL,
              `parent_id` binary(16) NULL,
              `value` longtext NULL,
              PRIMARY KEY (`id`),
              CHECK (JSON_VALID (`value`)),
              CONSTRAINT `fk_condition_rule.rule_id` FOREIGN KEY (`rule_id`) REFERENCES `rule` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk_condition_rule.parent_id` FOREIGN KEY (`parent_id`) REFERENCES rule_condition (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        '
        );

        $connection->executeQuery('ALTER TABLE `rule` MODIFY `payload` LONGTEXT NULL');

        $this->updateRules($connection);
    }

    public function updateRules(Connection $connection): void
    {
        $rules = $connection->fetchAll('SELECT id, payload from `rule`');

        foreach ($rules as $rule) {
            $this->updateRule($connection, $rule['id'], null, json_decode($rule['payload'], true));
        }
    }

    public function updateRule(
        Connection $connection,
        string $ruleId,
        ?string $parentId,
        array $payload
    ): void {
        $type = $payload['_class'];
        $conditionId = Uuid::uuid4()->getBytes();
        $value = $payload;
        unset($value['_class'], $value['operator'], $value['extensions'], $value['type'], $value['rules']);

        $data = [
            'id' => $conditionId,
            'parent_id' => $parentId,
            'rule_id' => $ruleId,
            'type' => $type,
        ];

        if ($value) {
            $data['value'] = json_encode($value);
        }

        $connection->insert('rule_condition', $data);

        if ($this->isContainer($payload)) {
            foreach ($payload['rules'] as $rule) {
                $this->updateRule($connection, $ruleId, $conditionId, $rule);
            }
        }
    }

    public function isContainer(array $payload): bool
    {
        return array_key_exists('rules', $payload) && !empty($payload['rules']);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}
