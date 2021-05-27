<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow;

use Shopware\Core\Content\Flow\SequenceTree\Sequence;
use Shopware\Core\Content\Flow\SequenceTree\SequenceTree;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\FlowEvent;
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

    public function execute(SequenceTree $sequences, BusinessEventInterface $event): void
    {
        $state = new FlowState($event);
        foreach ($sequences->getElements() as $sequence) {
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

        if ($state->stop) {
            return;
        }

        $globalEvent = new FlowEvent($actionName, $state, $sequence->config);
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
}
