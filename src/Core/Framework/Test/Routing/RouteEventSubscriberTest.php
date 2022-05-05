<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Routing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\RouteEventSubscriber;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
class RouteEventSubscriberTest extends TestCase
{
    public function testRequestEvent(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'frontend.home.page');

        $event = new RequestEvent($this->createMock(Kernel::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('frontend.home.page.request', $listener);

        $subscriber = new RouteEventSubscriber($dispatcher);
        $subscriber->request($event);
    }

    public function testResponseEvent(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'frontend.home.page');

        $event = new ResponseEvent($this->createMock(Kernel::class), $request, HttpKernelInterface::MAIN_REQUEST, new Response());

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('frontend.home.page.response', $listener);

        $subscriber = new RouteEventSubscriber($dispatcher);
        $subscriber->response($event);
    }

    public function testRenderEvent(): void
    {
        if (!\class_exists(StorefrontRenderEvent::class)) {
            //storefront dependency not installed
            return;
        }

        $request = new Request();
        $request->attributes->set('_route', 'frontend.home.page');

        $event = new StorefrontRenderEvent('', [], $request, $this->createMock(SalesChannelContext::class));

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('frontend.home.page.render', $listener);

        $subscriber = new RouteEventSubscriber($dispatcher);
        $subscriber->render($event);
    }
}
