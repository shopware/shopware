<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as DbalPdoException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\AbstractRuleLoader;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\CustomerRequestedGroupRule;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\Action\AddCustomerTagAction;
use Shopware\Core\Content\Flow\Dispatching\Action\AddOrderTagAction;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Content\Flow\Dispatching\Action\StopFlowAction;
use Shopware\Core\Content\Flow\Dispatching\FlowExecutor;
use Shopware\Core\Content\Flow\Dispatching\FlowState;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Struct\ActionSequence;
use Shopware\Core\Content\Flow\Dispatching\Struct\Flow;
use Shopware\Core\Content\Flow\Dispatching\Struct\IfSequence;
use Shopware\Core\Content\Flow\Dispatching\Struct\Sequence;
use Shopware\Core\Content\Flow\Dispatching\TransactionalAction;
use Shopware\Core\Content\Flow\Dispatching\TransactionFailedException;
use Shopware\Core\Content\Flow\Exception\ExecuteSequenceException;
use Shopware\Core\Content\Flow\Extension\FlowExecutorExtension;
use Shopware\Core\Content\Flow\FlowException;
use Shopware\Core\Content\Flow\Rule\FlowRuleScope;
use Shopware\Core\Content\Flow\Rule\FlowRuleScopeBuilder;
use Shopware\Core\Content\Flow\Rule\OrderTagRule;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\App\Event\AppFlowActionEvent;
use Shopware\Core\Framework\App\Flow\Action\AppFlowActionProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Event\SalesChannelContextAware;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tag\TagCollection;
use Shopware\Core\System\Tag\TagEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(FlowExecutor::class)]
class FlowExecutorTest extends TestCase
{
    private const ACTION_ADD_ORDER_TAG = 'action.add.order.tag';
    private const ACTION_ADD_CUSTOMER_TAG = 'action.add.customer.tag';
    private const ACTION_STOP_FLOW = 'action.stop.flow';

    private FlowExecutor $flowExecutor;

    private MockObject&EventDispatcherInterface $eventDispatcherMock;

    private MockObject&AppFlowActionProvider $appFlowActionProviderMock;

    private MockObject&AbstractRuleLoader $ruleLoaderMock;

    private MockObject&FlowRuleScopeBuilder $scopeBuilderMock;

    private MockObject&Connection $connectionMock;

    private MockObject&LoggerInterface $loggerMock;

    private MockObject&AddOrderTagAction $addOrderTagActionMock;

    private MockObject&AddCustomerTagAction $addCustomerTagActionMock;

    private MockObject&StopFlowAction $stopFlowActionMock;

    protected function setUp(): void
    {
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->appFlowActionProviderMock = $this->createMock(AppFlowActionProvider::class);
        $this->ruleLoaderMock = $this->createMock(AbstractRuleLoader::class);
        $this->scopeBuilderMock = $this->createMock(FlowRuleScopeBuilder::class);
        $this->connectionMock = $this->createMock(Connection::class);
        $this->connectionMock->method('transactional')
            ->willReturnCallback(static fn (\Closure $func) => $func());
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->addOrderTagActionMock = $this->createMock(AddOrderTagAction::class);
        $this->addCustomerTagActionMock = $this->createMock(AddCustomerTagAction::class);
        $this->stopFlowActionMock = $this->createMock(StopFlowAction::class);

        // Replace mocked FlowExecutor with an actual instance
        $this->flowExecutor = $this->createFlowExecutor([
            self::ACTION_ADD_ORDER_TAG => $this->addOrderTagActionMock,
            self::ACTION_ADD_CUSTOMER_TAG => $this->addCustomerTagActionMock,
            self::ACTION_STOP_FLOW => $this->stopFlowActionMock,
        ]);
    }

    public function testExecuteFlowsSingleActionExecuted(): void
    {
        $actionSequences = [];
        $actionSequence = new ActionSequence();
        $actionSequence->sequenceId = 'seq-add-order';
        $actionSequence->action = self::ACTION_ADD_ORDER_TAG;
        $actionSequences[] = $actionSequence;

        $flow = new Flow('flowId', $actionSequences);
        $storableFlow = new StorableFlow('', Context::createCLIContext());

        $this->addOrderTagActionMock->expects($this->once())->method('handleFlow')->with($storableFlow);
        $this->addCustomerTagActionMock->expects($this->never())->method('handleFlow');
        $this->stopFlowActionMock->expects($this->never())->method('handleFlow');

        $this->flowExecutor->executeFlows([
            ['id' => 'flowId', 'name' => 'flow', 'payload' => $flow],
        ], $storableFlow);
    }

    public function testExecuteFlowsMultipleActionsExecuted(): void
    {
        $actionSequences = [];
        $a1 = new ActionSequence();
        $a1->sequenceId = 'seq-a1';
        $a1->action = self::ACTION_ADD_ORDER_TAG;
        $actionSequences[] = $a1;

        $a2 = new ActionSequence();
        $a2->sequenceId = 'seq-a2';
        $a2->action = self::ACTION_ADD_CUSTOMER_TAG;
        $actionSequences[] = $a2;

        $a3 = new ActionSequence();
        $a3->sequenceId = 'seq-a3';
        $a3->action = self::ACTION_STOP_FLOW;
        $actionSequences[] = $a3;

        $flow = new Flow('flowId', $actionSequences);
        $storableFlow = new StorableFlow('', Context::createCLIContext());

        $this->addOrderTagActionMock->expects($this->once())->method('handleFlow')->with($storableFlow);
        $this->addCustomerTagActionMock->expects($this->once())->method('handleFlow')->with($storableFlow);
        $this->stopFlowActionMock->expects($this->once())->method('handleFlow')->with($storableFlow);

        $this->flowExecutor->executeFlows([
            ['id' => 'flowId', 'name' => 'flow', 'payload' => $flow],
        ], $storableFlow);
    }

    public function testExecuteFlowsActionExecutedWithTrueCase(): void
    {
        $actionSequences = [];
        $condition = new IfSequence();
        $condition->sequenceId = 'true_case';
        $condition->ruleId = 'ruleId';

        $context = Context::createCLIContext();
        $context->setRuleIds(['ruleId']);

        $trueSeq = new ActionSequence();
        $trueSeq->sequenceId = 'seq-true';
        $trueSeq->action = self::ACTION_ADD_ORDER_TAG;
        $condition->trueCase = $trueSeq;

        $actionSequences[] = $condition;

        $flow = new Flow('flowId', $actionSequences);
        $storableFlow = new StorableFlow('', $context);

        $this->addOrderTagActionMock->expects($this->once())->method('handleFlow')->with($storableFlow);
        $this->addCustomerTagActionMock->expects($this->never())->method('handleFlow');
        $this->stopFlowActionMock->expects($this->never())->method('handleFlow');

        $this->flowExecutor->executeFlows([
            ['id' => 'flowId', 'name' => 'flow', 'payload' => $flow],
        ], $storableFlow);
    }

    public function testExecuteFlowsActionExecutedWithFalseCase(): void
    {
        $actionSequences = [];
        $condition = new IfSequence();
        $condition->sequenceId = 'false_case';
        $condition->ruleId = 'ruleId';

        $falseSeq = new ActionSequence();
        $falseSeq->sequenceId = 'seq-false';
        $falseSeq->action = self::ACTION_ADD_ORDER_TAG;
        $condition->falseCase = $falseSeq;

        $actionSequences[] = $condition;

        $flow = new Flow('flowId', $actionSequences);
        $storableFlow = new StorableFlow('', Context::createCLIContext());

        $this->addOrderTagActionMock->expects($this->once())->method('handleFlow')->with($storableFlow);
        $this->addCustomerTagActionMock->expects($this->never())->method('handleFlow');
        $this->stopFlowActionMock->expects($this->never())->method('handleFlow');

        $this->flowExecutor->executeFlows([
            ['id' => 'flowId', 'name' => 'flow', 'payload' => $flow],
        ], $storableFlow);
    }

    public function testExecuteFlowsActionExecutedFromApp(): void
    {
        $actionSequences = [];
        $appActionSequence = new ActionSequence();
        $appActionSequence->appFlowActionId = 'AppActionId';
        $appActionSequence->sequenceId = 'AppActionSequenceId';
        $appActionSequence->action = 'app.action';
        $actionSequences[] = $appActionSequence;

        $flow = new Flow('flowId', $actionSequences);
        $storableFlow = new StorableFlow('', Context::createCLIContext());

        $this->appFlowActionProviderMock->expects($this->once())
            ->method('getWebhookPayloadAndHeaders')->willReturn([
                'headers' => [],
                'payload' => [],
            ]);

        $invocations = $this->exactly(3);
        $this->eventDispatcherMock->expects($invocations)
            ->method('dispatch')
            ->with(
                static::callback(
                    static function (object $event, ?string $_ = null) use ($flow, $storableFlow, $invocations): bool {
                        match ($invocations->numberOfInvocations()) {
                            1 => static::assertEquals(new FlowExecutorExtension($flow, $storableFlow), $event),
                            2 => static::assertEquals(new AppFlowActionEvent('app.action', [], []), $event),
                            3 => static::assertEquals(new FlowExecutorExtension($flow, $storableFlow), $event),
                            default => static::fail('Unexpected number of invocations'),
                        };

                        return true;
                    }
                ),
            );

        $this->flowExecutor->executeFlows([
            ['id' => 'flowId', 'name' => 'flow', 'payload' => $flow],
        ], $storableFlow);
    }

    public function testCallAppReturnsEarlyWhenAppFlowActionIdNotSet(): void
    {
        $actionSequence = new ActionSequence();
        $actionSequence->sequenceId = 'no-app-sequence';
        $actionSequence->action = 'app.action';

        $storableFlow = new StorableFlow('', Context::createCLIContext());
        $storableFlow->setFlowState(new FlowState());

        // When appFlowActionId is not set, callHandle should not call the AppFlowActionProvider
        $this->appFlowActionProviderMock->expects($this->never())
            ->method('getWebhookPayloadAndHeaders');

        $this->eventDispatcherMock->expects($this->never())
            ->method('dispatch');

        $this->flowExecutor->executeAction($actionSequence, $storableFlow);
    }

    #[DataProvider('logExceptionProvider')]
    public function testExecuteFlowsLogsExceptions(\Throwable $exception, string $extraLine): void
    {
        $actionSequences = [new ActionSequence()];
        $flow = new Flow('flowId', $actionSequences);
        $storableFlow = new StorableFlow('', Context::createCLIContext());

        $this->eventDispatcherMock->method('dispatch')
            ->willThrowException($exception);

        $expected = 'Could not execute flow with error message:' . "\n"
            . 'Flow name: flow' . "\n"
            . 'Flow id: flowId' . "\n"
            . $extraLine
            . $exception->getMessage() . "\n"
            . 'Error Code: ' . $exception->getCode() . "\n";

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with($expected);

        $this->flowExecutor->executeFlows(
            [
                [
                    'id' => 'flowId',
                    'name' => 'flow',
                    'payload' => $flow,
                ],
            ],
            $storableFlow,
        );
    }

    public static function logExceptionProvider(): \Generator
    {
        yield 'sequence exception' => [
            new ExecuteSequenceException('some-flow-id', 'some-sequence-id', 'error'),
            'Sequence id: some-sequence-id' . "\n",
        ];

        yield 'generic exception' => [
            new \Exception('error'),
            '',
        ];
    }

    public function testExecuteSingleActionExecuted(): void
    {
        $actionSequences = [];

        $actionSequence = new ActionSequence();
        $actionSequence->sequenceId = 'seq-add-order';
        $actionSequence->action = self::ACTION_ADD_ORDER_TAG;
        $actionSequences[] = $actionSequence;

        $flow = new Flow('flowId', $actionSequences);
        $storableFlow = new StorableFlow('', Context::createCLIContext());

        $this->addOrderTagActionMock->expects($this->once())->method('handleFlow')->with($storableFlow);
        $this->addCustomerTagActionMock->expects($this->never())->method('handleFlow');
        $this->stopFlowActionMock->expects($this->never())->method('handleFlow');

        $this->flowExecutor->execute($flow, $storableFlow);
    }

    public function testExecuteMultipleActionsExecuted(): void
    {
        $actionSequences = [];

        $a1 = new ActionSequence();
        $a1->sequenceId = 'seq-a1';
        $a1->action = self::ACTION_ADD_ORDER_TAG;
        $actionSequences[] = $a1;
        $a2 = new ActionSequence();
        $a2->sequenceId = 'seq-a2';
        $a2->action = self::ACTION_ADD_CUSTOMER_TAG;
        $actionSequences[] = $a2;
        $a3 = new ActionSequence();
        $a3->sequenceId = 'seq-a3';
        $a3->action = self::ACTION_STOP_FLOW;
        $actionSequences[] = $a3;
        $flow = new Flow('flowId', $actionSequences);
        $storableFlow = new StorableFlow('', Context::createCLIContext());

        $this->addOrderTagActionMock->expects($this->once())->method('handleFlow')->with($storableFlow);
        $this->addCustomerTagActionMock->expects($this->once())->method('handleFlow')->with($storableFlow);
        $this->stopFlowActionMock->expects($this->once())->method('handleFlow')->with($storableFlow);

        $this->flowExecutor->execute($flow, $storableFlow);
    }

    public function testExecuteActionExecutedWithTrueCase(): void
    {
        $actionSequences = [];

        $condition = new IfSequence();
        $condition->sequenceId = 'true_case';
        $condition->ruleId = 'ruleId';

        $context = Context::createCLIContext();
        $context->setRuleIds(['ruleId']);

        $trueSeq = new ActionSequence();
        $trueSeq->sequenceId = 'seq-true';
        $trueSeq->action = self::ACTION_ADD_ORDER_TAG;
        $condition->trueCase = $trueSeq;

        $actionSequences[] = $condition;

        $flow = new Flow('flowId', $actionSequences);
        $storableFlow = new StorableFlow('', $context);

        $this->addOrderTagActionMock->expects($this->once())->method('handleFlow')->with($storableFlow);
        $this->addCustomerTagActionMock->expects($this->never())->method('handleFlow');
        $this->stopFlowActionMock->expects($this->never())->method('handleFlow');

        $this->flowExecutor->execute($flow, $storableFlow);
    }

    public function testExecuteActionExecutedWithFalseCase(): void
    {
        $actionSequences = [];

        $condition = new IfSequence();
        $condition->sequenceId = 'false_case';
        $condition->ruleId = 'ruleId';

        $falseSeq = new ActionSequence();
        $falseSeq->sequenceId = 'seq-false';
        $falseSeq->action = self::ACTION_ADD_ORDER_TAG;
        $condition->falseCase = $falseSeq;

        $actionSequences[] = $condition;

        $flow = new Flow('flowId', $actionSequences);
        $storableFlow = new StorableFlow('', Context::createCLIContext());

        $this->addOrderTagActionMock->expects($this->once())->method('handleFlow')->with($storableFlow);
        $this->addCustomerTagActionMock->expects($this->never())->method('handleFlow');
        $this->stopFlowActionMock->expects($this->never())->method('handleFlow');

        $this->flowExecutor->execute($flow, $storableFlow);
    }

    public function testExecuteActionExecutedFromApp(): void
    {
        $actionSequences = [];

        $appActionSequence = new ActionSequence();
        $appActionSequence->appFlowActionId = 'AppActionId';
        $appActionSequence->sequenceId = 'AppActionSequenceId';
        $appActionSequence->action = 'app.action';
        $actionSequences[] = $appActionSequence;
        $flow = new Flow('flowId', $actionSequences);
        $storableFlow = new StorableFlow('', Context::createCLIContext());

        $this->appFlowActionProviderMock->expects($this->once())
            ->method('getWebhookPayloadAndHeaders')->willReturn([
                'headers' => [],
                'payload' => [],
            ]);

        $invocations = $this->exactly(3);
        $this->eventDispatcherMock->expects($invocations)
            ->method('dispatch')
            ->with(
                static::callback(
                    static function (object $event, ?string $_ = null) use ($flow, $storableFlow, $invocations): bool {
                        match ($invocations->numberOfInvocations()) {
                            1 => static::assertEquals(new FlowExecutorExtension($flow, $storableFlow), $event),
                            2 => static::assertEquals(new AppFlowActionEvent('app.action', [], []), $event),
                            3 => static::assertEquals(new FlowExecutorExtension($flow, $storableFlow), $event),
                            default => static::fail('Unexpected number of invocations'),
                        };

                        return true;
                    }
                ),
            );

        $this->flowExecutor->execute($flow, $storableFlow);
    }

    public function testExecuteIfWithRuleEvaluation(): void
    {
        $trueCaseSequence = new Sequence();
        $trueCaseSequence->assign(['sequenceId' => 'foobar']);
        $ruleId = Uuid::randomHex();
        $ifSequence = new IfSequence();
        $ifSequence->assign(['ruleId' => $ruleId, 'trueCase' => $trueCaseSequence]);

        $order = new OrderEntity();
        $tagId = Uuid::randomHex();
        $tag = new TagEntity();
        $tag->setId($tagId);
        $order->setTags(new TagCollection([$tag]));

        $flow = new StorableFlow('bar', Context::createCLIContext());
        $flow->setFlowState(new FlowState());
        $flow->setData(OrderAware::ORDER, $order);

        $this->scopeBuilderMock->method('build')->willReturn(
            new FlowRuleScope($order, new Cart('test'), $this->createMock(SalesChannelContext::class))
        );

        $rule = new OrderTagRule(Rule::OPERATOR_EQ, [$tagId]);
        $ruleEntity = new RuleEntity();
        $ruleEntity->setId($ruleId);
        $ruleEntity->setPayload($rule);
        $ruleEntity->setAreas([RuleAreas::FLOW_AREA]);
        $this->ruleLoaderMock->method('load')->willReturn(new RuleCollection([$ruleEntity]));

        $this->flowExecutor->executeIf($ifSequence, $flow);

        static::assertSame($trueCaseSequence, $flow->getFlowState()->currentSequence);
    }

    public function testExecuteIfWithNonEntityOrderFallsBackToContextRuleIds(): void
    {
        $ifSequence = new IfSequence();
        $ifSequence->assign(['ruleId' => 'ruleId']);

        $trueSeq = new ActionSequence();
        $trueSeq->sequenceId = 'true-seq';
        $trueSeq->action = AddOrderTagAction::getName();

        $ifSequence->trueCase = $trueSeq;

        // put a non-OrderEntity into the flow data
        $storableFlow = new StorableFlow('', Context::createCLIContext());
        $storableFlow->setFlowState(new FlowState());
        $storableFlow->setData(OrderAware::ORDER, new \stdClass());

        // put the rule id into the context so sequenceRuleMatches falls back to context
        $context = Context::createCLIContext();
        $context->setRuleIds(['ruleId']);
        $storableFlow = new StorableFlow('', $context);
        $storableFlow->setFlowState(new FlowState());
        $storableFlow->setData(OrderAware::ORDER, new \stdClass());

        $this->addOrderTagActionMock->expects($this->once())->method('handleFlow')->with($storableFlow);

        $this->flowExecutor->executeIf($ifSequence, $storableFlow);
    }

    public function testExecuteIfWhenRuleMissingFallsBackToContextRuleIds(): void
    {
        $ruleId = 'ruleId';

        $ifSequence = new IfSequence();
        $ifSequence->assign(['ruleId' => $ruleId]);

        $trueSeq = new ActionSequence();
        $trueSeq->sequenceId = 'true-seq';
        $trueSeq->action = AddOrderTagAction::getName();
        $ifSequence->trueCase = $trueSeq;

        // set an OrderEntity so code reaches the rule loader branch
        $order = new OrderEntity();

        $context = Context::createCLIContext();
        // ensure fallback value exists in context
        $context->setRuleIds([$ruleId]);

        $storableFlow = new StorableFlow('', $context);
        $storableFlow->setFlowState(new FlowState());
        $storableFlow->setData(OrderAware::ORDER, $order);

        // Simulate ruleLoader returning no rule for the given id
        $this->ruleLoaderMock->method('load')->willReturn(new RuleCollection([]));

        $this->addOrderTagActionMock->expects($this->once())->method('handleFlow')->with($storableFlow);

        $this->flowExecutor->executeIf($ifSequence, $storableFlow);
    }

    public function testActionExecutedInTransactionWhenItImplementsTransactional(): void
    {
        $actionSequence = new ActionSequence();
        $actionSequence->sequenceId = 'test-sequence';
        $actionSequence->action = StubFlowAction::class;

        $this->connectionMock->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static function (\Closure $func): void {
                $func();
            });

        $flow = new StorableFlow('some-flow', Context::createCLIContext());
        $flow->setFlowState(new FlowState());

        $stubFlowAction = new StubFlowAction(toBeHandled: true);
        $this->flowExecutor = $this->createFlowExecutor([
            StubFlowAction::class => $stubFlowAction,
        ]);
        $this->flowExecutor->executeAction($actionSequence, $flow);

        static::assertTrue($stubFlowAction->handled);
    }

    public function testTransactionCommitFailureExceptionIsWrapped(): void
    {
        $action = new StubFlowAction();

        $actionSequence = new ActionSequence();
        $actionSequence->sequenceId = 'test-sequence';
        $actionSequence->action = $action::class;

        $e = new TableNotFoundException(
            new DbalPdoException('Table not found', null, 1146),
            null
        );

        $this->connectionMock->expects($this->once())
            ->method('transactional')
            ->willThrowException($e);

        $flow = new StorableFlow('some-flow', Context::createCLIContext());
        $flow->setFlowState(new FlowState());

        $this->flowExecutor = $this->createFlowExecutor([
            $action::class => $action,
        ]);

        $this->expectExceptionObject(FlowException::transactionFailed($e));

        $this->flowExecutor->executeAction($actionSequence, $flow);
    }

    public function testTransactionAbortExceptionIsWrapped(): void
    {
        $exception = TransactionFailedException::because(new \Exception('broken'));
        $action = new StubFlowAction($exception);

        $actionSequence = new ActionSequence();
        $actionSequence->sequenceId = 'test-sequence';
        $actionSequence->action = $action::class;

        $this->connectionMock->expects($this->once())
            ->method('transactional')
            ->willThrowException($exception);

        $flow = new StorableFlow('some-flow', Context::createCLIContext());
        $flow->setFlowState(new FlowState());

        $this->flowExecutor = $this->createFlowExecutor([
            $action::class => $action,
        ]);

        $this->expectExceptionObject(FlowException::transactionFailed($exception));
        $this->flowExecutor->executeAction($actionSequence, $flow);
    }

    public function testTransactionWithUncaughtExceptionIsWrapped(): void
    {
        $exception = new \Exception('broken');
        $action = new StubFlowAction($exception);

        $actionSequence = new ActionSequence();
        $actionSequence->sequenceId = 'test-sequence';
        $actionSequence->action = $action::class;

        $flow = new StorableFlow('some-flow', Context::createCLIContext());
        $flow->setFlowState(new FlowState());

        $this->flowExecutor = $this->createFlowExecutor([
            $action::class => $action,
        ]);

        $this->expectExceptionObject(FlowException::transactionFailed($exception));
        $this->flowExecutor->executeAction($actionSequence, $flow);
    }

    public function testExtensionIsDispatched(): void
    {
        $flow = new Flow('test', []);
        $storableFlow = new StorableFlow('', Context::createCLIContext());

        $pre = $this->createMock(CallableClass::class);
        $pre->expects($this->once())->method('__invoke');

        $post = $this->createMock(CallableClass::class);
        $post->expects($this->once())->method('__invoke');

        $invocations = $this->exactly(2);
        $this->eventDispatcherMock->expects($invocations)
            ->method('dispatch')
            ->with(
                static::callback(
                    static function (object $event, ?string $_ = null) use ($flow, $storableFlow, $invocations, $pre, $post): bool {
                        $invocationNumber = $invocations->numberOfInvocations();
                        match ($invocationNumber) {
                            1 => static::assertEquals(new FlowExecutorExtension($flow, $storableFlow), $event),
                            2 => static::assertEquals(new FlowExecutorExtension($flow, $storableFlow), $event),
                            default => static::fail('Unexpected number of invocations'),
                        };

                        match ($invocationNumber) {
                            1 => $pre->__invoke(),
                            2 => $post->__invoke(),
                        };

                        return true;
                    }
                ),
            );

        $this->flowExecutor->execute($flow, $storableFlow);
    }

    public function testExitActionExecutionIfSequenceActionIsNotSet(): void
    {
        $actionSequence = new ActionSequence();
        $actionSequence->sequenceId = 'test-sequence';
        $actionSequence->config = ['test' => 'value'];
        $actionSequence->action = '';

        $flow = new StorableFlow('some-flow', Context::createCLIContext());
        $flow->setFlowState(new FlowState());

        $this->flowExecutor->executeAction($actionSequence, $flow);

        static::assertEmpty($flow->getConfig());
    }

    public function testExitActionExecutionIfFlowStopIsSet(): void
    {
        $actionSequence = new ActionSequence();
        $actionSequence->sequenceId = 'test-sequence';
        $actionSequence->config = ['test' => 'value'];
        $actionSequence->action = 'test.action';

        $flow = new StorableFlow('some-flow', Context::createCLIContext());
        $flow->setFlowState(new FlowState());
        $flow->getFlowState()->stop = true;

        $this->flowExecutor->executeAction($actionSequence, $flow);

        static::assertEmpty($flow->getConfig());
    }

    public function testExitActionExecutionIfFlowIsDelayed(): void
    {
        $actionSequence = new ActionSequence();
        $actionSequence->sequenceId = 'test-sequence';
        $actionSequence->config = ['test' => 'value'];
        $actionSequence->action = 'test.action';

        $actionSequence2 = new ActionSequence();
        $actionSequence2->sequenceId = 'next-sequence';
        $actionSequence2->config = ['next' => 'value'];
        $actionSequence2->action = AddOrderTagAction::getName();
        $actionSequence->nextAction = $actionSequence2;

        $flow = new StorableFlow('some-flow', Context::createCLIContext());
        $flow->setFlowState(new FlowState());
        $flow->getFlowState()->delayed = true;

        $this->addOrderTagActionMock
            ->expects($this->never())
            ->method('handleFlow');

        $this->flowExecutor->executeAction($actionSequence, $flow);

        static::assertNotEmpty($flow->getConfig());
    }

    public function testExitActionExecutionIfNextActionIsNull(): void
    {
        $actionSequence = new ActionSequence();
        $actionSequence->sequenceId = 'test-sequence';
        $actionSequence->config = ['test' => 'value'];
        $actionSequence->action = 'test.action';
        $actionSequence2 = new ActionSequence();
        $actionSequence2->sequenceId = 'next-sequence';
        $actionSequence2->config = ['next' => 'value'];
        $actionSequence2->action = AddOrderTagAction::getName();
        $actionSequence->nextAction = $actionSequence2;

        $flow = new StorableFlow('some-flow', Context::createCLIContext());
        $flow->setFlowState(new FlowState());

        $this->addOrderTagActionMock
            ->expects($this->once())
            ->method('handleFlow');

        $this->flowExecutor->executeAction($actionSequence, $flow);

        static::assertNotEmpty($flow->getConfig());
    }

    public function testSetCurrentSequenceInFlowStateForActionExecution(): void
    {
        $actionSequence = new ActionSequence();
        $actionSequence->sequenceId = 'first-sequence';
        $actionSequence->config = ['first' => 'value'];
        $actionSequence->action = AddOrderTagAction::getName();

        $actionSequence2 = new ActionSequence();
        $actionSequence2->sequenceId = 'second-sequence';
        $actionSequence2->config = ['second' => 'value'];
        $actionSequence2->action = AddOrderTagAction::getName();
        $actionSequence->nextAction = $actionSequence2;

        $actionSequence3 = new ActionSequence();
        $actionSequence3->sequenceId = 'third-sequence';
        $actionSequence3->config = ['third' => 'value'];
        $actionSequence3->action = AddOrderTagAction::getName();
        $actionSequence2->nextAction = $actionSequence3;

        $flow = new StorableFlow('some-flow', Context::createCLIContext());
        $flowState = new FlowState();
        $flowState->currentSequence = $actionSequence;
        $flow->setFlowState($flowState);

        $callCount = 0;
        $idSequence = [
            'first-sequence',
            'second-sequence',
            'third-sequence',
        ];

        $this->addOrderTagActionMock
            ->expects($this->exactly(3))
            ->method('handleFlow')
            ->willReturnCallback(static function (StorableFlow $flow) use (&$callCount, $idSequence): void {
                static::assertSame(
                    $idSequence[$callCount],
                    $flow->getFlowState()->currentSequence->sequenceId
                );

                ++$callCount;
            });

        $this->flowExecutor->executeAction($actionSequence, $flow);

        static::assertNotEmpty($flow->getConfig());
    }

    public function testExecuteStopsOnFlowStateReachesStop(): void
    {
        $storableFlow = new StorableFlow('', Context::createCLIContext());

        $actionSequence = new ActionSequence();
        $actionSequence->sequenceId = 'test-sequence-id';
        $actionSequence->action = 'action.stop.flow';

        $this->flowExecutor = $this->createFlowExecutor([
            self::ACTION_STOP_FLOW => new StopFlowAction(),
        ]);

        $flow = new Flow('test-flow', [$actionSequence]);
        $this->flowExecutor->execute($flow, $storableFlow);

        $stateAfter = $storableFlow->getFlowState();
        static::assertTrue($stateAfter->stop);
    }

    public function testExecuteWrapsSequenceException(): void
    {
        $actionSequence = new ActionSequence();
        $actionSequence->sequenceId = 'throwing-sequence';
        $actionSequence->flowId = 'flowId';
        $actionSequence->action = StubFlowAction::class;

        $storableFlow = new StorableFlow('', Context::createCLIContext());

        $throwing = new StubFlowAction(new \Exception('broken'));

        $this->flowExecutor = $this->createFlowExecutor([
            $throwing::class => $throwing,
        ]);

        $flow = new Flow('flowId', [$actionSequence]);

        $this->expectExceptionObject(ExecuteSequenceException::sequenceExecutionFailed(
            'flowId',
            'throwing-sequence',
            'broken'
        ));

        $this->flowExecutor->execute($flow, $storableFlow);
    }

    #[DataProvider('salesChannelContextCustomerDataProvider')]
    public function testExecuteIfWithCustomerRuleScopeEvaluation(?CustomerEntity $contextCustomer): void
    {
        $trueCaseSequence = new Sequence();
        $trueCaseSequence->assign(['sequenceId' => 'foobar']);
        $ruleId = Uuid::randomHex();
        $ifSequence = new IfSequence();
        $ifSequence->assign(['ruleId' => $ruleId, 'trueCase' => $trueCaseSequence]);

        $groupId = Uuid::randomHex();
        $customer = new CustomerEntity();
        $customer->setRequestedGroupId($groupId);
        $contextCustomer?->setRequestedGroupId($groupId);

        $context = Context::createDefaultContext();
        $context->setRuleIds([$ruleId]);

        $flow = new StorableFlow('bar', $context);
        $flow->setFlowState(new FlowState());
        $flow->setData(CustomerAware::CUSTOMER, $customer);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->expects($this->once())->method('getCustomer')->willReturn($contextCustomer);

        $flow->setData(SalesChannelContextAware::SALES_CHANNEL_CONTEXT, $salesChannelContext);

        $rule = new CustomerRequestedGroupRule(Rule::OPERATOR_EQ, [$groupId]);
        $ruleEntity = new RuleEntity();
        $ruleEntity->setId($ruleId);
        $ruleEntity->setPayload($rule);
        $ruleEntity->setAreas([RuleAreas::FLOW_AREA]);

        $this->ruleLoaderMock->expects($this->exactly($contextCustomer === null ? 1 : 0))
            ->method('load')
            ->willReturn(new RuleCollection([$ruleEntity]));

        $this->flowExecutor->executeIf($ifSequence, $flow);

        static::assertSame($trueCaseSequence, $flow->getFlowState()->currentSequence);
    }

    public static function salesChannelContextCustomerDataProvider(): \Generator
    {
        yield 'no customer in sales channel context from store' => [null];
        yield 'customer in sales channel context from store' => [new CustomerEntity()];
    }

    /**
     * @param array<string, FlowAction> $actions
     */
    private function createFlowExecutor(array $actions): FlowExecutor
    {
        return new FlowExecutor(
            $this->eventDispatcherMock,
            $this->appFlowActionProviderMock,
            $this->ruleLoaderMock,
            $this->scopeBuilderMock,
            $this->connectionMock,
            new ExtensionDispatcher($this->eventDispatcherMock),
            $this->loggerMock,
            $actions,
        );
    }
}

/**
 * @internal
 */
class StubFlowAction extends FlowAction implements TransactionalAction
{
    public bool $handled = false;

    public function __construct(private readonly ?\Exception $exceptionToThrow = null, private readonly bool $toBeHandled = false)
    {
    }

    public static function getName(): string
    {
        return 'transactional-action';
    }

    public function handleFlow(StorableFlow $flow): void
    {
        if ($this->exceptionToThrow !== null) {
            throw $this->exceptionToThrow;
        }

        if ($this->toBeHandled) {
            $this->handled = true;
        }
    }

    public function requirements(): array
    {
        return [];
    }
}
