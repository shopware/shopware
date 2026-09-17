<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent;
use Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EventDispatcherBehaviour;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(HttpCacheKeyGenerator::class)]
#[CoversClass(HttpCacheKeyEvent::class)]
#[Group('cache')]
class HttpCacheKeyGeneratorTest extends TestCase
{
    use EventDispatcherBehaviour;

    private HttpCacheKeyGenerator $cacheKeyGenerator;

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $this->cacheKeyGenerator = new HttpCacheKeyGenerator('foo', $this->eventDispatcher, ['_ga']);
    }

    #[DataProvider('differentKeyProvider')]
    public function testDifferentCacheKey(Request $requestA, Request $requestB): void
    {
        $keyA = $this->cacheKeyGenerator->generate($requestA);
        $keyB = $this->cacheKeyGenerator->generate($requestB);

        static::assertNotSame($keyA->key, $keyB->key);
        static::assertTrue($keyA->isCacheable);
        static::assertTrue($keyB->isCacheable);
    }

    #[DataProvider('sameKeyProvider')]
    public function testSameCacheKey(Request $requestA, Request $requestB): void
    {
        $keyA = $this->cacheKeyGenerator->generate($requestA);
        $keyB = $this->cacheKeyGenerator->generate($requestB);

        static::assertSame($keyA->key, $keyB->key);
        static::assertTrue($keyA->isCacheable);
        static::assertTrue($keyB->isCacheable);
    }

    public function testCookiesFromResponseOverwriteRequestCookies(): void
    {
        $request = Request::create('https://domain.com/method', 'GET', [], [HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE => 'foo']);

        $response = new Response();
        $response->headers->setCookie(new Cookie(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, 'bar'));

        $keyA = $this->cacheKeyGenerator->generate($request);
        $keyB = $this->cacheKeyGenerator->generate($request, $response);

        static::assertNotSame($keyA->key, $keyB->key);
        static::assertTrue($keyA->isCacheable);
        static::assertTrue($keyB->isCacheable);
    }

    public function testNonCacheableCacheCookieSetsNoCacheOnCacheKey(): void
    {
        $request = Request::create('https://domain.com/method', 'GET', [], [HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE => HttpCacheCookieEvent::NOT_CACHEABLE]);

        $key = $this->cacheKeyGenerator->generate($request);

        static::assertFalse($key->isCacheable);
    }

    public function testNonCacheableCacheHashHeaderSetsNoCacheOnCacheKey(): void
    {
        $request = Request::create('https://domain.com/method');
        $request->headers->set(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, HttpCacheCookieEvent::NOT_CACHEABLE);

        $key = $this->cacheKeyGenerator->generate($request);

        static::assertFalse($key->isCacheable);
    }

    public function testCacheHashHeaderIsEquivalentToCacheHashCookie(): void
    {
        $cookieRequest = Request::create('https://domain.com/method', 'GET', [], [HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE => 'foo']);

        $headerRequest = Request::create('https://domain.com/method');
        $headerRequest->headers->set(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, 'foo');

        static::assertSame(
            $this->cacheKeyGenerator->generate($cookieRequest)->key,
            $this->cacheKeyGenerator->generate($headerRequest)->key
        );
    }

    public function testCacheHashHeaderTakesPrecedenceOverRequestCookie(): void
    {
        $cookieAndHeaderRequest = Request::create('https://domain.com/method', 'GET', [], [HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE => 'foo']);
        $cookieAndHeaderRequest->headers->set(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, 'bar');

        $headerOnlyRequest = Request::create('https://domain.com/method');
        $headerOnlyRequest->headers->set(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, 'bar');

        static::assertSame(
            $this->cacheKeyGenerator->generate($headerOnlyRequest)->key,
            $this->cacheKeyGenerator->generate($cookieAndHeaderRequest)->key
        );
    }

    public function testResponseCookieTakesPrecedenceOverCacheHashHeader(): void
    {
        $headerRequest = Request::create('https://domain.com/method');
        $headerRequest->headers->set(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, 'bar');

        $response = new Response();
        $response->headers->setCookie(new Cookie(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, 'baz'));

        $cookieOnlyRequest = Request::create('https://domain.com/method', 'GET', [], [HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE => 'baz']);

        static::assertSame(
            $this->cacheKeyGenerator->generate($cookieOnlyRequest)->key,
            $this->cacheKeyGenerator->generate($headerRequest, $response)->key
        );
    }

    public function testCacheKeyStaysTheSameIfEventPartsAreSortedDifferently(): void
    {
        $request = Request::create('https://domain.com/method');
        $firstKey = $this->cacheKeyGenerator->generate($request);

        $this->addEventListener($this->eventDispatcher, HttpCacheKeyEvent::class, static function (HttpCacheKeyEvent $event): void {
            $uri = $event->get('uri');
            self::assertIsString($uri);
            $event->remove('uri');
            $event->add('uri', $uri);
        });

        $secondKey = $this->cacheKeyGenerator->generate($request);
        static::assertSame($firstKey->key, $secondKey->key);
        static::assertTrue($firstKey->isCacheable);
        static::assertTrue($secondKey->isCacheable);
    }

    public function testCacheKeyIsNotCacheableIfSetInEvent(): void
    {
        $request = Request::create('https://domain.com/method');
        $firstKey = $this->cacheKeyGenerator->generate($request);

        $this->addEventListener($this->eventDispatcher, HttpCacheKeyEvent::class, static function (HttpCacheKeyEvent $event): void {
            $event->isCacheable = false;
        });

        $secondKey = $this->cacheKeyGenerator->generate($request);
        static::assertSame($firstKey->key, $secondKey->key);
        static::assertTrue($firstKey->isCacheable);
        static::assertFalse($secondKey->isCacheable);
    }

    public static function sameKeyProvider(): \Generator
    {
        yield 'same Url with same get Parameter in different order' => [
            Request::create('https://domain.com/method?limit=1&order=ASC'),
            Request::create('https://domain.com/method?order=ASC&limit=1'),
        ];

        yield 'same URL with excluded parameter from ignore list' => [
            Request::create('https://domain.com/method'),
            Request::create('https://domain.com/method?_ga=1'),
        ];

        yield 'same Url with lost question mark' => [
            Request::create('https://domain.com/method?'),
            Request::create('https://domain.com/method'),
        ];

        yield 'same Url with same cookies' => [
            Request::create('https://domain.com/method', 'GET', [], [HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE => 'foo']),
            Request::create('https://domain.com/method', 'GET', [], [HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE => 'foo']),
        ];

        yield 'same Url with identical sw-language-id header' => [
            self::createRequestWithHeaders('https://domain.com/method', [PlatformRequest::HEADER_LANGUAGE_ID => 'language-a']),
            self::createRequestWithHeaders('https://domain.com/method', [PlatformRequest::HEADER_LANGUAGE_ID => 'language-a']),
        ];

        yield 'same Url with empty sw-language-id header treated as absent' => [
            self::createRequestWithHeaders('https://domain.com/method', [PlatformRequest::HEADER_LANGUAGE_ID => '']),
            Request::create('https://domain.com/method'),
        ];

        yield 'same Url with same cache hash: domain currency attribute is masked by the hash' => [
            self::createRequestWithDomainCurrency('currency-a', 'hash'),
            self::createRequestWithDomainCurrency('currency-b', 'hash'),
        ];
    }

    public static function differentKeyProvider(): \Generator
    {
        yield 'Urls with different actions' => [
            Request::create('https://domain.com/actionA'),
            Request::create('https://domain.com/actionB'),
        ];

        yield 'Urls with same Action, but different Get Parameters' => [
            Request::create('https://domain.com/actionA?limit=1'),
            Request::create('https://domain.com/actionA?limit=2'),
        ];

        yield 'same Url with different cookies' => [
            Request::create('https://domain.com/method', 'GET', [], [HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE => 'foo']),
            Request::create('https://domain.com/method', 'GET', [], [HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE => 'bar']),
        ];

        yield 'same Url with different sw-language-id headers' => [
            self::createRequestWithHeaders('https://domain.com/method', [PlatformRequest::HEADER_LANGUAGE_ID => 'language-a']),
            self::createRequestWithHeaders('https://domain.com/method', [PlatformRequest::HEADER_LANGUAGE_ID => 'language-b']),
        ];

        yield 'same Url with sw-language-id differing only in letter case (matched byte-exact)' => [
            self::createRequestWithHeaders('https://domain.com/method', [PlatformRequest::HEADER_LANGUAGE_ID => 'language-a']),
            self::createRequestWithHeaders('https://domain.com/method', [PlatformRequest::HEADER_LANGUAGE_ID => 'LANGUAGE-A']),
        ];

        yield 'same Url with sw-access-key differing only in letter case (case-sensitive credential)' => [
            self::createRequestWithHeaders('https://domain.com/method', [PlatformRequest::HEADER_ACCESS_KEY => 'access-key-a']),
            self::createRequestWithHeaders('https://domain.com/method', [PlatformRequest::HEADER_ACCESS_KEY => 'ACCESS-KEY-A']),
        ];

        yield 'same Url with and without sw-language-id header' => [
            self::createRequestWithHeaders('https://domain.com/method', [PlatformRequest::HEADER_LANGUAGE_ID => 'language-a']),
            Request::create('https://domain.com/method'),
        ];

        yield 'same Url with different sw-access-key headers' => [
            self::createRequestWithHeaders('https://domain.com/method', [PlatformRequest::HEADER_ACCESS_KEY => 'access-key-a']),
            self::createRequestWithHeaders('https://domain.com/method', [PlatformRequest::HEADER_ACCESS_KEY => 'access-key-b']),
        ];

        yield 'same Url with and without sw-access-key header' => [
            self::createRequestWithHeaders('https://domain.com/method', [PlatformRequest::HEADER_ACCESS_KEY => 'access-key-a']),
            Request::create('https://domain.com/method'),
        ];

        yield 'same Url with different sw-currency-id headers' => [
            self::createRequestWithHeaders('https://domain.com/method', [PlatformRequest::HEADER_CURRENCY_ID => 'currency-a']),
            self::createRequestWithHeaders('https://domain.com/method', [PlatformRequest::HEADER_CURRENCY_ID => 'currency-b']),
        ];

        yield 'same Url with and without sw-currency-id header' => [
            self::createRequestWithHeaders('https://domain.com/method', [PlatformRequest::HEADER_CURRENCY_ID => 'currency-a']),
            Request::create('https://domain.com/method'),
        ];

        yield 'same Url with different sales channel base urls (storefront language/domain selector)' => [
            self::createRequestWithBaseUrl('https://domain.com/method', '/de'),
            self::createRequestWithBaseUrl('https://domain.com/method', '/en'),
        ];

        yield 'same Url with and without sales channel base url' => [
            self::createRequestWithBaseUrl('https://domain.com/method', '/de'),
            Request::create('https://domain.com/method'),
        ];

        yield 'same Url with different domain currency attributes and no cache hash' => [
            self::createRequestWithDomainCurrency('currency-a'),
            self::createRequestWithDomainCurrency('currency-b'),
        ];

        yield 'same Url with different sw-cache-hash headers and no cookies' => [
            self::createRequestWithHeaders('https://domain.com/method', [HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE => 'foo']),
            self::createRequestWithHeaders('https://domain.com/method', [HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE => 'bar']),
        ];
    }

    /**
     * @param array<string, string> $headers
     */
    private static function createRequestWithHeaders(string $uri, array $headers): Request
    {
        $request = Request::create($uri);
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        return $request;
    }

    private static function createRequestWithBaseUrl(string $uri, string $baseUrl): Request
    {
        $request = Request::create($uri);
        // \Shopware\Storefront\Framework\Routing\RequestTransformer::SALES_CHANNEL_BASE_URL,
        // \Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator::SALES_CHANNEL_BASE_URL
        $request->attributes->set('sw-sales-channel-base-url', $baseUrl);

        return $request;
    }

    private static function createRequestWithDomainCurrency(string $currencyId, ?string $cacheHash = null): Request
    {
        $cookies = $cacheHash !== null ? [HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE => $cacheHash] : [];
        $request = Request::create('https://domain.com/method', 'GET', [], $cookies);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID, $currencyId);

        return $request;
    }
}
