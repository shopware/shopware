<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Cache\CacheWarmer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheIdLoader;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollection;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\Kernel;
use Shopware\Storefront\Framework\Cache\CacheStore;
use Shopware\Storefront\Framework\Cache\CacheWarmer\CacheWarmerTaskHandler;
use Shopware\Storefront\Framework\Cache\CacheWarmer\WarmUpMessage;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Framework\Cache\CacheWarmer\CacheWarmerTaskHandler
 */
class CacheWarmerTaskHandlerTest extends TestCase
{
    private MockObject&Kernel $kernel;

    private MockObject&RouterInterface $router;

    private MockObject&RequestTransformerInterface $requestTransformer;

    private MockObject&CacheIdLoader $cacheIdLoader;

    private MockObject&CacheTagCollection $cacheTagCollection;

    private CacheWarmerTaskHandler $handler;

    public function setUp(): void
    {
        $this->kernel = $this->createMock(Kernel::class);
        $this->router = $this->createMock(RouterInterface::class);
        $this->requestTransformer = $this->createMock(RequestTransformerInterface::class);
        $this->cacheIdLoader = $this->createMock(CacheIdLoader::class);
        $this->cacheTagCollection = $this->createMock(CacheTagCollection::class);

        $this->handler = new CacheWarmerTaskHandler(
            $this->kernel,
            $this->router,
            $this->requestTransformer,
            $this->cacheIdLoader,
            $this->cacheTagCollection
        );
    }

    public function testInvokeWithNotMatchingCacheIds(): void
    {
        $this->cacheIdLoader->expects(static::once())->method('load')->willReturn('cacheId');
        $this->kernel->expects(static::never())->method('handle');

        $message = new WarmUpMessage(
            'product.list',
            [['page' => '1'], ['page' => '2']],
        );
        $message->setCacheId('differentCacheId');

        $this->handler->__invoke($message);
    }

    public function testInvokeWillCallRoutes(): void
    {
        $this->cacheIdLoader->expects(static::once())->method('load')->willReturn('cacheId');

        $this->router->expects(static::exactly(2))->method('generate')->withConsecutive(
            ['product.list', ['page' => '1']],
            ['product.list', ['page' => '2']],
        )->willReturnOnConsecutiveCalls(
            '/product/list?page=1',
            '/product/list?page=2',
        );

        $request1 = Request::create('/product/list?page=1');
        $request2 = Request::create('/product/list?page=2');

        $this->requestTransformer->expects(static::exactly(2))->method('transform')->withConsecutive(
            [
                static::callback(static fn (Request $request) => $request->getRequestUri() === '/product/list?page=1'),
            ],
            [
                static::callback(static fn (Request $request) => $request->getRequestUri() === '/product/list?page=2'),
            ],
        )->willReturnOnConsecutiveCalls(
            $request1,
            $request2,
        );

        $this->cacheTagCollection->expects(static::exactly(2))
            ->method('reset');

        $this->kernel->expects(static::exactly(2))
            ->method('handle')
            ->withConsecutive(
                [
                    static::callback(static fn (Request $request) => $request->getRequestUri() === '/product/list?page=1'),
                ],
                [
                    static::callback(static fn (Request $request) => $request->getRequestUri() === '/product/list?page=2'),
                ],
            )->willReturn(new Response());

        $this->kernel->expects(static::once())
            ->method('reboot')
            ->with(null, null, 'cacheId');

        $container = new Container();
        $container->set(CacheStore::class, $this->createMock(CacheStore::class));

        $this->kernel->expects(static::once())
            ->method('getContainer')
            ->willReturn($container);

        $message = new WarmUpMessage(
            'product.list',
            [['page' => '1'], ['page' => '2']],
        );
        $message->setCacheId('cacheId');
        $message->setDomain('http://example.com');

        $this->handler->__invoke($message);
    }
}
