<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Routing\ContextAwareCacheHeadersService;
use Shopware\Core\Framework\Routing\ContextAwareCacheHeadersSubscriber;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[CoversClass(ContextAwareCacheHeadersSubscriber::class)]
class ContextAwareCacheHeadersSubscriberTest extends TestCase
{
    private ContextAwareCacheHeadersSubscriber $subscriber;

    private ContextAwareCacheHeadersService&MockObject $contextAwareCacheService;

    protected function setUp(): void
    {
        $this->contextAwareCacheService = $this->createMock(ContextAwareCacheHeadersService::class);
        $this->subscriber = new ContextAwareCacheHeadersSubscriber($this->contextAwareCacheService);
    }

    public function testOnResponseWithStoreApiResponse(): void
    {
        $context = Generator::generateSalesChannelContext();

        $request = $this->createRequest([ApiRouteScope::ID, StoreApiRouteScope::ID], $context);

        $response = new Response();

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $this->contextAwareCacheService
            ->expects($this->once())
            ->method('addContextHeaders')
            ->with($request, $response, $context);

        $this->subscriber->onResponse($event);
    }

    public function testOnResponseWithoutContext(): void
    {
        $request = $this->createRequest([StoreApiRouteScope::ID]);

        $response = new Response();

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $this->contextAwareCacheService
            ->expects($this->never())
            ->method('addContextHeaders');

        $this->subscriber->onResponse($event);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = ContextAwareCacheHeadersSubscriber::getSubscribedEvents();

        static::assertArrayHasKey('store-api.scope.response', $events);
        static::assertSame(['onResponse', -1000], $events['store-api.scope.response']);
    }

    /**
     * @param array<string> $scopes
     */
    private function createRequest(array $scopes = [], ?SalesChannelContext $context = null): Request
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, $scopes);

        if ($context !== null) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        }

        return $request;
    }
}
