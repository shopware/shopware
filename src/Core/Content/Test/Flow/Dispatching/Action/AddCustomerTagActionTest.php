<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Flow\Dispatching\Action;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\Action\AddCustomerTagAction;
use Shopware\Core\Content\Flow\Dispatching\FlowState;
use Shopware\Core\Content\Test\Flow\fixtures\CustomerAwareEvent;
use Shopware\Core\Content\Test\Flow\fixtures\RawFlowEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\DelayAware;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Test\IdsCollection;

/**
 * @internal
 */
class AddCustomerTagActionTest extends TestCase
{
    public function testRequirements(): void
    {
        $action = new AddCustomerTagAction($this->createMock(EntityRepositoryInterface::class));

        static::assertSame(
            [CustomerAware::class, DelayAware::class],
            $action->requirements()
        );
    }

    public function testSubscribedEvents(): void
    {
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
     * @dataProvider actionProvider
     */
    public function testAction(FlowEvent $event, array $expected): void
    {
        $repository = $this->createMock(EntityRepositoryInterface::class);

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

    public function actionProvider(): \Generator
    {
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

    private static function keys(array $ids): array
    {
        $return = \array_combine($ids, \array_fill(0, \count($ids), true));

        static::assertIsArray($return);

        return $return;
    }
}
