<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
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
    private SalesChannelContext $context;

    private EventDispatcherInterface&Stub $eventDispatcher;

    private LineItemFactoryInterface&Stub $factory;

    protected function setUp(): void
    {
        $this->factory = static::createStub(LineItemFactoryInterface::class);
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $this->context = Generator::generateSalesChannelContext();
    }

    public function testCreate(): void
    {
        $data = ['id' => 'test', 'type' => 'product', 'referencedId' => 'test'];
        $lineItem = new LineItem('test', LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 1);
        $factory = $this->createMock(LineItemFactoryInterface::class);
        $factory->expects($this->once())->method('supports')->with('product')->willReturn(true);
        $factory->expects($this->once())->method('create')->with($data, $this->context)->willReturn($lineItem);
        $service = $this->buildService(factory: $factory);
        $returnedLineItem = $service->create($data, $this->context);
        static::assertSame($lineItem, $returnedLineItem);
    }

    public function testCreateWithNoId(): void
    {
        $data = ['type' => 'product', 'referencedId' => 'test'];
        $lineItem = new LineItem('test', LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 1);
        $factory = $this->createMock(LineItemFactoryInterface::class);
        $factory->expects($this->once())->method('supports')->with('product')->willReturn(true);
        $factory->expects($this->once())->method('create')->willReturn($lineItem);
        $service = $this->buildService(factory: $factory);
        $returnedLineItem = $service->create($data, $this->context);
        static::assertSame($lineItem, $returnedLineItem);
    }

    public function testCreateWithUnsupportedType(): void
    {
        $data = ['id' => 'test', 'type' => 'product', 'referencedId' => 'test'];
        $factory = $this->createMock(LineItemFactoryInterface::class);
        $factory->expects($this->once())->method('supports')->with('product')->willReturn(false);
        $service = $this->buildService(factory: $factory);
        $this->expectException(CartException::class);
        $service->create($data, $this->context);
    }

    public function testUpdate(): void
    {
        $id = Uuid::randomHex();
        $lineItem = new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 1);

        $cart = new Cart('test');
        $cart->add($lineItem);

        $factory = $this->createMock(LineItemFactoryInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $factory->expects($this->once())->method('supports')->with('product')->willReturn(true);
        $eventDispatcher->expects($this->never())->method('dispatch');
        $factory->expects($this->once())->method('update')->with($lineItem, ['id' => $id, 'type' => LineItem::PRODUCT_LINE_ITEM_TYPE], $this->context);

        $service = $this->buildService(factory: $factory, eventDispatcher: $eventDispatcher);
        $service->update($cart, ['id' => $id], $this->context);
    }

    public function testUpdateWithMissingLineItem(): void
    {
        $this->expectException(CartException::class);
        $this->buildService()->update(new Cart('test'), ['id' => Uuid::randomHex(), 'quantity' => 2], $this->context);
    }

    public function testUpdateLineItem(): void
    {
        $id = Uuid::randomHex();
        $lineItem = new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 1);
        $lineItem->setStackable(true);

        $cart = new Cart('test');

        $factory = $this->createMock(LineItemFactoryInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $factory->expects($this->once())->method('supports')->with('product')->willReturn(true);
        $eventDispatcher->expects($this->never())->method('dispatch');
        $factory->expects($this->once())->method('update')->with($lineItem, ['id' => $id, 'type' => LineItem::PRODUCT_LINE_ITEM_TYPE], $this->context);

        $service = $this->buildService(factory: $factory, eventDispatcher: $eventDispatcher);
        $service->updateLineItem($cart, ['id' => $id], $lineItem, $this->context);
    }

    public function testUpdateLineItemWithQuantityEvent(): void
    {
        $id = Uuid::randomHex();
        $lineItem = new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 1);
        $lineItem->setStackable(true);

        $cart = new Cart('test');

        $factory = $this->createMock(LineItemFactoryInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $factory->expects($this->once())->method('supports')->with('product')->willReturn(true);
        $eventDispatcher->expects($this->once())->method('dispatch');
        $factory->expects($this->once())->method('update')->with($lineItem, ['id' => $id, 'quantity' => 2, 'type' => LineItem::PRODUCT_LINE_ITEM_TYPE], $this->context);

        $service = $this->buildService(factory: $factory, eventDispatcher: $eventDispatcher);
        $service->updateLineItem($cart, ['id' => $id, 'quantity' => 2], $lineItem, $this->context);
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

        $factory = $this->createMock(LineItemFactoryInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $factory->expects($this->once())->method('supports')->with('product')->willReturn(true);
        $factory->expects($this->once())->method('update')->with($lineItem, ['id' => $id, 'quantity' => $newQuantity, 'type' => LineItem::PRODUCT_LINE_ITEM_TYPE], $this->context);

        $eventDispatcher->expects($this->once())->method('dispatch');

        $eventDispatcher->expects($this->once())->method('dispatch')->with(
            static::callback(static function (BeforeLineItemQuantityChangedEvent $event) use ($beforeUpdateQuantity) {
                static::assertSame($beforeUpdateQuantity, $event->getBeforeUpdateQuantity());

                return true;
            })
        );

        $service = $this->buildService(factory: $factory, eventDispatcher: $eventDispatcher);
        $service->updateLineItem($cart, ['id' => $id, 'quantity' => $newQuantity], $lineItem, $this->context);
    }

    public function testUpdateLineItemWithUnsupportedType(): void
    {
        $id = Uuid::randomHex();
        $lineItem = new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 1);

        $cart = new Cart('test');
        $cart->add($lineItem);

        $factory = $this->createMock(LineItemFactoryInterface::class);
        $factory->expects($this->once())->method('supports')->with('product')->willReturn(false);
        $service = $this->buildService(factory: $factory);
        $this->expectException(CartException::class);
        $service->update($cart, ['id' => $id, 'quantity' => 2], $this->context);
    }

    private function buildService(
        ?LineItemFactoryInterface $factory = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): LineItemFactoryRegistry {
        return new LineItemFactoryRegistry(
            [$factory ?? $this->factory],
            static::createStub(DataValidator::class),
            $eventDispatcher ?? $this->eventDispatcher
        );
    }
}
