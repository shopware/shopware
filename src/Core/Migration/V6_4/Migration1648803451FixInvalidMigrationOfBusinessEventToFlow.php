<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @phpstan-type SequenceData array{id: string, parent_id: string|null, true_case: int|null, flow_id: string|null, rule_id: string|null, action_name: string|null, position: int, created_at: string|null, config: string|null}
 */
#[Package('core')]
class Migration1648803451FixInvalidMigrationOfBusinessEventToFlow extends MigrationStep
{
    /**
     * @var list<SequenceData>
     */
    private array $sequenceActions = [];

    /**
     * @var list<SequenceData>
     */
    private array $sequenceDelete = [];

    /**
     * @var list<SequenceData>
     */
    private array $sequenceUpdate = [];

    public function getCreationTimestamp(): int
    {
        return 1648803451;
    }

    public function update(Connection $connection): void
    {
        $invalidSequence = $connection->fetchAllAssociative(
            'SELECT DISTINCT HEX(`flow`.`id`),
                `flow_sequence`.`id`,
                `flow_sequence`.`flow_id`,
                `flow_sequence`.`parent_id`,
                `flow_sequence`.`true_case`,
                `flow_sequence`.`rule_id`,
                `flow_sequence`.`parent_id`,
                `flow_sequence`.`action_name`,
                `flow_sequence`.`config`,
                `flow_sequence`.`position`,
                `flow_sequence`.`created_at`
            FROM `event_action_rule`
                JOIN `event_action`
                    ON `event_action_rule`.`event_action_id` = `event_action`.`id`
                JOIN `flow`
                    ON `event_action`.`migrated_flow_id` = `flow`.`id`
                JOIN `flow_sequence`
                    ON `flow`.`id` = `flow_sequence`.`flow_id`
            WHERE `flow`.`updated_at` IS NULL;'
        );

        $invalidSequenceGroup = FetchModeHelper::group($invalidSequence);

        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $saleChannelRule = array_column($connection->fetchAllAssociative('SELECT `rule_id` FROM `sales_channel_rule`'), 'rule_id');

        foreach ($invalidSequenceGroup as $sequence) {
            $actionSequence = array_values(array_filter($sequence, fn ($sequence) => $sequence['action_name'] !== null))[0] ?? null;

            $parentCondition = array_values(array_filter($sequence, fn ($sequence) => $sequence['rule_id'] !== null && $sequence['parent_id'] === null))[0] ?? null;

            if ($actionSequence === null || $parentCondition === null) {
                continue;
            }

            $parentId = $parentCondition['id'];

            $hasSaleChannelRule = \in_array($parentCondition['rule_id'], $saleChannelRule, true);

            $trueCase = 1;
            if (!$hasSaleChannelRule) {
                $this->sequenceUpdate[] = $this->buildSequenceData(
                    $actionSequence['id'],
                    $parentId,
                    $trueCase,
                );

                $trueCase = 0;
            } else {
                $this->sequenceDelete[] = $this->buildSequenceData(
                    $actionSequence['id']
                );
            }

            $childrenCondition = array_values(array_filter($sequence, fn ($sequence) => $sequence['rule_id'] !== null && $sequence['parent_id'] !== null));

            foreach ($childrenCondition as $child) {
                $this->sequenceUpdate[] = $this->buildSequenceData(
                    $child['id'],
                    $parentId,
                    $trueCase,
                );

                $parentId = $child['id'];
                $trueCase = 0;

                $this->sequenceActions[] = $this->buildSequenceData(
                    Uuid::randomBytes(),
                    $parentId,
                    1,
                    $actionSequence['flow_id'],
                    $actionSequence['rule_id'],
                    $actionSequence['action_name'],
                    $createdAt,
                    $actionSequence['config'],
                );
            }
        }

        $queue = new MultiInsertQueryQueue($connection);

        foreach ($this->sequenceActions as $data) {
            $queue->addInsert(FlowSequenceDefinition::ENTITY_NAME, $data);
        }

        $queue->execute();

        foreach ($this->sequenceUpdate as $sequence) {
            $connection->executeStatement(
                'UPDATE `flow_sequence` SET `parent_id` = :parentId, `true_case` = :trueCase WHERE `id` = :id',
                [
                    'parentId' => $sequence['parent_id'],
                    'trueCase' => $sequence['true_case'],
                    'id' => $sequence['id'],
                ]
            );
        }

        if (empty($this->sequenceDelete)) {
            return;
        }

        $connection->executeStatement(
            'DELETE FROM `flow_sequence` WHERE `id` IN (:ids)',
            [
                'ids' => array_column($this->sequenceDelete, 'id'),
            ],
            [
                'ids' => ArrayParameterType::STRING,
            ]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    /**
     * @return SequenceData
     */
    private function buildSequenceData(
        string $id,
        ?string $parentId = null,
        ?int $trueCase = null,
        ?string $flowId = null,
        ?string $ruleId = null,
        ?string $actionName = null,
        ?string $createdAt = null,
        ?string $config = null
    ): array {
        return [
            'id' => $id,
            'parent_id' => $parentId,
            'true_case' => $trueCase,
            'flow_id' => $flowId,
            'rule_id' => $ruleId,
            'action_name' => $actionName,
            'position' => 1,
            'created_at' => $createdAt,
            'config' => $config,
        ];
    }
}
