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
        
        $cartService->method('getCart')
            ->willReturnCallback(function (string $token) {
                if ($token === 'test') {
                    throw new CartTokenNotFoundException(404, 'CART_NOT_FOUND', 'cart not found');
                }
                $this->fail('Unexpected token value');
            });

        $cartService->method('createNew')
            ->willReturnCallback(function (string $token) use ($newCart) {
                $this->assertEquals('test', $token);
                return $newCart;
            });

        $cartService->method('recalculate')
            ->willReturnCallback(function (Cart $cart, SalesChannelContext $context) use ($newCart, $calculatedCart) {
                $this->assertSame($newCart, $cart);
                return $calculatedCart;
            });

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getToken')
            ->willReturn('test');

        $cartLoadRoute = new CartLoadRoute(
            $cartService,
            $this->createMock(TaxProviderProcessor::class)
        );

        static::assertSame($calculatedCart, $cartLoadRoute->load(new Request(), $salesChannelContext)->getCart());
    }
}
