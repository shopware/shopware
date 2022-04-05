<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1648803451FixInvalidMigrationOfBusinessEventToFlow extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1648803451;
    }

    public function update(Connection $connection): void
    {
        $invalidFlows = $connection->fetchAllAssociative(
            'SELECT DISTINCT HEX(`flow`.`id`) as id
            FROM `event_action_rule`
                JOIN `event_action`
                    ON `event_action_rule`.`event_action_id` = `event_action`.`id`
                JOIN `flow`
                    ON `event_action`.`migrated_flow_id` = `flow`.`id`
            WHERE `flow`.`updated_at` IS NULL;'
        );

        foreach ($invalidFlows as $invalidFlow) {
            $flowSequences = $connection->fetchAllAssociative(
                'SELECT * FROM `flow_sequence` WHERE HEX(`flow_id`) = :flowId',
                [
                    'flowId' => $invalidFlow['id'],
                ]
            );
            $action = array_values(array_filter($flowSequences, function ($sequence) {
                return $sequence['action_name'] !== null;
            }));

            $parentCondition = array_values(array_filter($flowSequences, function ($sequence) {
                return $sequence['rule_id'] !== null && $sequence['parent_id'] === null;
            }));

            $parentId = $parentCondition[0]['id'];

            $saleChannelRule = $connection->fetchOne(
                'SELECT 1 FROM `sales_channel_rule` WHERE `rule_id` = :ruleId',
                [
                    'ruleId' => $parentCondition[0]['rule_id'],
                ]
            );

            $trueCase = true;
            if (!$saleChannelRule) {
                $connection->executeStatement(
                    'UPDATE `flow_sequence` SET `parent_id` = :parentId, `true_case` = :trueCase WHERE `id` = :id',
                    [
                        'parentId' => $parentId,
                        'trueCase' => $trueCase,
                        'id' => $action[0]['id'],
                    ]
                );
                $trueCase = false;
            } else {
                $connection->executeStatement(
                    'DELETE FROM `flow_sequence` WHERE `id` = :id',
                    [
                        'id' => $action[0]['id'],
                    ]
                );
            }

            $childrenCondition = array_values(array_filter($flowSequences, function ($sequence) {
                return $sequence['rule_id'] !== null && $sequence['parent_id'] !== null;
            }));

            foreach ($childrenCondition as $child) {
                $connection->executeStatement(
                    'UPDATE `flow_sequence` SET `parent_id` = :parentId, `true_case` = :trueCase WHERE `id` = :id',
                    [
                        'parentId' => $parentId,
                        'trueCase' => $trueCase,
                        'id' => $child['id'],
                    ]
                );

                $parentId = $child['id'];
                $trueCase = false;

                $connection->executeStatement(
                    'INSERT INTO `flow_sequence` (id, flow_id, parent_id, rule_id, action_name, config, position, display_group, true_case, custom_fields, created_at, updated_at)
                    VALUES (:id, :flow_id, :parent_id, :rule_id, :action_name, :config, :position, :display_group, :true_case, :custom_fields, :created_at, :updated_at)',
                    [
                        'id' => Uuid::randomBytes(),
                        'flow_id' => $action[0]['flow_id'],
                        'parent_id' => $parentId,
                        'rule_id' => $action[0]['rule_id'],
                        'action_name' => $action[0]['action_name'],
                        'config' => $action[0]['config'],
                        'position' => $action[0]['position'],
                        'display_group' => $action[0]['display_group'],
                        'true_case' => 1,
                        'custom_fields' => $action[0]['custom_fields'],
                        'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                        'updated_at' => null,
                    ]
                );
            }
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
