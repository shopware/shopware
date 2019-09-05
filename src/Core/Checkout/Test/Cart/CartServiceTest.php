<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\LineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Event\LineItemQuantityChangedEvent;
use Shopware\Core\Checkout\Cart\Event\LineItemRemovedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;

class CartServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testLineItemAddedEventFired(): void
    {
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');

        $dispatcher->addListener(LineItemAddedEvent::class, $listener);

        $cartService = $this->getContainer()->get(CartService::class);

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $cartService->add(
            $cartService->getCart(Uuid::randomHex(), $context),
            new LineItem('test', 'test'),
            $context
        );
    }

    public function testLineItemRemovedEventFired(): void
    {
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');

        $dispatcher->addListener(LineItemRemovedEvent::class, $listener);

        $cartService = $this->getContainer()->get(CartService::class);

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $productId = Uuid::randomHex();
        $product = [
            'id' => $productId,
            'productNumber' => $productId,
            'name' => 'test',
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 18],
            'manufacturer' => ['name' => 'test'],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$product], $context->getContext());

        $lineItem = (new ProductLineItemFactory())->create($productId);

        $cart = $cartService->getCart($context->getToken(), $context);

        $cart = $cartService->add($cart, $lineItem, $context);

        static::assertTrue($cart->has($productId));

        $cart = $cartService->remove($cart, $productId, $context);

        static::assertFalse($cart->has($productId));
    }

    public function testLineItemQuantityChangedEventFired(): void
    {
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');

        $dispatcher->addListener(LineItemQuantityChangedEvent::class, $listener);

        $cartService = $this->getContainer()->get(CartService::class);

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $productId = Uuid::randomHex();
        $product = [
            'id' => $productId,
            'productNumber' => $productId,
            'name' => 'test',
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 18],
            'manufacturer' => ['name' => 'test'],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$product], $context->getContext());

        $lineItem = (new ProductLineItemFactory())->create($productId);

        $cart = $cartService->getCart($context->getToken(), $context);

        $cart = $cartService->add($cart, $lineItem, $context);

        static::assertTrue($cart->has($productId));

        $cartService->changeQuantity($cart, $productId, 100, $context);
    }

    public function testZeroPricedItemsCanBeAddedToCart(): void
    {
        $cartService = $this->getContainer()->get(CartService::class);

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $productId = Uuid::randomHex();
        $product = [
            'id' => $productId,
            'productNumber' => $productId,
            'name' => 'test',
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 0, 'net' => 0, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 18],
            'manufacturer' => ['name' => 'test'],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$product], $context->getContext());

        $lineItem = (new ProductLineItemFactory())->create($productId);

        $cart = $cartService->getCart($context->getToken(), $context);

        $cart = $cartService->add($cart, $lineItem, $context);

        static::assertTrue($cart->has($productId));
        static::assertSame(0.0, $cart->getPrice()->getTotalPrice());
        $calculatedLineItem = $cart->getLineItems()->get($productId);
        static::assertSame(0.0, $calculatedLineItem->getPrice()->getTotalPrice());
        static::assertSame(0.0, $calculatedLineItem->getPrice()->getCalculatedTaxes()->getAmount());
    }
}
