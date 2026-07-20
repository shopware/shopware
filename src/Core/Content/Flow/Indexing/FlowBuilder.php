<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Indexing;

use Shopware\Core\Content\Flow\Dispatching\Struct\Flow;
use Shopware\Core\Content\Flow\Dispatching\Struct\Sequence;
use Shopware\Core\Content\Flow\FlowException;
use Shopware\Core\Content\Flow\Indexing\FlowBuilder\Sequence as SequenceDto;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;

/**
 * @internal not intended for decoration or replacement
 */
#[Package('after-sales')]
class FlowBuilder
{
    /**
     * @param list<SequenceDto> $flowSequences
     */
    public function build(string $id, array $flowSequences): Flow
    {
        $flowSequences = $this->buildHierarchyTree($flowSequences);

        $flatBag = new ArrayStruct();

        $sequences = [];
        foreach ($flowSequences as $flowSequence) {
            if ($flowSequence->sequenceId === null) {
                continue;
            }

            $sequences[] = $this->createNestedSequence($flowSequence, [], $flatBag);
        }

        $flat = $flatBag->all();

        return new Flow($id, $sequences, $flat);
    }

    /**
     * @param array<int, SequenceDto> $flowSequences
     *
     * @return list<SequenceDto>
     */
    private function buildHierarchyTree(array $flowSequences, ?string $parentId = null): array
    {
        $children = [];
        foreach ($flowSequences as $key => $flowSequence) {
            if ($flowSequence->parentId !== $parentId) {
                continue;
            }

            $children[] = $flowSequence;

            unset($flowSequences[$key]);
        }

        $items = [];
        foreach ($children as $child) {
            $child->children = $this->buildHierarchyTree($flowSequences, $child->sequenceId);
            $items[] = $child;
        }

        return $items;
    }

    /**
     * @param list<SequenceDto> $siblings
     */
    private function createNestedSequence(SequenceDto $sequence, array $siblings, ArrayStruct $flatBag): Sequence
    {
        \assert($sequence->sequenceId !== null); // Sequences without ID will not be handled. See `build` method

        if ($sequence->actionName !== null) {
            $object = $this->createNestedAction($sequence, $siblings, $flatBag);
        } else {
            $object = $this->createNestedIf($sequence, $flatBag);
        }

        $flatBag->set($sequence->sequenceId, $object);

        return $object;
    }

    /**
     * @param list<SequenceDto> $siblingSequences
     */
    private function createNestedAction(SequenceDto $currentSequence, array $siblingSequences, ArrayStruct $flagBag): Sequence
    {
        \assert($currentSequence->sequenceId !== null); // Sequences without ID will not be handled. See `build` method
        if ($currentSequence->actionName === null) {
            throw FlowException::missingRequiredSequenceField('action');
        }

        $children = $currentSequence->children;
        if ($children !== []) {
            $firstChildren = array_shift($children);

            return Sequence::createAction(
                $currentSequence->actionName,
                $this->createNestedSequence($firstChildren, $children, $flagBag),
                $currentSequence->flowId,
                $currentSequence->sequenceId,
                $currentSequence->config,
            );
        }

        if ($siblingSequences === []) {
            return Sequence::createAction(
                $currentSequence->actionName,
                null,
                $currentSequence->flowId,
                $currentSequence->sequenceId,
                $currentSequence->config,
                $currentSequence->appFlowActionId,
            );
        }

        $nextSequence = array_shift($siblingSequences);

        return Sequence::createAction(
            $currentSequence->actionName,
            $this->createNestedAction($nextSequence, $siblingSequences, $flagBag),
            $currentSequence->flowId,
            $currentSequence->sequenceId,
            $currentSequence->config,
            $currentSequence->appFlowActionId,
        );
    }

    private function createNestedIf(SequenceDto $currentSequence, ArrayStruct $flagBag): Sequence
    {
        \assert($currentSequence->sequenceId !== null); // Sequences without ID will not be handled. See `build` method
        if ($currentSequence->ruleId === null) {
            throw FlowException::missingRequiredSequenceField('ruleId');
        }

        $sequenceChildren = $currentSequence->children;
        if ($sequenceChildren === []) {
            // a dummy if with no false and true case
            return Sequence::createIF($currentSequence->ruleId, $currentSequence->flowId, $currentSequence->sequenceId, null, null);
        }

        $trueCases = array_values(array_filter($sequenceChildren, static fn (SequenceDto $sequence): bool => $sequence->trueCase === true));
        $falseCases = array_values(array_filter($sequenceChildren, static fn (SequenceDto $sequence): bool => $sequence->trueCase === false));

        $trueCaseSequence = null;
        if ($trueCases !== []) {
            $trueCase = array_shift($trueCases);

            $trueCaseSequence = $this->createNestedSequence($trueCase, $trueCases, $flagBag);
        }

        $falseCaseSequence = null;
        if ($falseCases !== []) {
            $falseCase = array_shift($falseCases);

            $falseCaseSequence = $this->createNestedSequence($falseCase, $falseCases, $flagBag);
        }

        return Sequence::createIF($currentSequence->ruleId, $currentSequence->flowId, $currentSequence->sequenceId, $trueCaseSequence, $falseCaseSequence);
    }
}
