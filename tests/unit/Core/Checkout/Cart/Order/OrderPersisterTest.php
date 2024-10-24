<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartSerializationCleaner;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Cart\Order\OrderPersister
 */
#[Package('checkout')]
class OrderPersisterTest extends TestCase
{
    public function testPersistWithCartCleaner(): void
    {
        $context = Generator::createSalesChannelContext();

        $lineItem = new LineItem('hatoken', 'product');
        $lineItem->setPayloadValue('customFields', ['test' => 'test']);

        $cart = new Cart('hatoken');
        $cart->add($lineItem);

        static::assertNotNull($cart->getLineItems()->first());

        static::assertSame($cart->getLineItems()->first()->getPayloadValue('customFields'), ['test' => 'test']);

        $order = new OrderEntity();
        $order->assign([
            'id' => 'test-id',
        ]);

        $cartSerializationCleaner = new CartSerializationCleaner(
            $this->createMock(Connection::class),
            $this->createMock(EventDispatcherInterface::class)
        );

        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->expects(static::once())
            ->method('convertToOrder')
            ->willReturn(['id' => $order->getId()]);

        $persister = new OrderPersister(
            $this->createMock(EntityRepository::class),
            $orderConverter,
            $cartSerializationCleaner,
        );

        $persister->persist($cart, $context);

        static::assertNotNull($cart->getLineItems()->first());

        static::assertSame($cart->getLineItems()->first()->getPayloadValue('customFields'), []);
    }
}
