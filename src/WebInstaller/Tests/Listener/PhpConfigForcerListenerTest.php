<?php
declare(strict_types=1);

namespace Shopware\WebInstaller\Tests\Listener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\WebInstaller\Listener\PhpConfigForcerListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(PhpConfigForcerListener::class)]
class PhpConfigForcerListenerTest extends TestCase
{
    public function testCallOnConfigurePageDoesNotCreateALoop(): void
    {
        $listener = new PhpConfigForcerListener($this->createMock(RouterInterface::class));

        $event = $this->createMock(RequestEvent::class);
        $request = $this->getRequest();
        $request->attributes->set('_route', 'configure');

        $event->method('getRequest')->willReturn($request);

        $listener($event);

        static::assertNull($event->getResponse());
    }

    public function testMissingRouteCreatesNoLoop(): void
    {
        $listener = new PhpConfigForcerListener($this->createMock(RouterInterface::class));

        $event = $this->createMock(RequestEvent::class);
        $request = $this->getRequest();

        $event->method('getRequest')->willReturn($request);

        $listener($event);

        static::assertNull($event->getResponse());
    }

    public function testCallOtherPageRedirectsPHP(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/configure');
        $listener = new PhpConfigForcerListener($router);

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $this->getRequest(),
            HttpKernelInterface::MAIN_REQUEST
        );
        $event->getRequest()->attributes->set('_route', 'install');

        $listener($event);

        static::assertInstanceOf(RedirectResponse::class, $event->getResponse());
    }

    public function getRequest(): Request
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
