<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemQuantityChangedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\LineItemFactoryInterface;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LineItemFactoryRegistry::class)]
class LineItemFactoryRegistryTest extends TestCase
{
    private LineItemFactoryRegistry $service;

    private SalesChannelContext $context;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private LineItemFactoryInterface&MockObject $factory;

    protected function setUp(): void
    {
        $this->service = new LineItemFactoryRegistry(
            [$this->factory = $this->createMock(LineItemFactoryInterface::class)],
            $this->createMock(DataValidator::class),
            $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class)
        );
        $this->context = Generator::createSalesChannelContext();
    }

    public function testCreate(): void
    {
        $data = ['id' => 'test', 'type' => 'product', 'referencedId' => 'test'];
        $lineItem = new LineItem('test', LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 1);
        $this->factory->expects(static::once())->method('supports')->with('product')->willReturn(true);
        $this->factory->expects(static::once())->method('create')->with($data, $this->context)->willReturn($lineItem);
        $returnedLineItem = $this->service->create($data, $this->context);
        static::assertSame($lineItem, $returnedLineItem);
    }

    public function testCreateWithNoId(): void
    {
        $data = ['type' => 'product', 'referencedId' => 'test'];
        $lineItem = new LineItem('test', LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 1);
        $this->factory->expects(static::once())->method('supports')->with('product')->willReturn(true);
        $this->factory->expects(static::once())->method('create')->willReturn($lineItem);
        $returnedLineItem = $this->service->create($data, $this->context);
        static::assertSame($lineItem, $returnedLineItem);
    }

    public function testCreateWithUnsupportedType(): void
    {
        $data = ['id' => 'test', 'type' => 'product', 'referencedId' => 'test'];
        $this->factory->expects(static::once())->method('supports')->with('product')->willReturn(false);
        $this->expectException(CartException::class);
        $this->service->create($data, $this->context);
    }

    public function testUpdate(): void
    {
        $id = Uuid::randomHex();
        $lineItem = new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 1);

        $cart = new Cart('test');
        $cart->add($lineItem);

        $this->factory->expects(static::once())->method('supports')->with('product')->willReturn(true);
        $this->eventDispatcher->expects(static::never())->method('dispatch');
        $this->factory->expects(static::once())->method('update')->with($lineItem, ['id' => $id, 'type' => LineItem::PRODUCT_LINE_ITEM_TYPE], $this->context);

        $this->service->update($cart, ['id' => $id], $this->context);
    }

    public function testUpdateWithMissingLineItem(): void
    {
        $this->expectException(CartException::class);
        $this->service->update(new Cart('test'), ['id' => Uuid::randomHex(), 'quantity' => 2], $this->context);
    }

    public function testUpdateLineItem(): void
    {
        $id = Uuid::randomHex();
        $lineItem = new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 1);
        $lineItem->setStackable(true);

        $cart = new Cart('test');

        $this->factory->expects(static::once())->method('supports')->with('product')->willReturn(true);
        $this->eventDispatcher->expects(static::never())->method('dispatch');
        $this->factory->expects(static::once())->method('update')->with($lineItem, ['id' => $id, 'type' => LineItem::PRODUCT_LINE_ITEM_TYPE], $this->context);

        $this->service->updateLineItem($cart, ['id' => $id], $lineItem, $this->context);
    }

    public function testUpdateLineItemWithQuantityEvent(): void
    {
        $id = Uuid::randomHex();
        $lineItem = new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 1);
        $lineItem->setStackable(true);

        $cart = new Cart('test');

        $this->factory->expects(static::once())->method('supports')->with('product')->willReturn(true);
        $this->eventDispatcher->expects(static::once())->method('dispatch');
        $this->factory->expects(static::once())->method('update')->with($lineItem, ['id' => $id, 'quantity' => 2, 'type' => LineItem::PRODUCT_LINE_ITEM_TYPE], $this->context);

        $this->service->updateLineItem($cart, ['id' => $id, 'quantity' => 2], $lineItem, $this->context);
    }

    public function testUpdateLineItemWithQuantityEventAndSetBeforeUpdateQuantity(): void
    {
        $id = Uuid::randomHex();
        $lineItem = new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 1);
        $lineItem->setStackable(true);

        $cart = new Cart('test');
        $cart->add($lineItem);

        $beforeUpdateQuantity = $lineItem->getQuantity();
        $newQuantity = 2;

        $this->factory->expects(static::once())->method('supports')->with('product')->willReturn(true);
        $this->factory->expects(static::once())->method('update')->with($lineItem, ['id' => $id, 'quantity' => $newQuantity, 'type' => LineItem::PRODUCT_LINE_ITEM_TYPE], $this->context);

        $this->eventDispatcher->expects(static::once())->method('dispatch');

        $this->eventDispatcher->expects(static::once())->method('dispatch')->with(
            static::callback(function (BeforeLineItemQuantityChangedEvent $event) use ($beforeUpdateQuantity) {
                static::assertSame($beforeUpdateQuantity, $event->getBeforeUpdateQuantity());

                return true;
            })
        );

        $this->service->updateLineItem($cart, ['id' => $id, 'quantity' => $newQuantity], $lineItem, $this->context);
    }

    public function testUpdateLineItemWithUnsupportedType(): void
    {
        $id = Uuid::randomHex();
        $lineItem = new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 1);

        $cart = new Cart('test');
        $cart->add($lineItem);

        $this->factory->expects(static::once())->method('supports')->with('product')->willReturn(false);
        $this->expectException(CartException::class);
        $this->service->update($cart, ['id' => $id, 'quantity' => 2], $this->context);
    }
}
