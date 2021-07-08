<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Flow\SequenceTree\SequenceTreeBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal (FEATURE_NEXT_8225)
 */
class FlowPayloadUpdater
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function update(array $ids): void
    {
        $listFlowSequence = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`flow_sequence`.`flow_id`)) as array_key,
            LOWER(HEX(`flow_sequence`.`id`)) as `id`,
            LOWER(HEX(`flow_sequence`.`parent_id`)) as `parent_id`,
            LOWER(HEX(`flow_sequence`.`rule_id`)) as `rule_id`,
            `flow_sequence`.`action_name` as `action_name`,
            `flow_sequence`.`config` as `config`,
            `flow_sequence`.`true_case` as `true_case`
            FROM `flow_sequence`
            LEFT JOIN `flow` ON `flow`.`id` = `flow_sequence`.`flow_id`
            WHERE `flow`.`active` = 1
                AND (`flow_sequence`.`rule_id` IS NOT NULL OR `flow_sequence`.`action_name` IS NOT NULL)
                AND `flow_sequence`.`flow_id` IN (:ids)
            ORDER BY `parent_id`, `true_case`, `position`',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $listFlowSequence = FetchModeHelper::group($listFlowSequence);

        $update = new RetryableQuery(
            $this->connection->prepare('UPDATE `flow` SET payload = :payload WHERE `id` = :id')
        );

        foreach ($listFlowSequence as $flowId => $flowSequences) {
            $serialized = serialize(SequenceTreeBuilder::build($flowSequences));

            $update->execute([
                'id' => Uuid::fromHexToBytes($flowId),
                'payload' => $serialized,
            ]);
        }
    }
}
