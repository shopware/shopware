<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\AbstractRuleLoader;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Content\Flow\Dispatching\Struct\ActionSequence;
use Shopware\Core\Content\Flow\Dispatching\Struct\Flow;
use Shopware\Core\Content\Flow\Dispatching\Struct\IfSequence;
use Shopware\Core\Content\Flow\Dispatching\Struct\Sequence;
use Shopware\Core\Content\Flow\Exception\ExecuteSequenceException;
use Shopware\Core\Content\Flow\Extension\FlowExecutorExtension;
use Shopware\Core\Content\Flow\FlowException;
use Shopware\Core\Content\Flow\Rule\FlowRuleScopeBuilder;
use Shopware\Core\Framework\App\Event\AppFlowActionEvent;
use Shopware\Core\Framework\App\Flow\Action\AppFlowActionProvider;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal not intended for decoration or replacement
 */
#[Package('after-sales')]
class FlowExecutor
{
    /**
     * @var array<string, mixed>
     */
    private readonly array $actions;

    /**
     * @param FlowAction[] $actions
     */
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly AppFlowActionProvider $appFlowActionProvider,
        private readonly AbstractRuleLoader $ruleLoader,
        private readonly FlowRuleScopeBuilder $scopeBuilder,
        private readonly Connection $connection,
        private readonly ExtensionDispatcher $extensions,
        private readonly LoggerInterface $logger,
        $actions
    ) {
        $this->actions = $actions instanceof \Traversable ? iterator_to_array($actions) : $actions;
    }

    /**
     * @param array<int, array<string, mixed>> $flowPayloads
     *
     * @experimental stableVersion:v6.8.0 feature:FLOW_EXECUTION_AFTER_BUSINESS_PROCESS
     */
    public function executeFlows(array $flowPayloads, StorableFlow $event): void
    {
        foreach ($flowPayloads as $flowPayload) {
            $flow = $flowPayload['payload'];
            $id = $flowPayload['id'];
            $name = $flowPayload['name'];

            try {
                $this->extensions->publish(
                    name: FlowExecutorExtension::NAME,
                    extension: new FlowExecutorExtension($flow, $event),
                    function: $this->_execute(...)
                );
            } catch (ExecuteSequenceException $e) {
                $this->logger->error(
                    "Could not execute flow with error message:\n"
                    . 'Flow name: ' . $name . "\n"
                    . 'Flow id: ' . $id . "\n"
                    . 'Sequence id: ' . $e->getSequenceId() . "\n"
                    . $e->getMessage() . "\n"
                    . 'Error Code: ' . $e->getCode() . "\n",
                    ['exception' => $e]
                );
            } catch (\Throwable $e) {
                $this->logger->error(
                    "Could not execute flow with error message:\n"
                    . 'Flow name: ' . $name . "\n"
                    . 'Flow id: ' . $id . "\n"
                    . $e->getMessage() . "\n"
                    . 'Error Code: ' . $e->getCode() . "\n",
                    ['exception' => $e]
                );
            }
        }
    }

    public function execute(Flow $flow, StorableFlow $event): void
    {
        $this->extensions->publish(
            name: FlowExecutorExtension::NAME,
            extension: new FlowExecutorExtension($flow, $event),
            function: $this->_execute(...)
        );
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
        if ($this->sequenceRuleMatches($event, $sequence->ruleId)) {
            $this->executeSequence($sequence->trueCase, $event);

            return;
        }

        $this->executeSequence($sequence->falseCase, $event);
    }

    private function _execute(Flow $flow, StorableFlow $event): void
    {
        $state = new FlowState();

        $event->setFlowState($state);
        $state->flowId = $flow->getId();
        foreach ($flow->getSequences() as $sequence) {
            $state->delayed = false;

            try {
                $this->executeSequence($sequence, $event);
            } catch (\Exception $e) {
                throw ExecuteSequenceException::sequenceExecutionFailed(
                    $sequence->flowId,
                    $sequence->sequenceId,
                    $e->getMessage(),
                    $e->getCode(),
                    $e
                );
            }

            if ($state->stop) {
                return;
            }
        }
    }

    private function callHandle(ActionSequence $sequence, StorableFlow $event): void
    {
        if ($sequence->appFlowActionId) {
            $this->callApp($sequence, $event);

            return;
        }

        $action = $this->actions[$sequence->action] ?? null;

        if (!$action instanceof FlowAction) {
            return;
        }

        if (!$action instanceof TransactionalAction) {
            $action->handleFlow($event);

            return;
        }

        $this->connection->beginTransaction();

        try {
            $action->handleFlow($event);
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            throw FlowException::transactionFailed($e);
        }

        try {
            $this->connection->commit();
        } catch (DBALException $e) {
            $this->connection->rollBack();

            throw FlowException::transactionFailed($e);
        }
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

    private function sequenceRuleMatches(StorableFlow $event, string $ruleId): bool
    {
        if (!$event->hasData(OrderAware::ORDER)) {
            return \in_array($ruleId, $event->getContext()->getRuleIds(), true);
        }

        $order = $event->getData(OrderAware::ORDER);

        if (!$order instanceof OrderEntity) {
            return \in_array($ruleId, $event->getContext()->getRuleIds(), true);
        }

        $rule = $this->ruleLoader->load($event->getContext())->filterForFlow()->get($ruleId);

        if (!$rule || !$rule->getPayload() instanceof Rule) {
            return \in_array($ruleId, $event->getContext()->getRuleIds(), true);
        }

        return $rule->getPayload()->match($this->scopeBuilder->build($order, $event->getContext()));
    }
}
