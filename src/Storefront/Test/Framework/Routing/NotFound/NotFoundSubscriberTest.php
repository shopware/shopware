<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Routing\NotFound;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopware\Storefront\Controller\ErrorController;
use Shopware\Storefront\Framework\Routing\NotFound\NotFoundSubscriber;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Framework\Routing\NotFound\NotFoundSubscriber
 */
class NotFoundSubscriberTest extends TestCase
{
    public function testDebugIsOnDoesNothing(): void
    {
        $subscriber = new NotFoundSubscriber(
            $this->createMock(ErrorController::class),
            $this->createMock(RequestStack::class),
            $this->createMock(SalesChannelContextServiceInterface::class),
            true,
            $this->createMock(CacheInterface::class),
            $this->createMock(AbstractCacheTracer::class),
            $this->createMock(EntityCacheKeyGenerator::class),
            $this->createMock(CacheInvalidator::class),
            new EventDispatcher()
        );

        $event = new ExceptionEvent(
            $this->createMock(Kernel::class),
            new Request(),
            0,
            new \Exception()
        );
        $subscriber->onError($event);

        static::assertNull($event->getResponse());
    }

    public function testErrorHandled(): void
    {
        $controller = $this->createMock(ErrorController::class);
        $controller
            ->expects(static::once())
            ->method('error')
            ->willReturn(new StorefrontResponse());

        $cacheTracer = $this->createMock(AbstractCacheTracer::class);
        $cacheTracer
            ->expects(static::once())
            ->method('trace')
            ->willReturnCallback(fn (string $name, \Closure $closure) => $closure());

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getMainRequest')->willReturn(new Request());

        $subscriber = new NotFoundSubscriber(
            $controller,
            $requestStack,
            $this->createMock(SalesChannelContextServiceInterface::class),
            false,
            new TagAwareAdapter(new ArrayAdapter(), new ArrayAdapter()),
            $cacheTracer,
            $this->createMock(EntityCacheKeyGenerator::class),
            $this->createMock(CacheInvalidator::class),
            new EventDispatcher()
        );

        $request = new Request();

        $event = new ExceptionEvent(
            $this->createMock(Kernel::class),
            $request,
            0,
            new HttpException(Response::HTTP_NOT_FOUND)
        );
        $subscriber->onError($event);

        /** @var StorefrontResponse $response */
        $response = $event->getResponse();

        static::assertInstanceOf(Response::class, $response);
        static::assertInstanceOf(StorefrontResponse::class, $response);
        static::assertEmpty($response->getData());
        static::assertNull($response->getContext());
    }

    public function testOtherExceptionsDoesNotGetCached(): void
    {
        $controller = $this->createMock(ErrorController::class);
        $controller
            ->expects(static::once())
            ->method('error')
            ->willReturn(new StorefrontResponse());

        $cacheTracer = $this->createMock(AbstractCacheTracer::class);
        $cacheTracer
            ->expects(static::never())
            ->method('trace');

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getMainRequest')->willReturn(new Request());

        $subscriber = new NotFoundSubscriber(
            $controller,
            $requestStack,
            $this->createMock(SalesChannelContextServiceInterface::class),
            false,
            new TagAwareAdapter(new ArrayAdapter(), new ArrayAdapter()),
            $cacheTracer,
            $this->createMock(EntityCacheKeyGenerator::class),
            $this->createMock(CacheInvalidator::class),
            new EventDispatcher()
        );

        $request = new Request();

        $event = new ExceptionEvent(
            $this->createMock(Kernel::class),
            $request,
            0,
            new \Exception()
        );
        $subscriber->onError($event);

        static::assertInstanceOf(Response::class, $event->getResponse());
    }

    /**
     * @dataProvider providerSystemConfigKeys
     */
    public function testInvalidationHappensOnSystemConfigChange(string $key, bool $shouldInvalidate): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator
            ->expects($shouldInvalidate ? static::once() : static::never())
            ->method('invalidate');

        $subscriber = new NotFoundSubscriber(
            $this->createMock(ErrorController::class),
            $this->createMock(RequestStack::class),
            $this->createMock(SalesChannelContextServiceInterface::class),
            true,
            $this->createMock(CacheInterface::class),
            $this->createMock(AbstractCacheTracer::class),
            $this->createMock(EntityCacheKeyGenerator::class),
            $cacheInvalidator,
            new EventDispatcher()
        );

        $subscriber->onSystemConfigChanged(new SystemConfigChangedEvent($key, 'foo', null));
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function providerSystemConfigKeys(): iterable
    {
        yield 'key matches' => [
            'core.basicInformation.http404Page',
            true,
        ];

        yield 'key not matches' => [
            'core.http404Page',
            false,
        ];
    }

    public function testSubscribedEvents(): void
    {
        static::assertArrayHasKey(SystemConfigChangedEvent::class, NotFoundSubscriber::getSubscribedEvents());

        static::assertArrayHasKey(KernelEvents::EXCEPTION, NotFoundSubscriber::getSubscribedEvents());
    }
}
