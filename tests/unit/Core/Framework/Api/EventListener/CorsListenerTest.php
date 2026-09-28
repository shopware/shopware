<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Cors\CorsHeaderProviderInterface;
use Shopware\Core\Framework\Api\Cors\CorsHeaders;
use Shopware\Core\Framework\Api\EventListener\CorsListener;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CorsListener::class)]
class CorsListenerTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [
                KernelEvents::REQUEST => ['onKernelRequest', 9999],
                KernelEvents::RESPONSE => ['onKernelResponse', 9999],
            ],
            CorsListener::getSubscribedEvents(),
        );
    }

    public function testPreflightRequestIsShortCircuited(): void
    {
        $listener = new CorsListener([]);
        $event = new RequestEvent(
            static::createStub(HttpKernelInterface::class),
            Request::create('/store-api/_mcp', 'OPTIONS'),
            HttpKernelInterface::MAIN_REQUEST,
        );

        $listener->onKernelRequest($event);

        $response = $event->getResponse();
        static::assertNotNull($response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testNonOptionsRequestIsNotShortCircuited(): void
    {
        $listener = new CorsListener([]);
        $event = new RequestEvent(
            static::createStub(HttpKernelInterface::class),
            Request::create('/store-api/_mcp', 'POST'),
            HttpKernelInterface::MAIN_REQUEST,
        );

        $listener->onKernelRequest($event);

        static::assertNull($event->getResponse());
    }

    public function testSubRequestIsIgnored(): void
    {
        $listener = new CorsListener([]);
        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            Request::create('/store-api/_mcp', 'POST'),
            HttpKernelInterface::SUB_REQUEST,
            new Response(),
        );

        $listener->onKernelResponse($event);

        static::assertFalse($event->getResponse()->headers->has('Access-Control-Allow-Origin'));
    }

    public function testOriginAndMethodsDoNotDependOnTheProviders(): void
    {
        $headers = $this->dispatchResponse(new CorsListener([]));

        static::assertSame('*', $headers->get('Access-Control-Allow-Origin'));
        static::assertSame('GET,POST,PUT,PATCH,DELETE', $headers->get('Access-Control-Allow-Methods'));
    }

    public function testWithoutProvidersBothHeaderListsAreEmpty(): void
    {
        $headers = $this->dispatchResponse(new CorsListener([]));

        static::assertSame('', $headers->get('Access-Control-Allow-Headers'));
        static::assertSame('', $headers->get('Access-Control-Expose-Headers'));
    }

    public function testProvidersContributeToBothListsSeparately(): void
    {
        $listener = new CorsListener([
            new CallbackCorsHeaderProvider(static function (CorsHeaders $headers): void {
                $headers->addAllowed('sw-plan', 'sw-interval');
            }),
            new CallbackCorsHeaderProvider(static function (CorsHeaders $headers): void {
                $headers->addAllowed('sw-source');
                $headers->addExposed('sw-state');
            }),
        ]);

        $headers = $this->dispatchResponse($listener);

        static::assertSame('sw-plan,sw-interval,sw-source', $headers->get('Access-Control-Allow-Headers'));
        static::assertSame('sw-state', $headers->get('Access-Control-Expose-Headers'));
    }

    public function testAProviderCanRemoveWhatAnEarlierProviderContributed(): void
    {
        $listener = new CorsListener([
            new CallbackCorsHeaderProvider(static function (CorsHeaders $headers): void {
                $headers->addAllowed('sw-plan', 'sw-interval');
                $headers->addExposed('sw-state');
            }),
            new CallbackCorsHeaderProvider(static function (CorsHeaders $headers): void {
                $headers->removeAllowed('SW-PLAN');
                $headers->removeExposed('sw-state');
            }),
        ]);

        $headers = $this->dispatchResponse($listener);

        static::assertSame('sw-interval', $headers->get('Access-Control-Allow-Headers'));
        static::assertSame('', $headers->get('Access-Control-Expose-Headers'));
    }

    public function testContributedHeadersAreDeduplicated(): void
    {
        $listener = new CorsListener([
            new CallbackCorsHeaderProvider(static function (CorsHeaders $headers): void {
                $headers->addAllowed('sw-plan');
            }),
            new CallbackCorsHeaderProvider(static function (CorsHeaders $headers): void {
                $headers->addAllowed('SW-Plan', 'sw-interval');
            }),
        ]);

        $headers = $this->dispatchResponse($listener);

        static::assertSame('sw-plan,sw-interval', $headers->get('Access-Control-Allow-Headers'));
    }

    private function dispatchResponse(CorsListener $listener): ResponseHeaderBag
    {
        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            Request::create('/store-api/subscription/plan', 'POST'),
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        );

        $listener->onKernelResponse($event);

        return $event->getResponse()->headers;
    }
}

/**
 * @internal
 */
#[Package('framework')]
class CallbackCorsHeaderProvider implements CorsHeaderProviderInterface
{
    /**
     * @param \Closure(CorsHeaders): void $contribute
     */
    public function __construct(private readonly \Closure $contribute)
    {
    }

    public function provide(CorsHeaders $headers): void
    {
        ($this->contribute)($headers);
    }
}
