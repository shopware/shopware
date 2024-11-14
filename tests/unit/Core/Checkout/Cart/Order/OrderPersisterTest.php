<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\CartSerializationCleaner;
use Shopware\Core\Checkout\Cart\Error\GenericCartError;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(OrderPersister::class)]
#[Package('checkout')]
class OrderPersisterTest extends TestCase
{
    public function testPersist(): void
    {
        $context = Generator::createSalesChannelContext();

        $cart = new Cart('hatoken');
        $cart->add(new LineItem('hatoken', 'product'));

        $order = new OrderEntity();
        $order->assign([
            'id' => 'test-id',
        ]);

        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->expects(static::once())
            ->method('convertToOrder')
            ->with($cart, $context, static::equalTo(new OrderConversionContext()))
            ->willReturn(['id' => $order->getId()]);

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects(static::once())
            ->method('create')
            ->with([['id' => $order->getId()]], $context->getContext());

        $persister = new OrderPersister($repo, $orderConverter, $this->createMock(CartSerializationCleaner::class));
        $id = $persister->persist($cart, $context);

        static::assertSame('test-id', $id);
    }

    public function testWithBlockingCart(): void
    {
        $context = Generator::createSalesChannelContext();

        $cart = new Cart('hatoken');
        $cart->add(new LineItem('hatoken', 'product'));
        $cart->addErrors(
            new GenericCartError(
                'test',
                'test',
                [],
                1,
                true,
                true,
                true
            )
        );

        $persister = new OrderPersister(
            $this->createMock(EntityRepository::class),
            $this->createMock(OrderConverter::class),
            $this->createMock(CartSerializationCleaner::class),
        );

        $this->expectException(CartException::class);

        $persister->persist($cart, $context);
    }

    public function testPersistWithoutCustomer(): void
    {
        $context = Generator::createSalesChannelContext();
        $context->assign(['customer' => null]);

        $cart = new Cart('hatoken');
        $cart->add(new LineItem('hatoken', 'product'));

        $persister = new OrderPersister(
            $this->createMock(EntityRepository::class),
            $this->createMock(OrderConverter::class),
            $this->createMock(CartSerializationCleaner::class),
        );

        $this->expectException(CartException::class);
        $this->expectExceptionMessage('Customer is not logged in.');

        $persister->persist($cart, $context);
    }

    public function testPersistWithEmptyCart(): void
    {
        $context = Generator::createSalesChannelContext();

        $cart = new Cart('hatoken');

        $persister = new OrderPersister(
            $this->createMock(EntityRepository::class),
            $this->createMock(OrderConverter::class),
            $this->createMock(CartSerializationCleaner::class),
        );

        $this->expectException(CartException::class);
        $this->expectExceptionMessage('Cart is empty');

        $persister->persist($cart, $context);
    }

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
