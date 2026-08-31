<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartContextHasher;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\RuleLoaderResult;
use Shopware\Core\Checkout\Cart\Telemetry\CartMetricsInstrumentor;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\System\SalesChannel\Telemetry\SalesChannelTypeResolver;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartCalculator::class)]
class CartCalculatorTest extends TestCase
{
    public const EXPECTED_HASH = '0e7471dd6822e878f04962fc750993c42ccfe121672409e8ef92237658055942';

    public function testCalculate(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setTypeId(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        $behavior = new CartBehavior($context->getPermissions());
        $cart = $this->getCart();
        $result = new RuleLoaderResult($cart, new RuleCollection());

        $cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $cartRuleLoader
            ->expects($this->once())
            ->method('loadByCart')
            ->with($context, $cart, static::equalTo($behavior))
            ->willReturn($result);

        $calculator = new CartCalculator(
            $cartRuleLoader,
            new CartContextHasher(new EventDispatcher()),
            new CartMetricsInstrumentor(static::createStub(Meter::class), new SalesChannelTypeResolver()),
        );
        $calculatedCart = $calculator->calculate($cart, $context);

        static::assertFalse($calculatedCart->isModified());
        static::assertCount(2, $calculatedCart->getLineItems());

        foreach ($calculatedCart->getLineItems() as $lineItem) {
            static::assertFalse($lineItem->isModified());
        }
    }

    public function testSetHash(): void
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('19d144ffe15f4772860d59fca7f207c1');

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('8beeb66e9dda46b18891a059257a590e');

        $context = Generator::generateSalesChannelContext(
            paymentMethod: $paymentMethod,
            shippingMethod: $shippingMethod,
        );
        $context->getSalesChannel()->setTypeId(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $behavior = new CartBehavior($context->getPermissions());
        $cart = $this->getCart();
        $result = new RuleLoaderResult($cart, new RuleCollection());

        $cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $cartRuleLoader
            ->expects($this->once())
            ->method('loadByCart')
            ->with($context, $cart, static::equalTo($behavior))
            ->willReturn($result);

        $calculator = new CartCalculator(
            $cartRuleLoader,
            new CartContextHasher(new EventDispatcher()),
            new CartMetricsInstrumentor(static::createStub(Meter::class), new SalesChannelTypeResolver()),
        );
        $calculatedCart = $calculator->calculate($cart, $context);

        static::assertSame(self::EXPECTED_HASH, $calculatedCart->getHash());
    }

    public function testMarkCalculatedStampsAnAlreadyCalculatedCart(): void
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('19d144ffe15f4772860d59fca7f207c1');

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('8beeb66e9dda46b18891a059257a590e');

        $context = Generator::generateSalesChannelContext(
            paymentMethod: $paymentMethod,
            shippingMethod: $shippingMethod,
        );
        $context->getSalesChannel()->setTypeId(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $cartRuleLoader->expects($this->never())->method('loadByCart');

        $calculator = new CartCalculator(
            $cartRuleLoader,
            new CartContextHasher(new EventDispatcher()),
            new CartMetricsInstrumentor(static::createStub(Meter::class), new SalesChannelTypeResolver()),
        );

        $markedCart = $calculator->markCalculated($this->getCart(), $context);

        static::assertSame(self::EXPECTED_HASH, $markedCart->getHash());
        static::assertFalse($markedCart->isModified());

        foreach ($markedCart->getLineItems() as $lineItem) {
            static::assertFalse($lineItem->isModified());
        }
    }

    private function getCart(): Cart
    {
        $cart = new Cart('hatoken');
        $cart->markModified();

        $item1 = new LineItem('a', 'product');
        $item1->markModified();

        $item2 = new LineItem('b', 'product');
        $item2->markModified();

        $cart->add($item1);
        $cart->add($item2);

        return $cart;
    }
}
