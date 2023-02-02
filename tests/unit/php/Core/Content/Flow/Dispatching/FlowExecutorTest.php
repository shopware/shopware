<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\Action\AddCustomerTagAction;
use Shopware\Core\Content\Flow\Dispatching\Action\AddOrderTagAction;
use Shopware\Core\Content\Flow\Dispatching\Action\StopFlowAction;
use Shopware\Core\Content\Flow\Dispatching\FlowExecutor;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Struct\ActionSequence;
use Shopware\Core\Content\Flow\Dispatching\Struct\Flow;
use Shopware\Core\Content\Flow\Dispatching\Struct\IfSequence;
use Shopware\Core\Framework\App\Event\AppFlowActionEvent;
use Shopware\Core\Framework\App\FlowAction\AppFlowActionProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestDataCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Dispatching\FlowExecutor
 */
class FlowExecutorTest extends TestCase
{
    private const ACTION_ADD_ORDER_TAG = 'action.add.order.tag';
    private const ACTION_ADD_CUSTOMER_TAG = 'action.add.customer.tag';
    private const ACTION_STOP_FLOW = 'action.stop.flow';

    /**
     * @dataProvider actionsProvider
     *
     * @param array<int, mixed> $actionSequencesExecuted
     * @param array<int, mixed> $actionSequencesTrueCase
     * @param array<int, mixed> $actionSequencesFalseCase
     *
     * @throws \Shopware\Core\Content\Flow\Exception\ExecuteSequenceException
     */
    public function testExecute(array $actionSequencesExecuted, array $actionSequencesTrueCase, array $actionSequencesFalseCase, ?string $appAction = null): void
    {
        Feature::skipTestIfInActive('v6.5.0.0', $this);

        $ids = new TestDataCollection();
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $appFlowActionProvider = $this->createMock(AppFlowActionProvider::class);

        $addOrderTagAction = $this->createMock(AddOrderTagAction::class);
        $addCustomerTagAction = $this->createMock(AddCustomerTagAction::class);
        $stopFlowAction = $this->createMock(StopFlowAction::class);
        $actions = [
            self::ACTION_ADD_ORDER_TAG => $addOrderTagAction,
            self::ACTION_ADD_CUSTOMER_TAG => $addCustomerTagAction,
            self::ACTION_STOP_FLOW => $stopFlowAction,
        ];

        $flow = $this->createMock(Flow::class);

        $actionSequences = [];
        if (!empty($actionSequencesExecuted)) {
            foreach ($actionSequencesExecuted as $actionSequenceExecuted) {
                $actionSequence = $this->createMock(ActionSequence::class);
                $actionSequence->sequenceId = $ids->get($actionSequenceExecuted);
                $actionSequence->action = $actionSequenceExecuted;

                $actionSequences[] = $actionSequence;
            }
        }

        $context = Context::createDefaultContext();
        if (!empty($actionSequencesTrueCase)) {
            $condition = $this->createMock(IfSequence::class);
            $condition->sequenceId = $ids->get('true_case');
            $condition->ruleId = $ids->get('ruleId');

            $context = $this->createMock(Context::class);
            $context->expects(static::exactly(\count($actionSequencesTrueCase)))->method('getRuleIds')->willReturn([$ids->get('ruleId')]);

            foreach ($actionSequencesTrueCase as $actionSequenceTrueCase) {
                $actionSequence = $this->createMock(ActionSequence::class);
                $actionSequence->sequenceId = $ids->get($actionSequenceTrueCase);
                $actionSequence->action = $actionSequenceTrueCase;

                $condition->trueCase = $actionSequence;
            }

            $actionSequences[] = $condition;
        }

        if (!empty($actionSequencesFalseCase)) {
            $condition = $this->createMock(IfSequence::class);
            $condition->sequenceId = $ids->get('false_case');
            $condition->ruleId = $ids->get('ruleId');

            $context = $this->createMock(Context::class);
            $context->expects(static::exactly(\count($actionSequencesFalseCase)))->method('getRuleIds')->willReturn([]);

            foreach ($actionSequencesFalseCase as $actionSequenceFalseCase) {
                $actionSequence = $this->createMock(ActionSequence::class);
                $actionSequence->sequenceId = $ids->get($actionSequenceFalseCase);
                $actionSequence->action = $actionSequenceFalseCase;

                $condition->falseCase = $actionSequence;
            }

            $actionSequences[] = $condition;
        }

        if ($appAction) {
            $appActionSequence = $this->createMock(ActionSequence::class);
            $appActionSequence->appFlowActionId = $ids->get('AppActionId');
            $appActionSequence->sequenceId = $ids->get('AppActionSequenceId');
            $appActionSequence->action = 'app.action';
            $appFlowActionProvider->expects(static::once())->method('getWebhookPayloadAndHeaders')->willReturn([
                'headers' => [],
                'payload' => [],
            ]);
            $eventDispatcher->expects(static::once())->method('dispatch')->with(
                new AppFlowActionEvent('app.action', [], [], ),
                'app.action'
            );
            $actionSequences[] = $appActionSequence;
        }

        $flow->expects(static::once())->method('getId')->willReturn($ids->get('flowId'));
        $flow->expects(static::once())->method('getSequences')->willReturn($actionSequences);

        $storableFlow = $this->createMock(StorableFlow::class);
        $call = \count($actionSequencesTrueCase) ?: \count($actionSequencesFalseCase);
        $storableFlow->expects(static::exactly($call))
            ->method('getContext')
            ->willReturn($context);

        if (\in_array(self::ACTION_ADD_ORDER_TAG, array_merge_recursive($actionSequencesExecuted, $actionSequencesTrueCase, $actionSequencesFalseCase), true)) {
            $addOrderTagAction->expects(static::once())->method('handleFlow')->with($storableFlow);
        } else {
            $addOrderTagAction->expects(static::never())->method('handleFlow');
        }

        if (\in_array(self::ACTION_ADD_CUSTOMER_TAG, array_merge_recursive($actionSequencesExecuted, $actionSequencesTrueCase, $actionSequencesFalseCase), true)) {
            $addCustomerTagAction->expects(static::once())->method('handleFlow')->with($storableFlow);
        } else {
            $addCustomerTagAction->expects(static::never())->method('handleFlow');
        }

        if (\in_array(self::ACTION_STOP_FLOW, array_merge_recursive($actionSequencesExecuted, $actionSequencesTrueCase, $actionSequencesFalseCase), true)) {
            $stopFlowAction->expects(static::once())->method('handleFlow')->with($storableFlow);
        } else {
            $stopFlowAction->expects(static::never())->method('handleFlow');
        }

        $flowExecutor = new FlowExecutor($eventDispatcher, $appFlowActionProvider, $actions);
        $flowExecutor->execute($flow, $storableFlow);
    }

    public function actionsProvider(): \Generator
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
}
