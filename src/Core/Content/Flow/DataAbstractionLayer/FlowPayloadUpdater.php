<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Flow\SequenceTree\Sequence;
use Shopware\Core\Content\Flow\SequenceTree\SequenceTree;
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
            $flowSequences = $this->buildTree($flowSequences);

            $sequences = [];
            foreach ($flowSequences as $flowSequence) {
                $sequences[] = $this->createNestedSequence($flowSequence, []);
            }

            $serialized = serialize(new SequenceTree($sequences));

            $update->execute([
                'id' => Uuid::fromHexToBytes($flowId),
                'payload' => $serialized,
            ]);
        }
    }

    private function buildTree(array $flowSequences, ?string $parentId = null): array
    {
        $children = [];

        foreach ($flowSequences as $key => $flowSequence) {
            if ($flowSequence['parent_id'] !== $parentId) {
                continue;
            }

            $children[] = $flowSequence;

            unset($flowSequences[$key]);
        }

        $items = [];

        foreach ($children as $child) {
            $child['children'] = $this->buildTree($flowSequences, $child['id']);
            $items[] = $child;
        }

        return $items;
    }

    private function createNestedSequence(array $sequence, array $siblings): Sequence
    {
        if ($sequence['action_name'] !== null) {
            return $this->createNestedAction($sequence, $siblings);
        }

        return $this->createNestedIf($sequence);
    }

    private function createNestedAction(array $currentSequence, array $siblingSequences): Sequence
    {
        $config = $currentSequence['config'] ? json_decode($currentSequence['config'], true) : [];
        if (empty($siblingSequences)) {
            return Sequence::createAction($currentSequence['action_name'], null, $config);
        }

        $nextSequence = array_shift($siblingSequences);

        return Sequence::createAction(
            $currentSequence['action_name'],
            $this->createNestedAction($nextSequence, $siblingSequences),
            $config
        );
    }

    private function createNestedIf(array $currentSequence): Sequence
    {
        $sequenceChildren = $currentSequence['children'];
        if (!$sequenceChildren) {
            // a dummy if with no false and true case
            return Sequence::createIF($currentSequence['rule_id'], null, null);
        }

        $trueCases = array_filter($sequenceChildren, function (array $sequence) {
            return (bool) $sequence['true_case'] === true;
        });

        $falseCases = array_filter($sequenceChildren, function (array $sequence) {
            return (bool) $sequence['true_case'] === false;
        });

        $trueCaseSequence = null;
        if (!empty($trueCases)) {
            $trueCase = array_shift($trueCases);

            $trueCaseSequence = $this->createNestedSequence($trueCase, $trueCases);
        }

        $falseCaseSequence = null;
        if (!empty($falseCases)) {
            $falseCase = array_shift($falseCases);

            $falseCaseSequence = $this->createNestedSequence($falseCase, $falseCases);
        }

        return Sequence::createIF($currentSequence['rule_id'], $trueCaseSequence, $falseCaseSequence);
    }
}
