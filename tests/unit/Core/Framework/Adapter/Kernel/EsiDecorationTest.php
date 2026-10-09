<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Kernel\EsiDecoration;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EsiDecoration::class)]
class EsiDecorationTest extends TestCase
{
    private HttpCache&Stub $cache;

    protected function setUp(): void
    {
        $this->cache = static::createStub(HttpCache::class);
        // The sub request copies cookies and server parameters from the main request the cache is handling
        $this->cache->method('getRequest')->willReturn(new Request(cookies: ['session' => 'abc']));
    }

    public function testHandle(): void
    {
        $this->cache
            ->method('handle')
            ->willReturnCallback(static function (Request $request, int $type) {
                static::assertTrue($request->attributes->getBoolean('_sw_esi'));
                static::assertSame(HttpKernelInterface::SUB_REQUEST, $type);
                static::assertSame('abc', $request->cookies->get('session'));

                return new Response('foo');
            });

        $esi = new EsiDecoration();
        $content = $esi->handle($this->cache, '/foo', '', false);

        static::assertSame('foo', $content);
    }

    public function testHandleCircularReference(): void
    {
        $esi = new EsiDecoration();

        $this->cache->method('handle')->willReturnCallback(function () use ($esi) {
            // this call will cause the circular reference exception
            $esi->handle($this->cache, '/foo', '', false);

            return new Response();
        });

        $this->expectExceptionObject(AdapterException::circularReferenceEsi(['/foo', '/foo']));

        // this is the first call
        $esi->handle($this->cache, '/foo', '', false);
    }

    public function testHandleError(): void
    {
        $this->cache
            ->method('handle')
            ->willReturn(new Response('foo', Response::HTTP_INTERNAL_SERVER_ERROR));

        $esi = new EsiDecoration();

        $this->expectExceptionObject(new \RuntimeException('Error when rendering "http://localhost/foo" (Status code is 500).'));

        $esi->handle($this->cache, '/foo', '', false);
    }

    public function testHandleErrorWithAlt(): void
    {
        $this->cache
            ->method('handle')
            ->willReturnCallback(static function (Request $request) {
                if ($request->getPathInfo() === '/foo') {
                    return new Response('foo', Response::HTTP_INTERNAL_SERVER_ERROR);
                }

                return new Response('bar');
            });

        $esi = new EsiDecoration();
        $content = $esi->handle($this->cache, '/foo', '/bar', false);

        static::assertSame('bar', $content);
    }

    public function testHandleErrorWithIgnoreErrors(): void
    {
        $this->cache
            ->method('handle')
            ->willReturn(new Response('foo', Response::HTTP_INTERNAL_SERVER_ERROR));

        $esi = new EsiDecoration();
        $content = $esi->handle($this->cache, '/foo', '', true);

        static::assertSame('', $content);
    }
}
