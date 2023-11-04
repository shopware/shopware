<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Content\Flow\Dispatching\Struct\Flow;
use Shopware\Core\Content\Flow\Dispatching\Struct\Sequence;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;

/**
 * @internal not intended for decoration or replacement
 */
#[Package('business-ops')]
class FlowBuilder
{
    public function build(string $id, array $flowSequences): Flow
    {
        $flowSequences = $this->buildHierarchyTree($flowSequences);

        $flatBag = new ArrayStruct();

        $sequences = [];
        foreach ($flowSequences as $flowSequence) {
            if ($flowSequence['sequence_id'] === null) {
                continue;
            }

            $sequences[] = $this->createNestedSequence($flowSequence, [], $flatBag);
        }

        /** @var array<string, Sequence> $flat */
        $flat = $flatBag->all();

        return new Flow($id, $sequences, $flat);
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

    /**
     * @param ArrayStruct<string, mixed> $flatBag
     */
    private function createNestedSequence(array $sequence, array $siblings, ArrayStruct $flatBag): Sequence
    {
        if ($sequence['action_name'] !== null) {
            $object = $this->createNestedAction($sequence, $siblings, $flatBag);
        } else {
            $object = $this->createNestedIf($sequence, $flatBag);
        }

        $flatBag->set($sequence['sequence_id'], $object);

        return $object;
    }

    /**
     * @param ArrayStruct<string, mixed> $flagBag
     */
    private function createNestedAction(array $currentSequence, array $siblingSequences, ArrayStruct $flagBag): Sequence
    {
        $config = $currentSequence['config'] ? json_decode((string) $currentSequence['config'], true, 512, \JSON_THROW_ON_ERROR) : [];

        $children = $currentSequence['children'];
        if (!empty($children)) {
            $firstChildren = array_shift($children);

            return Sequence::createAction(
                $currentSequence['action_name'],
                $this->createNestedSequence($firstChildren, $children, $flagBag),
                $currentSequence['flow_id'],
                $currentSequence['sequence_id'],
                $config
            );
        }

        if (empty($siblingSequences)) {
            return Sequence::createAction(
                $currentSequence['action_name'],
                null,
                $currentSequence['flow_id'],
                $currentSequence['sequence_id'],
                $config,
                $currentSequence['app_flow_action_id']
            );
        }

        $nextSequence = array_shift($siblingSequences);

        return Sequence::createAction(
            $currentSequence['action_name'],
            $this->createNestedAction($nextSequence, $siblingSequences, $flagBag),
            $currentSequence['flow_id'],
            $currentSequence['sequence_id'],
            $config,
            $currentSequence['app_flow_action_id']
        );
    }

    /**
     * @param ArrayStruct<string, mixed> $flagBag
     */
    private function createNestedIf(array $currentSequence, ArrayStruct $flagBag): Sequence
    {
        $sequenceChildren = $currentSequence['children'];
        if (!$sequenceChildren) {
            // a dummy if with no false and true case
            return Sequence::createIF($currentSequence['rule_id'], $currentSequence['flow_id'], $currentSequence['sequence_id'], null, null);
        }

        $trueCases = array_filter($sequenceChildren, fn (array $sequence) => (bool) $sequence['true_case'] === true);

        $falseCases = array_filter($sequenceChildren, fn (array $sequence) => (bool) $sequence['true_case'] === false);

        $trueCaseSequence = null;
        if (!empty($trueCases)) {
            $trueCase = array_shift($trueCases);

            $trueCaseSequence = $this->createNestedSequence($trueCase, $trueCases, $flagBag);
        }

        $falseCaseSequence = null;
        if (!empty($falseCases)) {
            $falseCase = array_shift($falseCases);

            $falseCaseSequence = $this->createNestedSequence($falseCase, $falseCases, $flagBag);
        }

        return Sequence::createIF($currentSequence['rule_id'], $currentSequence['flow_id'], $currentSequence['sequence_id'], $trueCaseSequence, $falseCaseSequence);
    }
}
