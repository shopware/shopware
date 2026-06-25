<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheHeadersService;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheRelevantRulesResolver;
use Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Test\TestCaseBase\EventDispatcherBehaviour;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CacheHeadersService::class)]
class CacheHeadersServiceTest extends TestCase
{
    use EventDispatcherBehaviour;

    /**
     * @var array<string, string>
     */
    private static array $hashes = [];

    private CacheHeadersService $cacheHeadersService;

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $extensionDispatcher = new ExtensionDispatcher($this->eventDispatcher);

        $this->cacheHeadersService = new CacheHeadersService(
            $extensionDispatcher,
            new CacheRelevantRulesResolver($extensionDispatcher),
            [],
            $this->eventDispatcher,
        );
    }

    #[DataProvider('cashHashProvider')]
    public function testGenerateCashHashWithItemsInCart(?CustomerEntity $customer, Cart $cart, bool $hasCookie, ?string $hashName = null): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);
        if ($customer !== null) {
            $salesChannelContext->expects($this->once())
                ->method('getRuleIdsByAreas')
                ->with([RuleAreas::PRODUCT_AREA])
                ->willReturn(['matched-rule']);
        }

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);

        if ($hasCookie === false) {
            $request->cookies->set(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, 'foo');
        }

        $response = new Response();

        $this->cacheHeadersService->applyCacheHash($request, $salesChannelContext, $cart, $response);

        if ($hasCookie) {
            static::assertTrue($response->headers->has('set-cookie'));

            $cookies = array_filter($response->headers->getCookies(), static fn (Cookie $cookie) => $cookie->getName() === HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE);

            static::assertCount(1, $cookies);
            $cookie = array_shift($cookies);

            static::assertNotNull($cookie->getValue());
            if ($hashName) {
                if (!isset(self::$hashes[$hashName])) {
                    self::$hashes[$hashName] = $cookie->getValue();
                }

                foreach (self::$hashes as $name => $value) {
                    if ($hashName === $name) {
                        static::assertSame(
                            $value,
                            $cookie->getValue(),
                            \sprintf('Hashes for state "%s" did not match, got "%s", but expected "%s"', $hashName, $cookie->getValue(), $value)
                        );
                    } else {
                        static::assertNotSame(
                            $value,
                            $cookie->getValue(),
                            \sprintf('Hashes for state "%s" and state "%s" should not match, but did match.', $hashName, $name)
                        );
                    }
                }
            }

            static::assertSame($cookie->getValue(), $response->headers->get(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE));
        } else {
            $cookies = $response->headers->getCookies();
            static::assertNotEmpty($cookies, 'the client cookie should be cleared');

            foreach ($cookies as $cookie) {
                static::assertSame(1, $cookie->getExpiresTime(), 'cookie should expire');
            }

            static::assertNull($response->headers->get(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE));
        }
    }

    /**
     * @return iterable<string, array<int, CustomerEntity|Cart|bool|string|null>>
     */
    public static function cashHashProvider(): iterable
    {
        $emptyCart = new Cart('empty');
        $customer = new CustomerEntity();

        $filledCart = new Cart('filled');
        $filledCart->add(new LineItem('test', 'test', 'test'));

        yield 'Test with no logged in customer' => [null, $emptyCart, false];
        yield 'Test with filled cart' => [null, $filledCart, true, 'not-logged-in'];
        // all logged in customer should share the same cache hash if no rules match
        yield 'Test with logged in customer' => [$customer, $emptyCart, true, 'logged-in'];
        yield 'Test with filled cart and logged in customer' => [$customer, $filledCart, true, 'logged-in'];
    }

    public function testStorefrontCacheHashDoesNotContainLanguageId(): void
    {
        $event = $this->cacheHeadersService->applyCacheHash(
            new Request(attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]]),
            $this->createCacheHashContext('language-a'),
            $this->createFilledCart(),
            new Response()
        );

        static::assertInstanceOf(HttpCacheCookieEvent::class, $event);
        static::assertNull($event->get(HttpCacheCookieEvent::LANGUAGE_ID));
    }

    public function testStoreApiCacheHashContainsLanguageId(): void
    {
        $event = $this->cacheHeadersService->applyCacheHash(
            new Request(attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]]),
            $this->createCacheHashContext('language-a'),
            $this->createFilledCart(),
            new Response()
        );

        static::assertInstanceOf(HttpCacheCookieEvent::class, $event);
        static::assertSame('language-a', $event->get(HttpCacheCookieEvent::LANGUAGE_ID));
    }

    public function testCurrencyChangeLeadsToDifferentCacheHash(): void
    {
        $request = new Request();
        $salesChannelContextMock = $this->createMock(SalesChannelContext::class);
        $salesChannelContextMock->method('getSalesChannel')->willReturn((new SalesChannelEntity())->assign(['currencyId' => Defaults::CURRENCY]));
        $salesChannelContextMock->method('getCurrencyId')->willReturn(Defaults::CURRENCY);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContextMock);

        $response = new Response();

        $this->cacheHeadersService->applyCacheHash($request, $salesChannelContextMock, new Cart('cart'), $response);

        $cookies = $response->headers->getCookies();
        static::assertEmpty($cookies);

        $salesChannelContextMock = $this->createMock(SalesChannelContext::class);
        $salesChannelContextMock->method('getSalesChannel')->willReturn((new SalesChannelEntity())->assign(['currencyId' => Defaults::CURRENCY]));
        $salesChannelContextMock->method('getCurrencyId')->willReturn('foo');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContextMock);

        $this->cacheHeadersService->applyCacheHash($request, $salesChannelContextMock, new Cart('cart'), $response);

        $cookies = $response->headers->getCookies();
        static::assertNotEmpty($cookies);
        // assert cache hash exist when currency is set to different value then the sales channel default
        static::assertSame(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $cookies[0]->getName());
        $firstHash = $cookies[0]->getValue();

        $salesChannelContextMock = $this->createMock(SalesChannelContext::class);
        $salesChannelContextMock->method('getSalesChannel')->willReturn((new SalesChannelEntity())->assign(['currencyId' => Defaults::CURRENCY]));
        $salesChannelContextMock->method('getCurrencyId')->willReturn('bar');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContextMock);

        $this->cacheHeadersService->applyCacheHash($request, $salesChannelContextMock, new Cart('cart'), $response);

        $cookies = $response->headers->getCookies();
        static::assertNotEmpty($cookies);
        static::assertSame(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $cookies[0]->getName());
        $secondHash = $cookies[0]->getValue();
        // assert cache hash is different when currency id is different
        static::assertNotSame($firstHash, $secondHash);
    }

    public function testCacheCookieStaysTheSameIfEventPartsAreSortedDifferently(): void
    {
        $customer = new CustomerEntity();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);

        $firstResponse = new Response();
        $this->cacheHeadersService->applyCacheHash($request, $salesChannelContext, new Cart('cart'), $firstResponse);

        $firstCacheCookie = $firstResponse->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY)['']['/'][HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE];
        static::assertInstanceOf(Cookie::class, $firstCacheCookie);

        $this->addEventListener($this->eventDispatcher, HttpCacheCookieEvent::class, static function (HttpCacheCookieEvent $event): void {
            $ruleIds = $event->get('rule-ids');
            self::assertIsArray($ruleIds);
            $event->remove('rule-ids');
            $event->add('rule-ids', $ruleIds);
        });

        $secondResponse = new Response();
        $this->cacheHeadersService->applyCacheHash($request, $salesChannelContext, new Cart('cart'), $secondResponse);

        $secondCacheCookie = $secondResponse->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY)['']['/'][HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE];
        static::assertInstanceOf(Cookie::class, $secondCacheCookie);

        static::assertSame($firstCacheCookie->getValue(), $secondCacheCookie->getValue());
    }

    public function testCacheCookieHasNoCacheValueIfSetInEvent(): void
    {
        $customer = new CustomerEntity();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);

        $firstResponse = new Response();
        $this->cacheHeadersService->applyCacheHash($request, $salesChannelContext, new Cart('cart'), $firstResponse);

        $firstCacheCookie = $firstResponse->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY)['']['/'][HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE];
        static::assertInstanceOf(Cookie::class, $firstCacheCookie);

        $this->addEventListener($this->eventDispatcher, HttpCacheCookieEvent::class, static function (HttpCacheCookieEvent $event): void {
            $event->isCacheable = false;
        });

        $secondResponse = new Response();
        $result = $this->cacheHeadersService->applyCacheHash($request, $salesChannelContext, new Cart('cart'), $secondResponse);
        static::assertInstanceOf(HttpCacheCookieEvent::class, $result);

        $secondCacheCookie = $secondResponse->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY)['']['/'][HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE];
        static::assertInstanceOf(Cookie::class, $secondCacheCookie);

        static::assertNotSame($firstCacheCookie->getValue(), $secondCacheCookie->getValue());
        static::assertSame(HttpCacheCookieEvent::NOT_CACHEABLE, $secondCacheCookie->getValue());
        static::assertSame(HttpCacheCookieEvent::NOT_CACHEABLE, $result->getHash());
    }

    public function testSetLanguageCurrencyHeaders(): void
    {
        $response = new Response();

        $context = $this->createMock(SalesChannelContext::class);
        $context->expects($this->once())->method('getLanguageId')->willReturn('language-id');
        $context->expects($this->once())->method('getCurrencyId')->willReturn('currency-id');

        $this->cacheHeadersService->applyCacheHeaders($context, $response);

        static::assertSame('language-id', $response->headers->get(PlatformRequest::HEADER_LANGUAGE_ID));
        static::assertSame('currency-id', $response->headers->get(PlatformRequest::HEADER_CURRENCY_ID));

        static::assertTrue($response->headers->has(PlatformRequest::HEADER_LANGUAGE_ID), 'Vary header should always be set');
        $vary = $response->headers->all('vary');
        static::assertCount(4, $vary);
        static::assertContains(PlatformRequest::HEADER_ACCESS_KEY, $vary);
        static::assertContains(PlatformRequest::HEADER_LANGUAGE_ID, $vary);
        static::assertContains(PlatformRequest::HEADER_CURRENCY_ID, $vary);
        static::assertContains(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $vary);
    }

    public function testCustomCacheRelevantCookiesInfluenceTheStateCookie(): void
    {
        $extensionDispatcher = new ExtensionDispatcher($this->eventDispatcher);
        $cacheHeadersService = new CacheHeadersService(
            $extensionDispatcher,
            new CacheRelevantRulesResolver($extensionDispatcher),
            ['my-custom-cookie'],
            $this->eventDispatcher,
        );

        $request = new Request();
        $salesChannelContextMock = $this->createMock(SalesChannelContext::class);
        $salesChannelContextMock->method('getSalesChannel')->willReturn((new SalesChannelEntity())->assign(['currencyId' => Defaults::CURRENCY]));
        $salesChannelContextMock->method('getCurrencyId')->willReturn(Defaults::CURRENCY);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContextMock);

        $response = new Response();

        $cacheHeadersService->applyCacheHash($request, $salesChannelContextMock, new Cart('cart'), $response);

        $cookies = $response->headers->getCookies();
        static::assertEmpty($cookies);

        $request->cookies->set('my-custom-cookie', 'foo');

        $cacheHeadersService->applyCacheHash($request, $salesChannelContextMock, new Cart('cart'), $response);

        $cookies = $response->headers->getCookies();
        static::assertNotEmpty($cookies);
        // assert cache hash exist when customCookie is set
        static::assertSame(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $cookies[0]->getName());
        $firstHash = $cookies[0]->getValue();

        $request->cookies->set('my-custom-cookie', 'bar');

        $cacheHeadersService->applyCacheHash($request, $salesChannelContextMock, new Cart('cart'), $response);

        $cookies = $response->headers->getCookies();
        static::assertNotEmpty($cookies);
        static::assertSame(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $cookies[0]->getName());
        $secondHash = $cookies[0]->getValue();
        // assert cache hash is different when custom cookie is different
        static::assertNotSame($firstHash, $secondHash);
    }

    private function createCacheHashContext(string $languageId): SalesChannelContext
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn(null);
        $salesChannelContext->method('getRuleIds')->willReturn([]);
        $salesChannelContext->method('getRuleIdsByAreas')->willReturn([]);
        $salesChannelContext->method('getVersionId')->willReturn(Defaults::LIVE_VERSION);
        $salesChannelContext->method('getCurrencyId')->willReturn(Defaults::CURRENCY);
        $salesChannelContext->method('getLanguageId')->willReturn($languageId);
        $salesChannelContext->method('getTaxState')->willReturn('gross');

        return $salesChannelContext;
    }

    private function createFilledCart(): Cart
    {
        $cart = new Cart('filled');
        $cart->add(new LineItem('test', 'test', 'test'));

        return $cart;
    }
}
