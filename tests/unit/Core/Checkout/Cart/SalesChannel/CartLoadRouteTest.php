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
#[Package('checkout')]
#[CoversClass(CartLoadRoute::class)]
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

    public function testLoadReusesTheResolvedCart(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->once())
            ->method('getToken')
            ->willReturn('test');

        $resolvedCart = new Cart('test');

        $persister = $this->createMock(AbstractCartPersister::class);
        $persister->expects($this->never())->method('load');

        $calculator = $this->createMock(CartCalculator::class);
        $calculator->expects($this->never())->method('calculate');
        $calculator
            ->expects($this->once())
            ->method('finalize')
            ->with($resolvedCart, $salesChannelContext)
            ->willReturn($resolvedCart);

        $cartLoadRoute = new CartLoadRoute(
            $persister,
            static::createStub(CartFactory::class),
            $calculator,
            static::createStub(TaxProviderProcessor::class),
        );

        static::assertSame(
            $resolvedCart,
            $cartLoadRoute->load(new Request(), $salesChannelContext, $resolvedCart)->getCart()
        );
    }

    public function testLoadReadsTheCartFromStorageForAnotherToken(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext
            ->method('getToken')
            ->willReturn('context-token');

        $storedCart = new Cart('other-token');
        $persister = $this->createMock(AbstractCartPersister::class);
        $persister
            ->expects($this->once())
            ->method('load')
            ->with('other-token')
            ->willReturn($storedCart);

        $calculatedCart = new Cart('other-token');
        $calculator = $this->createMock(CartCalculator::class);
        $calculator->expects($this->never())->method('finalize');
        $calculator
            ->expects($this->once())
            ->method('calculate')
            ->with($storedCart, $salesChannelContext)
            ->willReturn($calculatedCart);

        $cartLoadRoute = new CartLoadRoute(
            $persister,
            static::createStub(CartFactory::class),
            $calculator,
            static::createStub(TaxProviderProcessor::class),
        );

        $request = new Request(['token' => 'other-token']);

        static::assertSame(
            $calculatedCart,
            $cartLoadRoute->load($request, $salesChannelContext, new Cart('context-token'))->getCart()
        );
    }
}
