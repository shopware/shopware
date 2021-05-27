<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow;

use Shopware\Core\Content\Flow\Action\FlowAction;
use Shopware\Core\Content\Flow\FlowSequence\FlowSequenceCollection;
use Shopware\Core\Content\Flow\FlowSequence\FlowSequenceEntity;
use Shopware\Core\Framework\Event\BusinessEvent;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal (FEATURE_NEXT_8225)
 */
class FlowExecutor
{
    private EventDispatcherInterface $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function execute(FlowEntity $flow, BusinessEventInterface $event): void
    {
        $sequences = $flow->getFlowSequences();

        if ($sequences === null) {
            return;
        }

        $flowSequences = $this->buildTree($sequences);

        $state = new FlowState($event);
        foreach ($flowSequences as $flowSequence) {
            $sequence = $this->createNestedSequence($flowSequence, new FlowSequenceCollection());

            $this->executeSequence($sequence, $state);

            if ($state->stop) {
                return;
            }
        }
    }

    public function executeSequence(?Sequence $sequence, FlowState $state): void
    {
        if ($sequence === null) {
            return;
        }

        if ($sequence->isIf()) {
            $this->executeIf($sequence, $state);

            return;
        }

        $this->executeAction($sequence, $state);
    }

    public function executeAction(Sequence $sequence, FlowState $state): void
    {
        $actionName = $sequence->action;
        if (!$actionName) {
            return;
        }

        if ($actionName === FlowAction::STOP_FLOW) {
            $state->stop = true;

            return;
        }

        $globalEvent = new BusinessEvent($actionName, $state->event, $sequence->config);
        $this->dispatcher->dispatch($globalEvent, $actionName);

        $nextAction = $sequence->nextAction;
        if ($nextAction !== null) {
            $this->executeAction($nextAction, $state);
        }
    }

    public function executeIf(Sequence $sequence, FlowState $state): void
    {
        if (\in_array($sequence->ruleId, $state->event->getContext()->getRuleIds(), true)) {
            $this->executeSequence($sequence->trueCase, $state);

            return;
        }

        $this->executeSequence($sequence->falseCase, $state);
    }

    private function createNestedSequence(FlowSequenceEntity $sequence, FlowSequenceCollection $siblings): Sequence
    {
        $actionName = $sequence->getActionName();
        if ($actionName !== null) {
            return $this->createNestedAction($actionName, $sequence, $siblings);
        }

        /** @var string $ruleId */
        $ruleId = $sequence->getRuleId();

        return $this->createNestedIf($ruleId, $sequence);
    }

    private function createNestedAction(string $actionName, FlowSequenceEntity $currentSequence, FlowSequenceCollection $siblingSequences): Sequence
    {
        $nextSequence = $siblingSequences->first();
        if (!$nextSequence) {
            return Sequence::createAction($actionName, null, $currentSequence->getConfig());
        }

        $siblingSequences = $siblingSequences->filter(function (FlowSequenceEntity $flowSequenceEntity) use ($nextSequence) {
            return $flowSequenceEntity->getId() !== $nextSequence->getId();
        });

        /** @var string $nextActionName */
        $nextActionName = $nextSequence->getActionName();

        return Sequence::createAction(
            $actionName,
            $this->createNestedAction($nextActionName, $nextSequence, $siblingSequences),
            $currentSequence->getConfig()
        );
    }

    private function createNestedIf(string $ruleId, FlowSequenceEntity $currentSequence): Sequence
    {
        $sequenceChildren = $currentSequence->getChildren();
        if (!$sequenceChildren) {
            // a dummy if with no false and true case
            return Sequence::createIF($ruleId, null, null);
        }

        /** @var FlowSequenceCollection $trueCases */
        $trueCases = $sequenceChildren->filterByProperty('trueCase', true);
        /** @var FlowSequenceCollection $falseCases */
        $falseCases = $sequenceChildren->filterByProperty('trueCase', false);
        $trueCase = $trueCases->first();
        $falseCase = $falseCases->first();

        $trueCaseSequence = null;
        if ($trueCase) {
            $siblingTrueCases = $trueCases->filter(function (FlowSequenceEntity $flowSequenceEntity) use ($trueCase) {
                return $flowSequenceEntity->getId() !== $trueCase->getId();
            });

            $trueCaseSequence = $this->createNestedSequence($trueCase, $siblingTrueCases);
        }

        $falseCaseSequence = null;
        if ($falseCase) {
            $siblingFalseCases = $falseCases->filter(function (FlowSequenceEntity $flowSequenceEntity) use ($falseCase) {
                return $flowSequenceEntity->getId() !== $falseCase->getId();
            });

            $falseCaseSequence = $this->createNestedSequence($falseCase, $siblingFalseCases);
        }

        return Sequence::createIF($ruleId, $trueCaseSequence, $falseCaseSequence);
    }

    private function buildTree(FlowSequenceCollection $flowSequences, ?string $parentId = null): FlowSequenceCollection
    {
        $children = new FlowSequenceCollection();

        foreach ($flowSequences as $key => $flowSequence) {
            if ($flowSequence->getParentId() !== $parentId) {
                continue;
            }

            $children->add($flowSequence);

            $flowSequences->remove($key);
        }

        $items = new FlowSequenceCollection();

        /* @var FlowSequenceEntity $parent */
        foreach ($children as $child) {
            $child->setChildren($this->buildTree($flowSequences, $child->getId()));
            $items->add($child);
        }

        return $items;
    }
}
