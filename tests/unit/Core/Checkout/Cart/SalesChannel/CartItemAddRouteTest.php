<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartLocker;
use Shopware\Core\Checkout\Cart\Extension\CartItemAddRouteExtension;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\SalesChannel\CartItemAddRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartResponse;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\RateLimiter\RateLimiterException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartItemAddRoute::class)]
class CartItemAddRouteTest extends TestCase
{
    private const SALES_CHANNEL_ID = 'af0eb8b68a5f4e6d95f1cbd4f0bdcb45';

    public function testGetDecoratedThrows(): void
    {
        static::expectExceptionObject(new DecorationPatternException(CartItemAddRoute::class));

        (new CartItemAddRoute(
            static::createStub(CartCalculator::class),
            static::createStub(AbstractCartPersister::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(LineItemFactoryRegistry::class),
            static::createStub(RateLimiter::class),
            static::createStub(CartLocker::class),
            new ExtensionDispatcher(new EventDispatcher()),
            new RequestStack(),
        ))->getDecorated();
    }

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
            static::createStub(SalesChannelContext::class),
            null
        );
    }

    public function testRateLimitationId(): void
    {
        $cartItemAddRoute = $this->createCartItemAddRoute('line-item-id-127.0.0.1-' . self::SALES_CHANNEL_ID);

        $item = [
            'id' => 'line-item-id',
            'type' => 'line-item-type',
            'quantity' => 2,
        ];

        $cartItemAddRoute->add(
            $this->createRequest($item),
            new Cart(Uuid::randomHex()),
            $this->createSalesChannelContext(),
            null
        );
    }

    public function testRateLimitationReferenceId(): void
    {
        $cartItemAddRoute = $this->createCartItemAddRoute('line-item-referenced-id-127.0.0.1-' . self::SALES_CHANNEL_ID);

        $item = [
            'id' => 'line-item-id',
            'type' => 'line-item-type',
            'referencedId' => 'line-item-referenced-id',
            'quantity' => 2,
        ];

        $cartItemAddRoute->add(
            $this->createRequest($item),
            new Cart(Uuid::randomHex()),
            $this->createSalesChannelContext(),
            null
        );
    }

    public function testAddReturnsContextTokenHeader(): void
    {
        $cartItemAddRoute = $this->createCartItemAddRoute(null);

        $item = [
            'id' => 'line-item-id',
            'type' => 'line-item-type',
            'quantity' => 1,
        ];

        $context = static::createStub(SalesChannelContext::class);
        $context
            ->method('getToken')
            ->willReturn('context-token');

        $response = $cartItemAddRoute->add(
            $this->createRequest($item, null),
            new Cart(Uuid::randomHex()),
            $context,
            null
        );

        static::assertSame('context-token', $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testRouteUsesLock(): void
    {
        $cartLocker = $this->createMock(CartLocker::class);
        $cartLocker
            ->expects($this->once())
            ->method('locked')
            ->willReturnCallback(static fn (SalesChannelContext $context, \Closure $closure) => $closure());

        $cartItemAddRoute = $this->createCartItemAddRoute(null, $cartLocker);

        $item = [
            'id' => 'line-item-id',
            'type' => 'line-item-type',
            'quantity' => 1,
        ];

        $cartItemAddRoute->add(
            $this->createRequest($item, null),
            new Cart(Uuid::randomHex()),
            static::createStub(SalesChannelContext::class),
            null
        );
    }

    public function testPublishesExtension(): void
    {
        $request = new Request();
        $cart = new Cart(Uuid::randomHex());
        $context = Generator::generateSalesChannelContext();
        $items = [new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE)];
        $response = new CartResponse(new Cart('token'));

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('cart-item-add-route.add.pre', static function (CartItemAddRouteExtension $extension) use ($request, $cart, $context, $items, $response): void {
            static::assertSame(['request' => $request, 'cart' => $cart, 'context' => $context, 'items' => $items], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new CartItemAddRoute(
            static::createStub(CartCalculator::class),
            static::createStub(AbstractCartPersister::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(LineItemFactoryRegistry::class),
            static::createStub(RateLimiter::class),
            static::createStub(CartLocker::class),
            new ExtensionDispatcher($dispatcher),
            new RequestStack(),
        );

        static::assertSame($response, $route->add($request, $cart, $context, $items));
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

    public function testStorefrontProductsUsePerItemRateLimit(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/', server: ['REMOTE_ADDR' => '127.0.0.1']));
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())->method('ensureAccepted')
            ->with(RateLimiter::CART_ADD_LINE_ITEM, 'product-id-127.0.0.1-' . self::SALES_CHANNEL_ID, self::SALES_CHANNEL_ID);
        $route = $this->createRoute($rateLimiter, $requestStack);

        $route->add(new Request(), new Cart('test'), $this->createSalesChannelContext(), [
            new LineItem('product', LineItem::PRODUCT_LINE_ITEM_TYPE, 'product-id'),
        ]);
    }

    public function testStoreApiPromotionRateLimitRejectsBeforeAddingItem(): void
    {
        $exception = RateLimiterException::limitExceeded(time() + 30);
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

    public function testPromotionWithoutClientIpIsNotRateLimited(): void
    {
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->never())->method('ensureAccepted');
        $route = $this->createRoute($rateLimiter, new RequestStack());

        $route->add(new Request(), new Cart('test'), $this->createSalesChannelContext(), [
            new LineItem('promotion', LineItem::PROMOTION_LINE_ITEM_TYPE, 'example-code'),
        ]);
    }

    private function createCartItemAddRoute(?string $expectedCacheKey, ?CartLocker $cartLocker = null): CartItemAddRoute
    {
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter
            ->expects($this->exactly($expectedCacheKey === null ? 0 : 1))
            ->method('ensureAccepted')
            ->willReturnCallback(static function (string $route, string $key, ?string $salesChannelId = null) use ($expectedCacheKey): void {
                static::assertSame($route, RateLimiter::CART_ADD_LINE_ITEM);
                static::assertSame($expectedCacheKey, $key);
                static::assertSame(self::SALES_CHANNEL_ID, $salesChannelId);
            });

        return $this->createRoute($rateLimiter, new RequestStack(), $cartLocker);
    }

    private function createRoute(RateLimiter $rateLimiter, RequestStack $requestStack, ?CartLocker $cartLocker = null): CartItemAddRoute
    {
        $lineItemFactory = static::createStub(LineItemFactoryRegistry::class);
        $lineItemFactory
            ->method('create')
            ->willReturnCallback(
                static fn ($dataBag): LineItem => new LineItem($dataBag['id'], $dataBag['type'], $dataBag['referencedId'] ?? null, $dataBag['quantity'])
            );

        if ($cartLocker === null) {
            $cartLocker = static::createStub(CartLocker::class);
            $cartLocker->method('locked')->willReturnCallback(static fn (SalesChannelContext $context, \Closure $closure) => $closure());
        }

        return new CartItemAddRoute(
            static::createStub(CartCalculator::class),
            static::createStub(AbstractCartPersister::class),
            static::createStub(EventDispatcherInterface::class),
            $lineItemFactory,
            $rateLimiter,
            $cartLocker,
            new ExtensionDispatcher(new EventDispatcher()),
            $requestStack,
        );
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn(self::SALES_CHANNEL_ID);

        return $context;
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
