<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\CartFactory;
use Shopware\Core\Checkout\Cart\SalesChannel\CartLoadRoute;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxProviderProcessor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CartLoadRoute::class)]
#[Package('checkout')]
class CartLoadRouteTest extends TestCase
{
    public function testLoadCartCreatesNewCart(): void
    {
        $newCart = new Cart('test');
        $factory = $this->createMock(CartFactory::class);
        $factory
            ->expects($this->once())
            ->method('createNew')
            ->with('test')
            ->willReturn($newCart);

        $persister = $this->createMock(AbstractCartPersister::class);
        $persister
            ->expects($this->once())
            ->method('load')
            ->with('test')
            ->willThrowException(CartException::tokenNotFound('test'));

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->once())
            ->method('getToken')
            ->willReturn('test');

        $calculatedCart = new Cart('calculated');
        $calculator = $this->createMock(CartCalculator::class);
        $calculator
            ->expects($this->once())
            ->method('calculate')
            ->with($newCart, $salesChannelContext)
            ->willReturn($calculatedCart);

        $cartLoadRoute = new CartLoadRoute(
            $persister,
            $factory,
            $calculator,
            static::createStub(TaxProviderProcessor::class),
        );

        static::assertSame($calculatedCart, $cartLoadRoute->load(new Request(), $salesChannelContext)->getCart());
    }

    public function testLoadCreatesFreshCartWhenCartWasDeletedDuringCalculation(): void
    {
        $loadedCart = new Cart('test');
        $loadedCart->setPersisted(true);

        $persister = $this->createMock(AbstractCartPersister::class);
        $persister
            ->expects($this->once())
            ->method('load')
            ->with('test')
            ->willReturn($loadedCart);

        $newCart = new Cart('test');
        $factory = $this->createMock(CartFactory::class);
        $factory
            ->expects($this->once())
            ->method('createNew')
            ->with('test')
            ->willReturn($newCart);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->once())
            ->method('getToken')
            ->willReturn('test');

        $calculatedCart = new Cart('calculated');
        $calculator = $this->createMock(CartCalculator::class);
        $calculator
            ->expects($this->exactly(2))
            ->method('calculate')
            ->willReturnCallback(function (Cart $cart) use ($loadedCart, $newCart, $calculatedCart): Cart {
                if ($cart === $loadedCart) {
                    throw CartException::tokenNotFound('test');
                }

                static::assertSame($newCart, $cart);

                return $calculatedCart;
            });

        $cartLoadRoute = new CartLoadRoute(
            $persister,
            $factory,
            $calculator,
            static::createStub(TaxProviderProcessor::class),
        );

        static::assertSame($calculatedCart, $cartLoadRoute->load(new Request(), $salesChannelContext)->getCart());
    }
}
