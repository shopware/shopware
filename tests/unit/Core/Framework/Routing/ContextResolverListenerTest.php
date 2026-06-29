<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\ContextResolverListener;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedControllerEvent;
use Shopware\Core\Framework\Routing\RequestContextResolverInterface;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[CoversClass(ContextResolverListener::class)]
class ContextResolverListenerTest extends TestCase
{
    public function testListenerCanRedirectViaControllerEvent(): void
    {
        $context = $this->createMock(SalesChannelContext::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);

        $redirect = new RedirectResponse('/account/login');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            SalesChannelContextResolvedControllerEvent::class,
            static function (SalesChannelContextResolvedControllerEvent $event) use ($redirect, $request, $context): void {
                static::assertSame($request, $event->getRequest());
                static::assertSame($context, $event->getSalesChannelContext());

                $event->getControllerEvent()->setController(static fn (): Response => $redirect);
            }
        );

        $listener = new ContextResolverListener($this->createMock(RequestContextResolverInterface::class), $dispatcher);
        $controllerEvent = $this->controllerEvent($request, static fn (): Response => new Response('original'));

        $listener->resolveContext($controllerEvent);

        static::assertSame($redirect, ($controllerEvent->getController())());
    }

    public function testControllerUntouchedWithoutSubscriber(): void
    {
        $request = new Request();
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT,
            $this->createMock(SalesChannelContext::class)
        );

        $listener = new ContextResolverListener(
            $this->createMock(RequestContextResolverInterface::class),
            new EventDispatcher()
        );

        $original = static fn (): Response => new Response('original');
        $controllerEvent = $this->controllerEvent($request, $original);

        $listener->resolveContext($controllerEvent);

        static::assertSame($original, $controllerEvent->getController());
    }

    public function testNoEventWhenNoSalesChannelContext(): void
    {
        $dispatched = false;

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            SalesChannelContextResolvedControllerEvent::class,
            static function () use (&$dispatched): void {
                $dispatched = true;
            }
        );

        $listener = new ContextResolverListener($this->createMock(RequestContextResolverInterface::class), $dispatcher);
        $controllerEvent = $this->controllerEvent(new Request(), static fn (): Response => new Response('original'));

        $listener->resolveContext($controllerEvent);

        static::assertFalse($dispatched);
    }

    private function controllerEvent(Request $request, callable $controller): ControllerEvent
    {
        return new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            $controller,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );
    }
}
