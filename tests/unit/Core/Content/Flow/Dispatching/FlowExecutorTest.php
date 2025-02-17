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
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tag\TagCollection;
use Shopware\Core\System\Tag\TagEntity;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
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
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->addOrderTagActionMock = $this->createMock(AddOrderTagAction::class);
        $this->addCustomerTagActionMock = $this->createMock(AddCustomerTagAction::class);
        $this->stopFlowActionMock = $this->createMock(StopFlowAction::class);

        $this->flowExecutor = new FlowExecutor(
            $this->eventDispatcherMock,
            $this->appFlowActionProviderMock,
            $this->ruleLoaderMock,
            $this->scopeBuilderMock,
            $this->connectionMock,
            new ExtensionDispatcher($this->eventDispatcherMock),
            $this->loggerMock,
            [
                self::ACTION_ADD_ORDER_TAG => $this->addOrderTagActionMock,
                self::ACTION_ADD_CUSTOMER_TAG => $this->addCustomerTagActionMock,
                self::ACTION_STOP_FLOW => $this->stopFlowActionMock,
            ]
        );
    }

    /**
     * @param array<int, mixed> $actionSequencesExecuted
     * @param array<int, mixed> $actionSequencesTrueCase
     * @param array<int, mixed> $actionSequencesFalseCase
     *
     * @throws ExecuteSequenceException
     */
    #[DataProvider('actionsProvider')]
    public function testExecuteFlows(array $actionSequencesExecuted, array $actionSequencesTrueCase, array $actionSequencesFalseCase, ?string $appAction = null): void
    {
        $ids = new IdsCollection();
        $actionSequences = [];
        if ($actionSequencesExecuted !== []) {
            foreach ($actionSequencesExecuted as $actionSequenceExecuted) {
                $actionSequence = new ActionSequence();
                $actionSequence->sequenceId = $ids->get($actionSequenceExecuted);
                $actionSequence->action = $actionSequenceExecuted;

                $actionSequences[] = $actionSequence;
            }
        }

        $context = Context::createCLIContext();
        if ($actionSequencesTrueCase !== []) {
            $condition = new IfSequence();
            $condition->sequenceId = $ids->get('true_case');
            $condition->ruleId = $ids->get('ruleId');

            $context = Context::createCLIContext();
            $context->setRuleIds([$ids->get('ruleId')]);

            foreach ($actionSequencesTrueCase as $actionSequenceTrueCase) {
                $actionSequence = new ActionSequence();
                $actionSequence->sequenceId = $ids->get($actionSequenceTrueCase);
                $actionSequence->action = $actionSequenceTrueCase;

                $condition->trueCase = $actionSequence;
            }

            $actionSequences[] = $condition;
        }

        if ($actionSequencesFalseCase !== []) {
            $condition = new IfSequence();
            $condition->sequenceId = $ids->get('false_case');
            $condition->ruleId = $ids->get('ruleId');

            $context = Context::createCLIContext();

            foreach ($actionSequencesFalseCase as $actionSequenceFalseCase) {
                $actionSequence = new ActionSequence();
                $actionSequence->sequenceId = $ids->get($actionSequenceFalseCase);
                $actionSequence->action = $actionSequenceFalseCase;

                $condition->falseCase = $actionSequence;
            }

            $actionSequences[] = $condition;
        }

        $flow = new Flow($ids->get('flowId'), $actionSequences);

        $storableFlow = new StorableFlow('', $context);

        if ($appAction) {
            $appActionSequence = new ActionSequence();
            $appActionSequence->appFlowActionId = $ids->get('AppActionId');
            $appActionSequence->sequenceId = $ids->get('AppActionSequenceId');
            $appActionSequence->action = 'app.action';
            $actionSequences[] = $appActionSequence;
            $flow = new Flow($ids->get('flowId'), $actionSequences);
            $this->appFlowActionProviderMock->expects(static::once())
                ->method('getWebhookPayloadAndHeaders')->willReturn([
                    'headers' => [],
                    'payload' => [],
                ]);
            $invocations = static::exactly(3);
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
        }

        if (\in_array(self::ACTION_ADD_ORDER_TAG, array_merge_recursive($actionSequencesExecuted, $actionSequencesTrueCase, $actionSequencesFalseCase), true)) {
            $this->addOrderTagActionMock->expects(static::once())->method('handleFlow')->with($storableFlow);
        } else {
            $this->addOrderTagActionMock->expects(static::never())->method('handleFlow');
        }

        if (\in_array(self::ACTION_ADD_CUSTOMER_TAG, array_merge_recursive($actionSequencesExecuted, $actionSequencesTrueCase, $actionSequencesFalseCase), true)) {
            $this->addCustomerTagActionMock->expects(static::once())->method('handleFlow')->with($storableFlow);
        } else {
            $this->addCustomerTagActionMock->expects(static::never())->method('handleFlow');
        }

        if (\in_array(self::ACTION_STOP_FLOW, array_merge_recursive($actionSequencesExecuted, $actionSequencesTrueCase, $actionSequencesFalseCase), true)) {
            $this->stopFlowActionMock->expects(static::once())->method('handleFlow')->with($storableFlow);
        } else {
            $this->stopFlowActionMock->expects(static::never())->method('handleFlow');
        }

        $this->flowExecutor->executeFlows(
            [
                [
                    'id' => $ids->get('flowId'),
                    'name' => 'flow',
                    'payload' => $flow,
                ],
            ],
            $storableFlow,
        );
    }

    public function testExecuteFlowsLogsSequenceExceptions(): void
    {
        $ids = new IdsCollection();
        $actionSequences = [new ActionSequence()];
        $flow = new Flow($ids->get('flowId'), $actionSequences);
        $storableFlow = new StorableFlow('', Context::createCLIContext());

        $this->eventDispatcherMock->method('dispatch')
            ->willThrowException(new ExecuteSequenceException(
                'some-flow-id',
                'some-sequence-id',
                'error',
            ));

        $this->loggerMock->expects(static::once())
            ->method('error')
            ->with(
                'Could not execute flow with error message:' . "\n"
                . 'Flow name: flow' . "\n"
                . 'Flow id: ' . $ids->get('flowId') . "\n"
                . 'Sequence id: some-sequence-id' . "\n"
                . 'error' . "\n"
                . 'Error Code: 0' . "\n",
            );

        $this->flowExecutor->executeFlows(
            [
                [
                    'id' => $ids->get('flowId'),
                    'name' => 'flow',
                    'payload' => $flow,
                ],
            ],
            $storableFlow,
        );
    }

    public function testExecuteFlowsLogsGenericExceptions(): void
    {
        $ids = new IdsCollection();
        $actionSequences = [new ActionSequence()];
        $flow = new Flow($ids->get('flowId'), $actionSequences);
        $storableFlow = new StorableFlow('', Context::createCLIContext());

        $this->eventDispatcherMock->method('dispatch')
            ->willThrowException(new \Exception('error'));

        $this->loggerMock->expects(static::once())
            ->method('error')
            ->with(
                'Could not execute flow with error message:' . "\n"
                . 'Flow name: flow' . "\n"
                . 'Flow id: ' . $ids->get('flowId') . "\n"
                . 'error' . "\n"
                . 'Error Code: 0' . "\n",
            );

        $this->flowExecutor->executeFlows(
            [
                [
                    'id' => $ids->get('flowId'),
                    'name' => 'flow',
                    'payload' => $flow,
                ],
            ],
            $storableFlow,
        );
    }

    /**
     * @param array<int, mixed> $actionSequencesExecuted
     * @param array<int, mixed> $actionSequencesTrueCase
     * @param array<int, mixed> $actionSequencesFalseCase
     *
     * @throws ExecuteSequenceException
     */
    #[DataProvider('actionsProvider')]
    public function testExecute(array $actionSequencesExecuted, array $actionSequencesTrueCase, array $actionSequencesFalseCase, ?string $appAction = null): void
    {
        $ids = new IdsCollection();
        $actionSequences = [];
        if ($actionSequencesExecuted !== []) {
            foreach ($actionSequencesExecuted as $actionSequenceExecuted) {
                $actionSequence = new ActionSequence();
                $actionSequence->sequenceId = $ids->get($actionSequenceExecuted);
                $actionSequence->action = $actionSequenceExecuted;

                $actionSequences[] = $actionSequence;
            }
        }

        $context = Context::createCLIContext();
        if ($actionSequencesTrueCase !== []) {
            $condition = new IfSequence();
            $condition->sequenceId = $ids->get('true_case');
            $condition->ruleId = $ids->get('ruleId');

            $context = Context::createCLIContext();
            $context->setRuleIds([$ids->get('ruleId')]);

            foreach ($actionSequencesTrueCase as $actionSequenceTrueCase) {
                $actionSequence = new ActionSequence();
                $actionSequence->sequenceId = $ids->get($actionSequenceTrueCase);
                $actionSequence->action = $actionSequenceTrueCase;

                $condition->trueCase = $actionSequence;
            }

            $actionSequences[] = $condition;
        }

        if ($actionSequencesFalseCase !== []) {
            $condition = new IfSequence();
            $condition->sequenceId = $ids->get('false_case');
            $condition->ruleId = $ids->get('ruleId');

            $context = Context::createCLIContext();

            foreach ($actionSequencesFalseCase as $actionSequenceFalseCase) {
                $actionSequence = new ActionSequence();
                $actionSequence->sequenceId = $ids->get($actionSequenceFalseCase);
                $actionSequence->action = $actionSequenceFalseCase;

                $condition->falseCase = $actionSequence;
            }

            $actionSequences[] = $condition;
        }

        $flow = new Flow($ids->get('flowId'), $actionSequences);

        $storableFlow = new StorableFlow('', $context);

        if ($appAction) {
            $appActionSequence = new ActionSequence();
            $appActionSequence->appFlowActionId = $ids->get('AppActionId');
            $appActionSequence->sequenceId = $ids->get('AppActionSequenceId');
            $appActionSequence->action = 'app.action';
            $actionSequences[] = $appActionSequence;
            $flow = new Flow($ids->get('flowId'), $actionSequences);
            $this->appFlowActionProviderMock->expects(static::once())
                ->method('getWebhookPayloadAndHeaders')->willReturn([
                    'headers' => [],
                    'payload' => [],
                ]);
            $invocations = static::exactly(3);
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
        }

        if (\in_array(self::ACTION_ADD_ORDER_TAG, array_merge_recursive($actionSequencesExecuted, $actionSequencesTrueCase, $actionSequencesFalseCase), true)) {
            $this->addOrderTagActionMock->expects(static::once())->method('handleFlow')->with($storableFlow);
        } else {
            $this->addOrderTagActionMock->expects(static::never())->method('handleFlow');
        }

        if (\in_array(self::ACTION_ADD_CUSTOMER_TAG, array_merge_recursive($actionSequencesExecuted, $actionSequencesTrueCase, $actionSequencesFalseCase), true)) {
            $this->addCustomerTagActionMock->expects(static::once())->method('handleFlow')->with($storableFlow);
        } else {
            $this->addCustomerTagActionMock->expects(static::never())->method('handleFlow');
        }

        if (\in_array(self::ACTION_STOP_FLOW, array_merge_recursive($actionSequencesExecuted, $actionSequencesTrueCase, $actionSequencesFalseCase), true)) {
            $this->stopFlowActionMock->expects(static::once())->method('handleFlow')->with($storableFlow);
        } else {
            $this->stopFlowActionMock->expects(static::never())->method('handleFlow');
        }

        $this->flowExecutor->execute($flow, $storableFlow);
    }

    public static function actionsProvider(): \Generator
    {
        yield 'Single action executed' => [
            [
                self::ACTION_ADD_ORDER_TAG,
            ],
            [],
            [],
        ];

        yield 'Multiple actions executed' => [
            [
                self::ACTION_ADD_ORDER_TAG,
                self::ACTION_ADD_CUSTOMER_TAG,
                self::ACTION_STOP_FLOW,
            ],
            [],
            [],
        ];

        yield 'Action executed with true case' => [
            [],
            [
                self::ACTION_ADD_ORDER_TAG,
            ],
            [],
        ];

        yield 'Action executed with false case' => [
            [],
            [],
            [
                self::ACTION_ADD_ORDER_TAG,
            ],
        ];

        yield 'Action executed from App' => [
            [],
            [],
            [],
            'app.action',
        ];
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

        static::assertEquals($trueCaseSequence, $flow->getFlowState()->currentSequence);
    }

    public function testActionExecutedInTransactionWhenItImplementsTransactional(): void
    {
        $ids = new IdsCollection();
        $action = new class extends FlowAction implements TransactionalAction {
            public bool $handled = false;

            public function requirements(): array
            {
                return [];
            }

            public function handleFlow(StorableFlow $flow): void
            {
                $this->handled = true;
            }

            public static function getName(): string
            {
                return 'transactional-action';
            }
        };

        $actionSequence = new ActionSequence();
        $actionSequence->sequenceId = $ids->get($action::class);
        $actionSequence->action = $action::class;

        $this->connectionMock->expects(static::once())
            ->method('beginTransaction');

        $this->connectionMock->expects(static::once())
            ->method('commit');

        $flow = new StorableFlow('some-flow', Context::createCLIContext());
        $flow->setFlowState(new FlowState());

        $this->flowExecutor = new FlowExecutor(
            $this->eventDispatcherMock,
            $this->appFlowActionProviderMock,
            $this->ruleLoaderMock,
            $this->scopeBuilderMock,
            $this->connectionMock,
            new ExtensionDispatcher($this->eventDispatcherMock),
            $this->loggerMock,
            [
                $action::class => $action,
            ],
        );
        $this->flowExecutor->executeAction($actionSequence, $flow);

        static::assertTrue($action->handled);
    }

    public function testTransactionCommitFailureExceptionIsWrapped(): void
    {
        $ids = new IdsCollection();
        $action = new class extends FlowAction implements TransactionalAction {
            public function requirements(): array
            {
                return [];
            }

            public function handleFlow(StorableFlow $flow): void
            {
            }

            public static function getName(): string
            {
                return 'transactional-action';
            }
        };

        $actionSequence = new ActionSequence();
        $actionSequence->sequenceId = $ids->get($action::class);
        $actionSequence->action = $action::class;

        $this->connectionMock->expects(static::once())
            ->method('beginTransaction');

        $e = new TableNotFoundException(
            new DbalPdoException('Table not found', null, 1146),
            null
        );

        $this->connectionMock->expects(static::once())
            ->method('commit')
            ->willThrowException($e);

        $this->connectionMock->expects(static::once())
            ->method('rollBack');

        $flow = new StorableFlow('some-flow', Context::createCLIContext());
        $flow->setFlowState(new FlowState());

        $this->flowExecutor = new FlowExecutor(
            $this->eventDispatcherMock,
            $this->appFlowActionProviderMock,
            $this->ruleLoaderMock,
            $this->scopeBuilderMock,
            $this->connectionMock,
            new ExtensionDispatcher($this->eventDispatcherMock),
            $this->loggerMock,
            [
                $action::class => $action,
            ],
        );

        try {
            $this->flowExecutor->executeAction($actionSequence, $flow);
            static::fail(FlowException::class . ' should be thrown');
        } catch (FlowException $e) {
            static::assertSame(FlowException::FLOW_ACTION_TRANSACTION_COMMIT_FAILED, $e->getErrorCode());
            static::assertSame('An exception occurred in the driver: Table not found', $e->getPrevious()?->getMessage());
        }
    }

    public function testTransactionAbortExceptionIsWrapped(): void
    {
        $ids = new IdsCollection();
        $action = new class extends FlowAction implements TransactionalAction {
            public function requirements(): array
            {
                return [];
            }

            public function handleFlow(StorableFlow $flow): void
            {
                throw TransactionFailedException::because(new \Exception('broken'));
            }

            public static function getName(): string
            {
                return 'transactional-action';
            }
        };

        $actionSequence = new ActionSequence();
        $actionSequence->sequenceId = $ids->get($action::class);
        $actionSequence->action = $action::class;

        $this->connectionMock->expects(static::once())
            ->method('beginTransaction');

        $this->connectionMock->expects(static::once())
            ->method('rollBack');

        $flow = new StorableFlow('some-flow', Context::createCLIContext());
        $flow->setFlowState(new FlowState());

        $this->flowExecutor = new FlowExecutor(
            $this->eventDispatcherMock,
            $this->appFlowActionProviderMock,
            $this->ruleLoaderMock,
            $this->scopeBuilderMock,
            $this->connectionMock,
            new ExtensionDispatcher($this->eventDispatcherMock),
            $this->loggerMock,
            [
                $action::class => $action,
            ],
        );

        try {
            $this->flowExecutor->executeAction($actionSequence, $flow);
            static::fail(FlowException::class . ' should be thrown');
        } catch (FlowException $e) {
            static::assertSame(FlowException::FLOW_ACTION_TRANSACTION_ABORTED, $e->getErrorCode());
            static::assertSame('Transaction failed because an exception occurred. Exception: broken', $e->getPrevious()?->getMessage());
        }
    }

    public function testTransactionWithUncaughtExceptionIsWrapped(): void
    {
        $ids = new IdsCollection();
        $action = new class extends FlowAction implements TransactionalAction {
            public function requirements(): array
            {
                return [];
            }

            public function handleFlow(StorableFlow $flow): void
            {
                /** @phpstan-ignore-next-line  */
                throw new \Exception('broken');
            }

            public static function getName(): string
            {
                return 'transactional-action';
            }
        };

        $actionSequence = new ActionSequence();
        $actionSequence->sequenceId = $ids->get($action::class);
        $actionSequence->action = $action::class;

        $this->connectionMock->expects(static::once())
            ->method('beginTransaction');

        $this->connectionMock->expects(static::once())
            ->method('rollBack');

        $flow = new StorableFlow('some-flow', Context::createCLIContext());
        $flow->setFlowState(new FlowState());

        $this->flowExecutor = new FlowExecutor(
            $this->eventDispatcherMock,
            $this->appFlowActionProviderMock,
            $this->ruleLoaderMock,
            $this->scopeBuilderMock,
            $this->connectionMock,
            new ExtensionDispatcher($this->eventDispatcherMock),
            $this->loggerMock,
            [
                $action::class => $action,
            ],
        );

        try {
            $this->flowExecutor->executeAction($actionSequence, $flow);
            static::fail(FlowException::class . ' should be thrown');
        } catch (FlowException $e) {
            static::assertSame(FlowException::FLOW_ACTION_TRANSACTION_UNCAUGHT_EXCEPTION, $e->getErrorCode());
            static::assertSame('broken', $e->getPrevious()?->getMessage());
        }
    }

    public function testExtensionIsDispatched(): void
    {
        $flow = new Flow('test', []);
        $storableFlow = new StorableFlow('', Context::createCLIContext());

        $pre = $this->createMock(CallableClass::class);
        $pre->expects(static::once())->method('__invoke');

        $post = $this->createMock(CallableClass::class);
        $post->expects(static::once())->method('__invoke');

        $invocations = static::exactly(2);
        $this->eventDispatcherMock->expects($invocations)
            ->method('dispatch')
            ->with(
                static::callback(
                    static function (object $event, ?string $_ = null) use ($flow, $storableFlow, $invocations, $pre, $post): bool {
                        match ($invocations->numberOfInvocations()) {
                            1 => static::assertEquals(new FlowExecutorExtension($flow, $storableFlow), $event),
                            2 => static::assertEquals(new FlowExecutorExtension($flow, $storableFlow), $event),
                            default => static::fail('Unexpected number of invocations'),
                        };

                        match ($invocations->numberOfInvocations()) {
                            1 => $pre->__invoke(),
                            2 => $post->__invoke(),
                            default => static::fail('Unexpected number of invocations'),
                        };

                        return true;
                    }
                ),
            );

        $this->flowExecutor->execute($flow, $storableFlow);
    }
}
