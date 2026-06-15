<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Routing\RouteEventSubscriber;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(RouteEventSubscriber::class)]
class RouteEventSubscriberTest extends TestCase
{
    private HttpKernelInterface&Stub $kernel;

    private EventDispatcher $dispatcher;

    private RouteEventSubscriber $subscriber;

    private CallableClass&MockObject $listener;

    private CallableClass&MockObject $secondListener;

    protected function setUp(): void
    {
        $this->kernel = static::createStub(HttpKernelInterface::class);
        $this->dispatcher = new EventDispatcher();
        $this->subscriber = new RouteEventSubscriber($this->dispatcher);
        $this->listener = $this->createMock(CallableClass::class);
        $this->secondListener = $this->createMock(CallableClass::class);
    }

    #[TestDox('getSubscribedEvents registers request, controller and response handlers at priority -10')]
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [
                KernelEvents::REQUEST => ['request', -10],
                KernelEvents::CONTROLLER => ['controller', -10],
                KernelEvents::RESPONSE => ['response', -10],
            ],
            RouteEventSubscriber::getSubscribedEvents()
        );
    }

    #[TestDox('No event is dispatched when the request has neither a route nor a scope')]
    public function testNoEventDispatchedWithoutRouteOrScope(): void
    {
        $request = new Request();

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $subscriber = new RouteEventSubscriber($dispatcher);

        $subscriber->request(new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $subscriber->controller(new ControllerEvent(
            $this->kernel,
            [CallableClassFoo::class, 'test'],
            $request,
            HttpKernelInterface::MAIN_REQUEST
        ));
        $subscriber->response(new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Response()));
    }

    #[TestDox('A scope event is dispatched for every route scope on the request')]
    public function testDispatchesEventForEachScope(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [ApiRouteScope::ID, StoreApiRouteScope::ID]);

        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->listener->expects($this->once())->method('__invoke');
        $this->secondListener->expects($this->once())->method('__invoke');

        $this->dispatcher->addListener('api.scope.request', $this->listener);
        $this->dispatcher->addListener('store-api.scope.request', $this->secondListener);

        $this->subscriber->request($event);
    }

    public function testRequestEvent(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'frontend.home.page');

        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->listener->expects($this->once())->method('__invoke');

        $this->dispatcher->addListener('frontend.home.page.request', $this->listener);

        $this->subscriber->request($event);
    }

    public function testResponseEvent(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'frontend.home.page');

        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Response());

        $this->listener->expects($this->once())->method('__invoke');

        $this->dispatcher->addListener('frontend.home.page.response', $this->listener);

        $this->subscriber->response($event);
    }

    public function testRequestScopeEvent(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'frontend.home.page');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [ApiRouteScope::ID]);

        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->listener->expects($this->once())->method('__invoke');

        $this->dispatcher->addListener('api.scope.request', $this->listener);

        $this->subscriber->request($event);
    }

    public function testControllerEvent(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'frontend.home.page');

        $event = new ControllerEvent(
            $this->kernel,
            [CallableClassFoo::class, 'test'],
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->listener->expects($this->once())->method('__invoke');

        $this->dispatcher->addListener('frontend.home.page.controller', $this->listener);

        $this->subscriber->controller($event);
    }

    public function testControllerScopeEvent(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'frontend.home.page');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [ApiRouteScope::ID]);

        $event = new ControllerEvent(
            $this->kernel,
            [CallableClassFoo::class, 'test'],
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->listener->expects($this->once())->method('__invoke');

        $this->dispatcher->addListener('api.scope.controller', $this->listener);

        $this->subscriber->controller($event);
    }

    public function testResponseScopeEvent(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'frontend.home.page');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [ApiRouteScope::ID]);

        $event = new ResponseEvent(
            $this->kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response()
        );

        $this->listener->expects($this->once())->method('__invoke');

        $this->dispatcher->addListener('api.scope.response', $this->listener);

        $this->subscriber->response($event);
    }
}

/**
 * @internal
 */
class CallableClassFoo
{
    public static function test(): void
    {
    }
}
