<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Content\Flow\Dispatching\Struct\ActionSequence;
use Shopware\Core\Content\Flow\Dispatching\Struct\Flow;
use Shopware\Core\Content\Flow\Dispatching\Struct\IfSequence;
use Shopware\Core\Content\Flow\Dispatching\Struct\Sequence;
use Shopware\Core\Content\Flow\Exception\ExecuteSequenceException;
use Shopware\Core\Framework\App\Event\AppFlowActionEvent;
use Shopware\Core\Framework\App\FlowAction\AppFlowActionProvider;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Feature;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal not intended for decoration or replacement
 */
class FlowExecutor
{
    private EventDispatcherInterface $dispatcher;

    private AppFlowActionProvider $appFlowActionProvider;

    /**
     * @var array<string, mixed>
     */
    private array $actions;

    /**
     * @param FlowAction[] $actions
     */
    public function __construct(EventDispatcherInterface $dispatcher, AppFlowActionProvider $appFlowActionProvider, $actions)
    {
        $this->dispatcher = $dispatcher;
        $this->appFlowActionProvider = $appFlowActionProvider;
        $this->actions = $actions instanceof \Traversable ? iterator_to_array($actions) : $actions;
    }

    public function execute(Flow $flow, StorableFlow $event): void
    {
        if (!Feature::isActive('v6.5.0.0')) {
            $state = new FlowState($event->getOriginalEvent());
        } else {
            $state = new FlowState();
        }

        $event->setFlowState($state);
        $state->flowId = $flow->getId();
        foreach ($flow->getSequences() as $sequence) {
            $state->sequenceId = $sequence->sequenceId;
            $state->delayed = false;

            try {
                $this->executeSequence($sequence, $event);
            } catch (\Exception $e) {
                throw new ExecuteSequenceException($sequence->flowId, $sequence->sequenceId, $e->getMessage(), $e->getCode(), $e);
            }

            if ($state->stop) {
                return;
            }
        }
    }

    public function executeSequence(?Sequence $sequence, StorableFlow $event): void
    {
        if ($sequence === null) {
            return;
        }

        $event->getFlowState()->currentSequence = $sequence;

        if ($sequence instanceof IfSequence) {
            $this->executeIf($sequence, $event);

            return;
        }

        if ($sequence instanceof ActionSequence) {
            $this->executeAction($sequence, $event);
        }
    }

    public function executeAction(ActionSequence $sequence, StorableFlow $event): void
    {
        $actionName = $sequence->action;
        if (!$actionName) {
            return;
        }

        if ($event->getFlowState()->stop) {
            return;
        }

        $event->setConfig($sequence->config);

        $this->callHandle($sequence, $event);

        if ($event->getFlowState()->delayed) {
            return;
        }

        $event->getFlowState()->currentSequence = $sequence;

        /** @var ActionSequence $nextAction */
        $nextAction = $sequence->nextAction;
        if ($nextAction !== null) {
            $this->executeAction($nextAction, $event);
        }
    }

    public function executeIf(IfSequence $sequence, StorableFlow $event): void
    {
        if (\in_array($sequence->ruleId, $event->getContext()->getRuleIds(), true)) {
            $this->executeSequence($sequence->trueCase, $event);

            return;
        }

        $this->executeSequence($sequence->falseCase, $event);
    }

    private function callHandle(ActionSequence $sequence, StorableFlow $event): void
    {
        if ($sequence->appFlowActionId) {
            $this->callApp($sequence, $event);

            return;
        }

        if (Feature::isActive('v6.5.0.0')) {
            $action = $this->actions[$sequence->action] ?? null;
            if ($action) {
                $action->handleFlow($event);
            }

            return;
        }

        $globalEvent = new FlowEvent($sequence->action, $event->getFlowState(), $sequence->config);
        $event->setFlowEvent($globalEvent);

        $this->dispatcher->dispatch($globalEvent, $sequence->action);
    }

    private function callApp(ActionSequence $sequence, StorableFlow $event): void
    {
        if (!$sequence->appFlowActionId) {
            return;
        }

        $eventData = $this->appFlowActionProvider->getWebhookPayloadAndHeaders($event, $sequence->appFlowActionId);

        $globalEvent = new AppFlowActionEvent(
            $sequence->action,
            $eventData['headers'],
            $eventData['payload'],
        );

        $this->dispatcher->dispatch($globalEvent, $sequence->action);
    }
}
