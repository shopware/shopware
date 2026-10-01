<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Routing\ClearSiteDataListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ClearSiteDataListener::class)]
class ClearSiteDataListenerTest extends TestCase
{
    /**
     * The storefront-scoped event keeps every other scope (Store API, Admin API) out of reach.
     */
    public function testSubscribesToTheStorefrontScopedResponseEvent(): void
    {
        static::assertSame(
            ['storefront.scope.response' => 'onResponse'],
            ClearSiteDataListener::getSubscribedEvents()
        );
    }

    public function testHeaderIsSentForAnOptedInStorefrontResponse(): void
    {
        $response = $this->dispatch(['cookies', 'storage'], $this->createStorefrontLogoutRequest());

        static::assertSame('"cookies", "storage"', $response->headers->get('Clear-Site-Data'));
    }

    public function testNoHeaderWithoutConfiguredDirectives(): void
    {
        $response = $this->dispatch([], $this->createStorefrontLogoutRequest());

        static::assertFalse($response->headers->has('Clear-Site-Data'));
    }

    public function testNoHeaderWhenTheRequestDidNotOptIn(): void
    {
        $request = $this->createStorefrontLogoutRequest();
        $request->attributes->remove(PlatformRequest::ATTRIBUTE_CLEAR_SITE_DATA);

        $response = $this->dispatch(['storage'], $request);

        static::assertFalse($response->headers->has('Clear-Site-Data'));
    }

    public function testNoHeaderOnSubRequests(): void
    {
        $response = $this->dispatch(['storage'], $this->createStorefrontLogoutRequest(), HttpKernelInterface::SUB_REQUEST);

        static::assertFalse($response->headers->has('Clear-Site-Data'));
    }

    /**
     * `_httpCache` is not a boolean attribute, so reading it with `getBoolean()` would throw here.
     *
     * @param array<string, mixed>|bool|string|int $httpCache
     */
    #[DataProvider('httpCacheAttributeProvider')]
    public function testNoHeaderForAnyHttpCacheAttributeShape(array|bool|string|int $httpCache): void
    {
        $request = $this->createStorefrontLogoutRequest();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, $httpCache);

        $response = $this->dispatch(['storage'], $request);

        static::assertFalse($response->headers->has('Clear-Site-Data'));
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>|bool|string|int}>
     */
    public static function httpCacheAttributeProvider(): iterable
    {
        yield 'bool' => [true];
        yield 'string' => ['true'];
        yield 'int' => [1];
        yield 'array' => [['maxAge' => 3600]];
    }

    /**
     * A `Clear-Site-Data` wipe affects the whole origin, so only a deliberate top-level navigation
     * the visitor triggered themselves may cause it.
     *
     * @param array<string, string|null> $headers
     */
    #[DataProvider('fetchMetadataProvider')]
    public function testOnlyDeliberateNavigationsAreAccepted(array $headers, bool $expectHeader): void
    {
        $request = $this->createStorefrontLogoutRequest();

        foreach ($headers as $name => $value) {
            if ($value === null) {
                $request->headers->remove($name);
            } else {
                $request->headers->set($name, $value);
            }
        }

        $response = $this->dispatch(['storage'], $request);

        static::assertSame($expectHeader, $response->headers->has('Clear-Site-Data'));
    }

    /**
     * @return iterable<string, array{0: array<string, string|null>, 1: bool}>
     */
    public static function fetchMetadataProvider(): iterable
    {
        yield 'same-origin navigation' => [[], true];
        yield 'same-site navigation' => [['Sec-Fetch-Site' => 'same-site'], true];
        yield 'bookmark or typed url' => [['Sec-Fetch-Site' => 'none'], true];
        yield 'cross-site navigation' => [['Sec-Fetch-Site' => 'cross-site'], false];
        yield 'missing Sec-Fetch-Site' => [['Sec-Fetch-Site' => null], false];
        yield 'image or fetch subresource' => [['Sec-Fetch-Mode' => 'no-cors', 'Sec-Fetch-Dest' => 'image'], false];
        yield 'xhr' => [['Sec-Fetch-Mode' => 'cors', 'Sec-Fetch-Dest' => 'empty'], false];
        yield 'iframe' => [['Sec-Fetch-Dest' => 'iframe'], false];
        yield 'missing Sec-Fetch-Mode' => [['Sec-Fetch-Mode' => null], false];
        yield 'missing Sec-Fetch-Dest' => [['Sec-Fetch-Dest' => null], false];
        yield 'speculative prefetch' => [['Sec-Purpose' => 'prefetch'], false];
        yield 'speculative prerender' => [['Sec-Purpose' => 'prefetch;prerender'], false];
    }

    private function createStorefrontLogoutRequest(): Request
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CLEAR_SITE_DATA, true);
        $request->headers->set('Sec-Fetch-Site', 'same-origin');
        $request->headers->set('Sec-Fetch-Mode', 'navigate');
        $request->headers->set('Sec-Fetch-Dest', 'document');

        return $request;
    }

    /**
     * @param list<string> $directives
     */
    private function dispatch(array $directives, Request $request, int $requestType = HttpKernelInterface::MAIN_REQUEST): Response
    {
        $response = new Response();

        $listener = new ClearSiteDataListener($directives);
        $listener->onResponse(
            new ResponseEvent(static::createStub(HttpKernelInterface::class), $request, $requestType, $response)
        );

        return $response;
    }
}
