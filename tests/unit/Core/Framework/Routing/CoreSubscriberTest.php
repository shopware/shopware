<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\CoreSubscriber;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[CoversClass(CoreSubscriber::class)]
class CoreSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = CoreSubscriber::getSubscribedEvents();

        static::assertCount(2, $events);
        static::assertArrayHasKey('kernel.request', $events);
        static::assertArrayHasKey('kernel.response', $events);
    }

    public function testOnRequestNonceGenerated(): void
    {
        $subscriber = new CoreSubscriber([]);
        $request = new Request();
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->initializeCspNonce($event);

        static::assertNotNull($event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_CSP_NONCE));
    }

    public function testNonSuccessfulResponseDoesNotGetTouched(): void
    {
        $subscriber = new CoreSubscriber([]);
        $request = new Request();
        $response = new Response('', Response::HTTP_INTERNAL_SERVER_ERROR);

        $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->setSecurityHeaders($event);

        static::assertCount(2, $response->headers->all());
    }

    public function testSuccessfullyGetTouched(): void
    {
        $subscriber = new CoreSubscriber([]);
        $request = new Request();
        $request->server->set('HTTPS', 'on');
        $response = new Response();

        $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->setSecurityHeaders($event);

        static::assertCount(6, $response->headers->all());
    }

    public function testCSP(): void
    {
        $subscriber = new CoreSubscriber(['admin' => 'default-src \'self\'; script-src \'self\' \'nonce-%nonce%\';']);
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['admin']);
        $request->server->set('HTTPS', 'on');
        $response = new Response();

        $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->initializeCspNonce(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $subscriber->setSecurityHeaders($event);

        static::assertCount(7, $response->headers->all());
    }
}
