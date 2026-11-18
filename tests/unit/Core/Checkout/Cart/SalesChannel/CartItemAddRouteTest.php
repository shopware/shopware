<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartLocker;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\SalesChannel\CartItemAddRoute;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(CartItemAddRoute::class)]
class CartItemAddRouteTest extends TestCase
{
    private const SALES_CHANNEL_ID = 'af0eb8b68a5f4e6d95f1cbd4f0bdcb45';

    public function testRateLimitationWithoutIp(): void
    {
        $cartItemAddRoute = $this->createCartItemAddRoute(null);

        $item = [
            'id' => 'line-item-id',
            'type' => 'line-item-type',
            'quantity' => 2,
        ];

        $cartItemAddRoute->add(
            $this->createRequest($item, null),
            new Cart('test'),
            $this->createMock(SalesChannelContext::class),
            null
        );
    }

    public function testRateLimitationId(): void
    {
        $cartItemAddRoute = $this->createCartItemAddRoute('line-item-id-127.0.0.1');

        $item = [
            'id' => 'line-item-id',
            'type' => 'line-item-type',
            'quantity' => 2,
        ];

        $cartItemAddRoute->add(
            $this->createRequest($item),
            new Cart(Uuid::randomHex()),
            $this->createMock(SalesChannelContext::class),
            null
        );
    }

    public function testRateLimitationReferenceId(): void
    {
        $cartItemAddRoute = $this->createCartItemAddRoute('line-item-referenced-id-127.0.0.1');

        $item = [
            'id' => 'line-item-id',
            'type' => 'line-item-type',
            'referencedId' => 'line-item-referenced-id',
            'quantity' => 2,
        ];

        $cartItemAddRoute->add(
            $this->createRequest($item),
            new Cart(Uuid::randomHex()),
            $this->createMock(SalesChannelContext::class),
            null
        );
    }

    public function testRouteUsesLock(): void
    {
        $cartLocker = $this->createMock(CartLocker::class);
        $cartLocker
            ->expects($this->once())
            ->method('locked')
            ->willReturnCallback(fn (SalesChannelContext $context, \Closure $closure) => $closure());

        $cartItemAddRoute = $this->createCartItemAddRoute(null, $cartLocker);

        $item = [
            'id' => 'line-item-id',
            'type' => 'line-item-type',
            'quantity' => 1,
        ];

        $cartItemAddRoute->add(
            $this->createRequest($item, null),
            new Cart(Uuid::randomHex()),
            $this->createMock(SalesChannelContext::class),
            null
        );
    }

    public function testStorefrontPromotionCodesShareRateLimit(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/', server: ['REMOTE_ADDR' => '127.0.0.1']));
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->exactly(2))->method('ensureAccepted')
            ->with(RateLimiter::PROMOTION_CODE, '127.0.0.1-' . self::SALES_CHANNEL_ID);
        $route = $this->createRoute($rateLimiter, $requestStack);

        $route->add(new Request(), new Cart('test'), $this->createSalesChannelContext(), [
            new LineItem('first', LineItem::PROMOTION_LINE_ITEM_TYPE, 'first-code'),
            new LineItem('second', LineItem::PROMOTION_LINE_ITEM_TYPE, 'second-code'),
        ]);
    }

    public function testStorefrontProductsAreNotRateLimited(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/', server: ['REMOTE_ADDR' => '127.0.0.1']));
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->never())->method('ensureAccepted');
        $route = $this->createRoute($rateLimiter, $requestStack);

        $route->add(new Request(), new Cart('test'), $this->createSalesChannelContext(), [
            new LineItem('product', LineItem::PRODUCT_LINE_ITEM_TYPE, 'product-id'),
        ]);
    }

    public function testProductIpv6AddressesSharePerItemPrefixAllowance(): void
    {
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->exactly(2))->method('ensureAccepted')
            ->with(RateLimiter::CART_ADD_LINE_ITEM, 'product-id-2001:db8:1234:5678::');
        $route = $this->createRoute($rateLimiter, new RequestStack());

        foreach (['2001:db8:1234:5678::1', '2001:db8:1234:5678::2'] as $ip) {
            $route->add(Request::create('/', server: ['REMOTE_ADDR' => $ip]), new Cart('test'), $this->createSalesChannelContext(), [
                new LineItem('product', LineItem::PRODUCT_LINE_ITEM_TYPE, 'product-id'),
            ]);
        }
    }

    public function testPromotionIpv6AddressesSharePrefixAllowance(): void
    {
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->exactly(2))->method('ensureAccepted')
            ->with(RateLimiter::PROMOTION_CODE, '2001:db8:1234:5678::-' . self::SALES_CHANNEL_ID);
        $route = $this->createRoute($rateLimiter, new RequestStack());

        foreach (['2001:db8:1234:5678::1', '2001:db8:1234:5678::2'] as $ip) {
            $route->add(Request::create('/', server: ['REMOTE_ADDR' => $ip]), new Cart('test'), $this->createSalesChannelContext(), [
                new LineItem('promotion', LineItem::PROMOTION_LINE_ITEM_TYPE, 'example-code'),
            ]);
        }
    }

    public function testStoreApiPromotionRateLimitRejectsBeforeAddingItem(): void
    {
        $exception = new RateLimitExceededException(time() + 30);
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())->method('ensureAccepted')
            ->with(RateLimiter::PROMOTION_CODE, '127.0.0.1-' . self::SALES_CHANNEL_ID)
            ->willThrowException($exception);
        $route = $this->createRoute($rateLimiter, new RequestStack());
        $cart = new Cart('test');
        $request = $this->createRequest([
            'id' => 'promotion',
            'type' => LineItem::PROMOTION_LINE_ITEM_TYPE,
            'referencedId' => 'example-code',
            'quantity' => 1,
        ]);

        $this->expectExceptionObject($exception);
        try {
            $route->add($request, $cart, $this->createSalesChannelContext(), null);
        } finally {
            static::assertCount(0, $cart->getLineItems());
        }
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn(self::SALES_CHANNEL_ID);

        return $context;
    }

    private function createCartItemAddRoute(?string $expectedCacheKey, ?CartLocker $cartLocker = null): CartItemAddRoute
    {
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter
            ->expects($this->exactly($expectedCacheKey === null ? 0 : 1))
            ->method('ensureAccepted')
            ->willReturnCallback(function (string $route, string $key) use ($expectedCacheKey): void {
                static::assertSame($route, RateLimiter::CART_ADD_LINE_ITEM);
                static::assertSame($expectedCacheKey, $key);
            });

        return $this->createRoute($rateLimiter, new RequestStack(), $cartLocker);
    }

    private function createRoute(RateLimiter $rateLimiter, RequestStack $requestStack, ?CartLocker $cartLocker = null): CartItemAddRoute
    {
        $lineItemFactory = static::createStub(LineItemFactoryRegistry::class);
        $lineItemFactory
            ->method('create')
            ->willReturnCallback(
                fn ($dataBag): LineItem => new LineItem($dataBag['id'], $dataBag['type'], $dataBag['referencedId'] ?? null, $dataBag['quantity'])
            );

        if ($cartLocker === null) {
            $cartLocker = $this->createMock(CartLocker::class);
            $cartLocker->method('locked')->willReturnCallback(fn (SalesChannelContext $context, \Closure $closure) => $closure());
        }

        return new CartItemAddRoute(
            $this->createMock(CartCalculator::class),
            $this->createMock(AbstractCartPersister::class),
            $this->createMock(EventDispatcherInterface::class),
            $lineItemFactory,
            $rateLimiter,
            $cartLocker,
            $requestStack,
        );
    }

    /**
     * @param array<string, string|int> $lineItems
     */
    private function createRequest(array $lineItems, ?string $ip = '127.0.0.1'): Request
    {
        $items = [
            'items' => [
                $lineItems,
            ],
        ];

        if ($ip === null) {
            return new Request([], $items);
        }

        return new Request([], $items, [], [], [], ['REMOTE_ADDR' => $ip]);
    }
}
