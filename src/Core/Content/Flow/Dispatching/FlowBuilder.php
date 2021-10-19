<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Content\Flow\Dispatching\Struct\Flow;
use Shopware\Core\Content\Flow\Dispatching\Struct\Sequence;

/**
 * @internal not intended for decoration or replacement
 */
class FlowBuilder
{
    public function build(string $id, array $flowSequences): Flow
    {
        $flowSequences = $this->buildHierarchyTree($flowSequences);

        $sequences = [];
        foreach ($flowSequences as $flowSequence) {
            if ($flowSequence['sequence_id'] === null) {
                continue;
            }

            $sequences[] = $this->createNestedSequence($flowSequence, []);
        }

        return new Flow($id, $sequences);
    }

    private function buildHierarchyTree(array $flowSequences, ?string $parentId = null): array
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
            $child['children'] = $this->buildHierarchyTree($flowSequences, $child['sequence_id']);
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
            return Sequence::createAction($currentSequence['action_name'], null, $currentSequence['flow_id'], $currentSequence['sequence_id'], $config);
        }

        $nextSequence = array_shift($siblingSequences);

        return Sequence::createAction(
            $currentSequence['action_name'],
            $this->createNestedAction($nextSequence, $siblingSequences),
            $currentSequence['flow_id'],
            $currentSequence['sequence_id'],
            $config
        );
    }

    private function createNestedIf(array $currentSequence): Sequence
    {
        $sequenceChildren = $currentSequence['children'];
        if (!$sequenceChildren) {
            // a dummy if with no false and true case
            return Sequence::createIF($currentSequence['rule_id'], $currentSequence['flow_id'], $currentSequence['sequence_id'], null, null);
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

        return Sequence::createIF($currentSequence['rule_id'], $currentSequence['flow_id'], $currentSequence['sequence_id'], $trueCaseSequence, $falseCaseSequence);
    }
}
