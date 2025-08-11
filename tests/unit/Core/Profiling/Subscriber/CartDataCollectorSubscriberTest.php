<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Profiling\Subscriber\CartDataCollectorSubscriber;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(CartDataCollectorSubscriber::class)]
class CartDataCollectorSubscriberTest extends TestCase
{
    public function testEvents(): void
    {
        static::assertSame(
            [
                SalesChannelContextResolvedEvent::class => 'onContextResolved',
            ],
            CartDataCollectorSubscriber::getSubscribedEvents()
        );
    }

    public function testDataCollection(): void
    {
        $cartToken = Uuid::randomHex();

        $cart = new Cart('test-cart');
        $lineItem = new LineItem('line-item-id', 'product', 'product-id', 2);
        $lineItem->setLabel('Test Product');
        $lineItem->setPrice(new CalculatedPrice(
            100.00,
            200.00,
            new CalculatedTaxCollection(),
            new TaxRuleCollection()
        ));

        $cart->addLineItems(new LineItemCollection([$lineItem]));
        $cart->setPrice(new CartPrice(
            200.00,
            200.00,
            200.00,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_GROSS
        ));

        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode('EUR');

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $context = new Context(new SystemSource());
        $salesChannelContext->method('getContext')->willReturn($context);
        $salesChannelContext->method('getCurrency')->willReturn($currency);

        $event = new SalesChannelContextResolvedEvent($salesChannelContext, $cartToken);

        $cartPersister = $this->createMock(AbstractCartPersister::class);
        $cartPersister->method('load')->willReturn($cart);

        $subscriber = new CartDataCollectorSubscriber($cartPersister);
        $subscriber->onContextResolved($event);
        $subscriber->collect(new Request(), new Response());

        static::assertEquals($cart, $subscriber->getCart());
        static::assertSame(1, $subscriber->getItemCount());
        static::assertSame(200.00, $subscriber->getCartTotal());
        static::assertSame('EUR', $subscriber->getCurrency());
    }

    public function testEmptyCart(): void
    {
        $cartToken = Uuid::randomHex();

        $cart = new Cart('empty-cart');

        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode('EUR');

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCurrency')->willReturn($currency);

        $event = new SalesChannelContextResolvedEvent($salesChannelContext, $cartToken);
        $cartPersister = $this->createMock(AbstractCartPersister::class);
        $cartPersister->method('load')->willReturn($cart);

        $subscriber = new CartDataCollectorSubscriber($cartPersister);
        $subscriber->onContextResolved($event);
        $subscriber->collect(new Request(), new Response());
        static::assertEquals($cart, $subscriber->getCart());
        static::assertSame(0, $subscriber->getItemCount());
        static::assertSame(0.0, $subscriber->getCartTotal());
        static::assertSame('EUR', $subscriber->getCurrency());
    }

    public function testReset(): void
    {
        $cartToken = Uuid::randomHex();

        $cart = new Cart('test-cart');

        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode('EUR');

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCurrency')->willReturn($currency);

        $event = new SalesChannelContextResolvedEvent($salesChannelContext, $cartToken);

        $cartPersister = $this->createMock(AbstractCartPersister::class);
        $cartPersister->method('load')->willReturn($cart);

        $subscriber = new CartDataCollectorSubscriber($cartPersister);
        $subscriber->onContextResolved($event);
        $subscriber->collect(new Request(), new Response());

        static::assertEquals($cart, $subscriber->getCart());

        $subscriber->reset();

        $subscriber->collect(new Request(), new Response());

        static::assertNull($subscriber->getCart());
    }
}
