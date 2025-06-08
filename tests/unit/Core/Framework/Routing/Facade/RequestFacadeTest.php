<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\Facade\RequestFacade;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(RequestFacade::class)]
class RequestFacadeTest extends TestCase
{
    public function testUrl(): void
    {
        $request = new Request();
        $request->server->set('REQUEST_URI', '/foo/bar');
        $request->attributes->set('sw-original-request-uri', 'https://example.com/foo/bar');

        $facade = new RequestFacade($request);

        static::assertSame('https://example.com/foo/bar', $facade->uri());
    }

    public function testUrlOutsideStorefront(): void
    {
        $request = new Request();
        $request->server->set('REQUEST_URI', '/foo/bar');

        $facade = new RequestFacade($request);

        static::assertSame('/foo/bar', $facade->uri());
    }

    public function testPathInfo(): void
    {
        $request = new Request();
        $request->server->set('REQUEST_URI', '/foo/bar');
        $request->attributes->set('sw-original-request-uri', 'https://example.com/foo/bar');

        $facade = new RequestFacade($request);

        static::assertSame('/foo/bar', $facade->pathInfo());
    }

    public function testScheme(): void
    {
        $request = new Request();

        $facade = new RequestFacade($request);

        static::assertSame('http', $facade->scheme());
    }

    public function testMethod(): void
    {
        $request = new Request();
        $request->setMethod('POST');

        $facade = new RequestFacade($request);

        static::assertSame('POST', $facade->method());
    }

    public function testQuery(): void
    {
        $request = new Request();
        $request->query->set('foo', 'bar');

        $facade = new RequestFacade($request);

        static::assertSame(['foo' => 'bar'], $facade->query());
    }

    public function testRequest(): void
    {
        $request = new Request();
        $request->request->set('foo', 'bar');

        $facade = new RequestFacade($request);

        static::assertSame(['foo' => 'bar'], $facade->request());
    }

    public function testHeaders(): void
    {
        $request = new Request();
        $request->headers->set('foo', 'bar');
        $request->headers->set('accept', 'application/json');

        $facade = new RequestFacade($request);

        static::assertSame(['accept' => ['application/json']], $facade->headers());
    }

    public function testCookies(): void
    {
        $request = new Request();
        $request->cookies->set('foo', 'bar');

        $facade = new RequestFacade($request);

        static::assertSame(['foo' => 'bar'], $facade->cookies());
    }

    public function testIp(): void
    {
        $request = new Request();

        $facade = new RequestFacade($request);

        static::assertSame($request->getClientIp(), $facade->ip());
    }
}
