<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Action;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\Action\AddCustomerTagAction;
use Shopware\Core\Content\Flow\Dispatching\FlowState;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Test\Flow\fixtures\CustomerAwareEvent;
use Shopware\Core\Content\Test\Flow\fixtures\RawFlowEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 * @covers \Shopware\Core\Content\Flow\Dispatching\Action\AddCustomerTagAction
 */
class AddCustomerTagActionTest extends TestCase
{
    /**
     * @var MockObject|EntityRepository
     */
    private $repository;

    private AddCustomerTagAction $action;

    /**
     * @var MockObject|StorableFlow
     */
    private $flow;

    public function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->action = new AddCustomerTagAction($this->repository);

        $this->flow = $this->createMock(StorableFlow::class);
    }

    public function testRequirements(): void
    {
        static::assertSame(
            [CustomerAware::class],
            $this->action->requirements()
        );
    }

    public function testSubscribedEvents(): void
    {
        if (Feature::isActive('v6.5.0.0')) {
            static::assertSame(
                [],
                AddCustomerTagAction::getSubscribedEvents()
            );

            return;
        }

        static::assertSame(
            ['action.add.customer.tag' => 'handle'],
            AddCustomerTagAction::getSubscribedEvents()
        );
    }

    public function testName(): void
    {
        static::assertSame('action.add.customer.tag', AddCustomerTagAction::getName());
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $expected
     *
     * @dataProvider actionExecutedProvider
     */
    public function testActionExecuted(array $config, array $expected): void
    {
        $this->flow->expects(static::exactly(2))->method('getStore')->willReturn(Uuid::randomHex());
        $this->flow->expects(static::once())->method('hasStore')->willReturn(true);
        $this->flow->expects(static::once())->method('getConfig')->willReturn($config);
        $customerId = $this->flow->getStore('customerId');

        $this->repository->expects(static::once())
            ->method('update')
            ->with([['id' => $customerId, 'tags' => $expected]]);

        $this->action->handleFlow($this->flow);
    }

    /**
     * @param array<string, mixed> $expected
     * @dataProvider actionProvider
     */
    public function testActionWithHandle(FlowEvent $event, array $expected): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);

        $repository = $this->createMock(EntityRepository::class);

        if (!empty($expected)) {
            static::assertInstanceOf(CustomerAwareEvent::class, $event->getEvent());

            $customerId = $event->getEvent()->getCustomerId();

            $repository->expects(static::once())
                ->method('update')
                ->with([['id' => $customerId, 'tags' => $expected]]);
        } else {
            $repository->expects(static::never())
                ->method('update');
        }

        $action = new AddCustomerTagAction($repository);

        $action->handle($event);
    }

    public function testActionWithNotAware(): void
    {
        $this->flow->expects(static::once())->method('hasStore')->willReturn(false);
        $this->flow->expects(static::never())->method('getStore');
        $this->repository->expects(static::never())->method('update');

        $this->action->handleFlow($this->flow);
    }

    public function testActionWithEmptyConfig(): void
    {
        $this->flow->expects(static::once())->method('hasStore')->willReturn(true);
        $this->flow->expects(static::exactly(1))->method('getStore')->willReturn(Uuid::randomHex());
        $this->flow->expects(static::once())->method('getConfig')->willReturn([]);
        $this->repository->expects(static::never())->method('update');

        $this->action->handleFlow($this->flow);
    }

    public function actionExecutedProvider(): \Generator
    {
        $ids = new TestDataCollection();

        yield 'Test with single tag' => [
            ['tagIds' => self::keys([$ids->get('tag-1')])],
            $ids->getIdArray(['tag-1']),
        ];

        yield 'Test with multiple tags' => [
            ['tagIds' => self::keys($ids->getList(['tag-1', 'tag-2']))],
            $ids->getIdArray(['tag-1', 'tag-2']),
        ];
    }

    public function actionProvider(): \Generator
    {
        if (Feature::isActive('v6.5.0.0')) {
            return;
        }

        $ids = new IdsCollection();

        $awareState = new FlowState(new CustomerAwareEvent($ids->get('customer')));

        $notAware = new FlowState(new RawFlowEvent());

        yield 'Test with single tag' => [
            new FlowEvent('foo', $awareState, ['tagIds' => self::keys([$ids->get('tag-1')])]),
            $ids->getIdArray(['tag-1']),
        ];

        yield 'Test with multiple tags' => [
            new FlowEvent('foo', $awareState, ['tagIds' => self::keys($ids->getList(['tag-1', 'tag-2']))]),
            $ids->getIdArray(['tag-1', 'tag-2']),
        ];

        yield 'Test with empty tagIds' => [
            new FlowEvent('foo', $awareState, ['tagIds' => []]),
            [],
        ];

        yield 'Test not customer aware' => [
            new FlowEvent('foo', $notAware, ['tagIds' => self::keys([$ids->get('tag-1')])]),
            [],
        ];

        yield 'Test aware event without config' => [
            new FlowEvent('foo', $awareState, []),
            [],
        ];

        yield 'Test not aware event without config' => [
            new FlowEvent('foo', $notAware, []),
            [],
        ];
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string, mixed>
     */
    private static function keys(array $ids): array
    {
        $return = \array_combine($ids, \array_fill(0, \count($ids), true));

        static::assertIsArray($return);

        return $return;
    }
}
