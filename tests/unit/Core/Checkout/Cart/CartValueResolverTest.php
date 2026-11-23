<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValueResolver;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartValueResolver::class)]
class CartValueResolverTest extends TestCase
{
    public function testIgnoresArgumentsOfOtherTypes(): void
    {
        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->never())->method('getCart');

        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => Generator::generateSalesChannelContext(),
            PlatformRequest::ATTRIBUTE_EFFECTIVE_CART_OBJECT => new Cart('order-token'),
        ]);

        $resolved = (new CartValueResolver($cartService))->resolve($request, self::argument(SalesChannelContext::class));

        static::assertSame([], iterator_to_array($resolved));
    }

    public function testPrefersTheOrderBasedCartOfAnOptedInRoute(): void
    {
        $cart = new Cart('order-token');

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->never())->method('getCart');

        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => Generator::generateSalesChannelContext(),
            PlatformRequest::ATTRIBUTE_EFFECTIVE_CART_OBJECT => $cart,
        ]);

        $resolved = (new CartValueResolver($cartService))->resolve($request, self::argument(Cart::class));

        static::assertSame([$cart], iterator_to_array($resolved));
    }

    public function testLoadsTheSessionCartWithoutAnOrderBasedCart(): void
    {
        $context = Generator::generateSalesChannelContext();
        $cart = new Cart($context->getToken());

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->once())
            ->method('getCart')
            ->with($context->getToken(), static::identicalTo($context))
            ->willReturn($cart);

        $request = new Request(attributes: [PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $context]);

        $resolved = (new CartValueResolver($cartService))->resolve($request, self::argument(Cart::class));

        static::assertSame([$cart], iterator_to_array($resolved));
    }

    private static function argument(string $type): ArgumentMetadata
    {
        return new ArgumentMetadata('cart', $type, isVariadic: false, hasDefaultValue: false, defaultValue: null);
    }
}
