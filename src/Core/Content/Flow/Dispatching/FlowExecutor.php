<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Content\Flow\Dispatching\Struct\ActionSequence;
use Shopware\Core\Content\Flow\Dispatching\Struct\Flow;
use Shopware\Core\Content\Flow\Dispatching\Struct\IfSequence;
use Shopware\Core\Content\Flow\Dispatching\Struct\Sequence;
use Shopware\Core\Content\Flow\Exception\ExecuteSequenceException;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\FlowEventAware;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal not intended for decoration or replacement
 */
class FlowExecutor
{
    private EventDispatcherInterface $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function execute(Flow $flow, FlowEventAware $event): void
    {
        $state = new FlowState($event);
        $state->flowId = $flow->getId();
        foreach ($flow->getSequences() as $sequence) {
            $state->sequenceId = $sequence->sequenceId;

            try {
                $this->executeSequence($sequence, $state);
            } catch (\Exception $e) {
                throw new ExecuteSequenceException($sequence->flowId, $sequence->sequenceId);
            }

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

        if ($sequence instanceof IfSequence) {
            $this->executeIf($sequence, $state);

            return;
        }

        if ($sequence instanceof ActionSequence) {
            $this->executeAction($sequence, $state);
        }
    }

    public function executeAction(ActionSequence $sequence, FlowState $state): void
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

        /** @var ActionSequence $nextAction */
        $nextAction = $sequence->nextAction;
        if ($nextAction !== null) {
            $this->executeAction($nextAction, $state);
        }
    }

    public function executeIf(IfSequence $sequence, FlowState $state): void
    {
        if (\in_array($sequence->ruleId, $state->event->getContext()->getRuleIds(), true)) {
            $this->executeSequence($sequence->trueCase, $state);

            return;
        }

        $this->executeSequence($sequence->falseCase, $state);
    }
}
