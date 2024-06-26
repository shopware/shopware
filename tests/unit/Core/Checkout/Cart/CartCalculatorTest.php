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
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(CartCalculator::class)]
#[Package('checkout')]
class CartCalculatorTest extends TestCase
{
    public const EXPECTED_HASH = '0e7471dd6822e878f04962fc750993c42ccfe121672409e8ef92237658055942';

    public function testCalculate(): void
    {
        $context = Generator::createSalesChannelContext();
        $behavior = new CartBehavior($context->getPermissions());
        $cart = $this->getCart();
        $result = new RuleLoaderResult($cart, new RuleCollection());

        $cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $cartRuleLoader
            ->expects(static::once())
            ->method('loadByCart')
            ->with($context, $cart, static::equalTo($behavior))
            ->willReturn($result);

        $calculator = new CartCalculator($cartRuleLoader, new CartContextHasher(new EventDispatcher()));
        $calculatedCart = $calculator->calculate($cart, $context);

        static::assertFalse($calculatedCart->isModified());
        static::assertCount(2, $calculatedCart->getLineItems());

        foreach ($calculatedCart->getLineItems() as $lineItem) {
            static::assertFalse($lineItem->isModified());
        }
    }

    public function testSetHash(): void
    {
        $context = Generator::createSalesChannelContext();
        $behavior = new CartBehavior($context->getPermissions());
        $cart = $this->getCart();
        $result = new RuleLoaderResult($cart, new RuleCollection());

        $cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $cartRuleLoader
            ->expects(static::once())
            ->method('loadByCart')
            ->with($context, $cart, static::equalTo($behavior))
            ->willReturn($result);

        $calculator = new CartCalculator($cartRuleLoader, new CartContextHasher(new EventDispatcher()));
        $calculatedCart = $calculator->calculate($cart, $context);

        static::assertSame(self::EXPECTED_HASH, $calculatedCart->getHash());
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
