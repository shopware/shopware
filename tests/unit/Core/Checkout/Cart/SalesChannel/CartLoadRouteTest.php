<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\SalesChannel\CartLoadRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxProviderProcessor;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CartLoadRoute::class)]
class CartLoadRouteTest extends TestCase
{
    public function testLoadCartCreatesNewCart(): void
    {
        $newCart = new Cart('test');
        $calculatedCart = new Cart('calculated');

        $cartService = $this->createMock(CartService::class);

        $cartService
            ->expects($this->once())
            ->method('load')
            ->with('test', static::isInstanceOf(SalesChannelContext::class))
            ->willThrowException(new CartTokenNotFoundException(404, 'CART_NOT_FOUND', 'cart not found'));

        $cartService
            ->expects($this->once())
            ->method('createNew')
            ->with('test')
            ->willReturn($newCart);

        $cartService
            ->expects($this->once())
            ->method('recalculate')
            ->with($newCart, static::isInstanceOf(SalesChannelContext::class))
            ->willReturn($calculatedCart);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->once())
            ->method('getToken')
            ->willReturn('test');

        $cartLoadRoute = new CartLoadRoute(
            $cartService,
            $this->createMock(TaxProviderProcessor::class)
        );

        static::assertSame($calculatedCart, $cartLoadRoute->load(new Request(), $salesChannelContext)->getCart());
    }

    public function testLoadCartReturnsExistingCart(): void
    {
        $existingCart = new Cart('test');
        $recalculatedCart = new Cart('recalculated');

        $cartService = $this->createMock(CartService::class);

        $cartService
            ->expects($this->once())
            ->method('load')
            ->with('test', static::isInstanceOf(SalesChannelContext::class))
            ->willReturn($existingCart);

        $cartService
            ->expects($this->never())
            ->method('createNew');

        $cartService
            ->expects($this->once())
            ->method('recalculate')
            ->with($existingCart, static::isInstanceOf(SalesChannelContext::class))
            ->willReturn($recalculatedCart);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->once())
            ->method('getToken')
            ->willReturn('test');

        $cartLoadRoute = new CartLoadRoute(
            $cartService,
            $this->createMock(TaxProviderProcessor::class)
        );

        static::assertSame($recalculatedCart, $cartLoadRoute->load(new Request(), $salesChannelContext)->getCart());
    }
}
