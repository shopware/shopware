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
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EsiDecoration::class)]
class EsiDecorationTest extends TestCase
{
    private HttpKernelInterface&Stub $kernel;

    private HttpCache $cache;

    protected function setUp(): void
    {
        $this->kernel = static::createStub(HttpKernelInterface::class);
        $this->cache = new HttpCache($this->kernel, static::createStub(StoreInterface::class));

        // The HttpCache kernel needs a request to be set
        $request = new Request();
        $ref = new \ReflectionObject($this->cache);
        $reqRef = $ref->getProperty('request');
        $reqRef->setValue($this->cache, $request);
    }

    public function testHandle(): void
    {
        $this->kernel
            ->method('handle')
            ->willReturnCallback(static function (Request $request) {
                static::assertTrue($request->attributes->getBoolean('_sw_esi'));

                return new Response('foo');
            });

        $esi = new EsiDecoration();
        $content = $esi->handle($this->cache, '/foo', '', false);

        static::assertSame('foo', $content);
    }

    public function testHandleCircularReference(): void
    {
        $esi = new EsiDecoration();

        $this->kernel->method('handle')->willReturnCallback(function () use ($esi) {
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
        $this->kernel
            ->method('handle')
            ->willReturn(new Response('foo', Response::HTTP_INTERNAL_SERVER_ERROR));

        $esi = new EsiDecoration();

        $this->expectExceptionObject(new \RuntimeException('Error when rendering "http://localhost/foo" (Status code is 500).'));

        $esi->handle($this->cache, '/foo', '', false);
    }

    public function testHandleErrorWithAlt(): void
    {
        $this->kernel
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
        $this->kernel
            ->method('handle')
            ->willReturn(new Response('foo', Response::HTTP_INTERNAL_SERVER_ERROR));

        $esi = new EsiDecoration();
        $content = $esi->handle($this->cache, '/foo', '', true);

        static::assertSame('', $content);
    }
}
