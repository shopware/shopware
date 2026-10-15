<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartLocker;
use Shopware\Core\Checkout\Cart\Extension\CartItemAddRouteExtension;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\PromotionLineItemFactory;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\SalesChannel\CartItemAddRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartResponse;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\RateLimiter\RateLimiterFactory;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 *
 * @phpstan-import-type RateLimiterConfig from RateLimiterFactory
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

    #[DataProvider('promotionRequests')]
    public function testPromotionCodesShareRateLimit(bool $storefront, bool $batch): void
    {
        $request = Request::create('/', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        /** @var array{shopware: array{api: array{rate_limiter: array<string, RateLimiterConfig>}}} $config */
        $config = Yaml::parseFile(\dirname(__DIR__, 6) . '/src/Core/Framework/Resources/config/packages/shopware.yaml');
        $rateLimiter = new RateLimiter();
        $rateLimiter->registerLimiterFactory(RateLimiter::PROMOTION_CODE, new RateLimiterFactory(
            $config['shopware']['api']['rate_limiter']['promotion_code'] + ['id' => RateLimiter::PROMOTION_CODE],
            new InMemoryStorage(),
            static::createStub(SystemConfigService::class),
            new NativeClock(),
        ));
        $rateLimiter->registerLimiterFactory(RateLimiter::CART_ADD_LINE_ITEM, new RateLimiterFactory(
            ['id' => RateLimiter::CART_ADD_LINE_ITEM, 'enabled' => false, 'policy' => 'time_backoff'],
            new InMemoryStorage(),
            static::createStub(SystemConfigService::class),
            new NativeClock(),
        ));

        $calculator = $this->createMock(CartCalculator::class);
        $calculator->expects($batch ? $this->never() : $this->exactly(3))
            ->method('calculate')->willReturnArgument(0);
        $persister = $this->createMock(AbstractCartPersister::class);
        $persister->expects($batch ? $this->never() : $this->exactly(3))->method('save');
        $locker = static::createStub(CartLocker::class);
        $locker->method('locked')->willReturnCallback(static fn (SalesChannelContext $context, \Closure $closure) => $closure());
        $factory = new LineItemFactoryRegistry([new PromotionLineItemFactory()], static::createStub(DataValidator::class), new EventDispatcher());
        $route = new CartItemAddRoute(
            $calculator,
            $persister,
            new EventDispatcher(),
            $factory,
            $rateLimiter,
            $locker,
            new ExtensionDispatcher(new EventDispatcher()),
            $requestStack,
        );

        $items = [];
        for ($attempt = 1; $attempt <= 4; ++$attempt) {
            $items[] = new LineItem('promotion-' . $attempt, LineItem::PROMOTION_LINE_ITEM_TYPE, 'code-' . $attempt);
        }

        $cart = new Cart(Uuid::randomHex());
        if (!$batch) {
            foreach (\array_slice($items, 0, 3) as $item) {
                $request->request->set('items', [['type' => LineItem::PROMOTION_LINE_ITEM_TYPE, 'referencedId' => $item->getReferencedId()]]);
                $route->add($storefront ? new Request() : $request, new Cart(Uuid::randomHex()), $this->createSalesChannelContext(), $storefront ? [$item] : null);
            }
            $items = [$items[3]];
        }

        $request->request->set('items', array_map(static fn (LineItem $item): array => ['type' => LineItem::PROMOTION_LINE_ITEM_TYPE, 'referencedId' => $item->getReferencedId()], $items));
        $this->expectException(RateLimitExceededException::class);
        try {
            $route->add($storefront ? new Request() : $request, $cart, $this->createSalesChannelContext(), $storefront ? $items : null);
        } finally {
            static::assertCount($batch ? 3 : 0, $cart->getLineItems());
        }
    }

    public static function promotionRequests(): \Generator
    {
        yield 'Store API requests with different codes and carts' => [false, false];
        yield 'Storefront requests with different codes and carts' => [true, false];
        yield 'Store API batch' => [false, true];
        yield 'Storefront batch' => [true, true];
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

        $lineItemFactory = $this->createMock(LineItemFactoryRegistry::class);
        $lineItemFactory
            ->expects($this->atLeastOnce())
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
            new RequestStack(),
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
