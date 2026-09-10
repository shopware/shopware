<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\CoreSubscriber;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[Package('framework')]
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
        $subscriber = new CoreSubscriber([], static::createStub(ScriptExecutor::class));
        $request = new Request();
        $event = new RequestEvent(static::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->initializeCspNonce($event);

        $nonce = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_CSP_NONCE);

        static::assertIsString($nonce);
        // URL-safe Base64 alphabet without padding: no '+', '/' or '=' that could be mistaken for a URL
        static::assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $nonce);
        static::assertSame(24, \strlen($nonce));
    }

    public function testNonSuccessfulResponseDoesNotGetTouched(): void
    {
        $subscriber = new CoreSubscriber([], static::createStub(ScriptExecutor::class));
        $request = new Request();
        $response = new Response('', Response::HTTP_INTERNAL_SERVER_ERROR);

        $event = new ResponseEvent(static::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->setSecurityHeaders($event);

        static::assertCount(2, $response->headers->all());
    }

    public function testSuccessfullyGetTouched(): void
    {
        $subscriber = new CoreSubscriber([], static::createStub(ScriptExecutor::class));
        $request = new Request();
        $request->server->set('HTTPS', 'on');
        $response = new Response();

        $event = new ResponseEvent(static::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->setSecurityHeaders($event);

        static::assertCount(6, $response->headers->all());
    }

    public function testCSP(): void
    {
        $subscriber = new CoreSubscriber(['admin' => 'default-src \'self\'; script-src \'self\' \'nonce-%nonce%\';'], static::createStub(ScriptExecutor::class));
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['admin']);
        $request->server->set('HTTPS', 'on');
        $response = new Response();

        $event = new ResponseEvent(static::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->initializeCspNonce(new RequestEvent(static::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $subscriber->setSecurityHeaders($event);

        static::assertCount(7, $response->headers->all());
    }
}
