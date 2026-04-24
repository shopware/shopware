<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing\NotFound;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Kernel;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopware\Core\Test\Assert\Serialization;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Storefront\Framework\Routing\Exception\ErrorRedirectRequestEvent;
use Shopware\Storefront\Framework\Routing\NotFound\NotFoundSubscriber;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(NotFoundSubscriber::class)]
class NotFoundSubscriberTest extends TestCase
{
    public function testDebugIsOnDoesNothing(): void
    {
        $event = $this->createExceptionEvent();

        $this->createNotFoundSubscriber(debug: true)->onError($event);

        static::assertNull($event->getResponse());
    }

    public function testErrorHandled(): void
    {
        $event = $this->createExceptionEvent();

        $kernelResponse = new Response();
        $this->createNotFoundSubscriber($kernelResponse)->onError($event);

        $response = $event->getResponse();

        static::assertSame($kernelResponse, $response);
        static::assertTrue($event->isPropagationStopped());
    }

    public function testCookiesAreNotPersistedToNotFoundPages(): void
    {
        $response = new Response();
        $response->headers->setCookie(new Cookie('extension-cookie', '1'));
        $response->headers->setCookie(new Cookie(PlatformRequest::FALLBACK_SESSION_NAME, '1'));

        $arrayAdapter = new ArrayAdapter();

        $this->createNotFoundSubscriber($response, adapter: $arrayAdapter)->onError($this->createExceptionEvent());

        $writtenCaches = array_values($arrayAdapter->getValues());

        static::assertArrayHasKey(0, $writtenCaches);

        $cacheItem = Serialization::assertUnserializedInstanceOf(Response::class, $writtenCaches[0]);

        $cookies = $cacheItem->headers->getCookies();
        static::assertCount(1, $cookies);

        static::assertSame('extension-cookie', $cookies[0]->getName());
    }

    public function testOtherExceptionsDoNotGetCached(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CAPTCHA, true);

        $exception = new \RuntimeException('test');

        $event = $this->createExceptionEvent($request, $exception);

        $kernelResponse = new Response();
        $eventDispatcher = new CollectingEventDispatcher();
        $subscriber = $this->createNotFoundSubscriber(
            $kernelResponse,
            static::callback(static function (Request $request) {
                return $request->attributes->get(PlatformRequest::ATTRIBUTE_CAPTCHA) === false;
            }),
            eventDispatcher: $eventDispatcher
        );

        $subscriber->onError($event);

        static::assertSame($kernelResponse, $event->getResponse());

        $dispatchedEvents = $eventDispatcher->getEvents();
        static::assertCount(1, $dispatchedEvents);
        $dispatchedEvent = $dispatchedEvents[0];
        static::assertInstanceOf(ErrorRedirectRequestEvent::class, $dispatchedEvent);
        $dispatchedException = $dispatchedEvent->getRequest()->attributes->get('exception');
        static::assertSame($exception, $dispatchedException);

        $subscriber->reset();
    }

    #[DataProvider('providerSystemConfigKeys')]
    public function testInvalidationHappensOnSystemConfigChange(string $key, bool $shouldInvalidate): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator
            ->expects($shouldInvalidate ? $this->once() : $this->never())
            ->method('invalidate');

        $subscriber = $this->createNotFoundSubscriber(cacheInvalidator: $cacheInvalidator);

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

    private function createNotFoundSubscriber(
        ?Response $kernelResponse = null,
        mixed $kernelCallback = null,
        bool $debug = false,
        ArrayAdapter $adapter = new ArrayAdapter(),
        ?CacheInvalidator $cacheInvalidator = null,
        EventDispatcherInterface $eventDispatcher = new EventDispatcher(),
    ): NotFoundSubscriber {
        $kernel = $this->createMock(HttpKernelInterface::class);
        if ($kernelResponse !== null) {
            $kernel
                ->expects($this->once())
                ->method('handle')
                ->willReturn($kernelResponse);
        }

        if ($kernelCallback !== null) {
            $kernel->method('handle')->with($kernelCallback);
        }

        $cacheInvalidator ??= static::createStub(CacheInvalidator::class);

        return new NotFoundSubscriber(
            $kernel,
            static::createStub(SalesChannelContextServiceInterface::class),
            $debug,
            new TagAwareAdapter($adapter, $adapter),
            static::createStub(EntityCacheKeyGenerator::class),
            $cacheInvalidator,
            $eventDispatcher
        );
    }

    private function createExceptionEvent(
        Request $request = new Request(),
        \Exception $exception = new HttpException(Response::HTTP_NOT_FOUND),
    ): ExceptionEvent {
        return new ExceptionEvent(
            static::createStub(Kernel::class),
            $request,
            0,
            $exception,
        );
    }
}
