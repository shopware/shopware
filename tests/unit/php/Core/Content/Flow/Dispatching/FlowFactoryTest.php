<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\FlowFactory;
use Shopware\Core\Content\Flow\Dispatching\Storer\OrderStorer;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestDataCollection;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Dispatching\FlowFactory
 */
class FlowFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $ids = new TestDataCollection();
        $order = new OrderEntity();
        $order->setId($ids->get('orderId'));

        $awareEvent = new CheckoutOrderPlacedEvent(Context::createDefaultContext(), $order, Defaults::SALES_CHANNEL);
        $orderStorer = new OrderStorer($this->createMock(EntityRepository::class));
        $flowFactory = new FlowFactory([$orderStorer]);
        $flow = $flowFactory->create($awareEvent);

        static::assertEquals($ids->get('orderId'), $flow->getStore('orderId'));
    }

    public function testRestore(): void
    {
        $ids = new TestDataCollection();
        $order = new OrderEntity();
        $order->setId($ids->get('orderId'));

        $entitySearchResult = $this->createMock(EntitySearchResult::class);
        $entitySearchResult->expects(static::once())
            ->method('get')
            ->willReturn($order);

        $orderRepo = $this->createMock(EntityRepository::class);
        $orderRepo->expects(static::once())
            ->method('search')
            ->willReturn($entitySearchResult);

        $awareEvent = new CheckoutOrderPlacedEvent(Context::createDefaultContext(), $order, Defaults::SALES_CHANNEL);
        $orderStorer = new OrderStorer($orderRepo);
        $flowFactory = new FlowFactory([$orderStorer]);

        $storedData = [
            'orderId' => $ids->get('orderId'),
            'additional_keys' => ['order'],
        ];
        $flow = $flowFactory->restore('checkout.order.placed', $awareEvent->getContext(), $storedData);

        static::assertEquals($ids->get('orderId'), $flow->getData('order')->getId());
    }
}
