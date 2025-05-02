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
            ->expects(static::once())
            ->method('getCart')
            ->with('test', $this->isInstanceOf(SalesChannelContext::class))
            ->willThrowException(new CartTokenNotFoundException(404, 'CART_NOT_FOUND', 'cart not found'));

        $cartService
            ->expects(static::once())
            ->method('createNew')
            ->with('test')
            ->willReturn($newCart);

        $cartService
            ->expects(static::once())
            ->method('recalculate')
            ->with($newCart, $this->isInstanceOf(SalesChannelContext::class))
            ->willReturn($calculatedCart);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects(static::once())
            ->method('getToken')
            ->willReturn('test');

        $cartLoadRoute = new CartLoadRoute(
            $cartService,
            $this->createMock(TaxProviderProcessor::class),

        );

        static::assertSame($calculatedCart, $cartLoadRoute->load(new Request(), $salesChannelContext)->getCart());
    }
}