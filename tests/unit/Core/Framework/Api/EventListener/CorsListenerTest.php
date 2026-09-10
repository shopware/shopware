<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Cors\CorsHeaderProviderInterface;
use Shopware\Core\Framework\Api\EventListener\CorsListener;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
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
        $listener = new CorsListener();
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
        $listener = new CorsListener();
        $event = new RequestEvent(
            static::createStub(HttpKernelInterface::class),
            Request::create('/store-api/_mcp', 'POST'),
            HttpKernelInterface::MAIN_REQUEST,
        );

        $listener->onKernelRequest($event);

        static::assertNull($event->getResponse());
    }

    public function testResponseContainsMcpCorsHeaders(): void
    {
        $listener = new CorsListener();
        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            Request::create('/store-api/_mcp', 'POST'),
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        );

        $listener->onKernelResponse($event);

        $headers = $event->getResponse()->headers;
        static::assertSame('*', $headers->get('Access-Control-Allow-Origin'));

        $allowedHeaders = explode(',', (string) $headers->get('Access-Control-Allow-Headers'));
        static::assertContains(PlatformRequest::HEADER_MCP_SESSION_ID, $allowedHeaders);
        static::assertContains(PlatformRequest::HEADER_MCP_PROTOCOL_VERSION, $allowedHeaders);
        static::assertContains(PlatformRequest::HEADER_CONTEXT_TOKEN, $allowedHeaders);
        static::assertContains(PlatformRequest::HEADER_ACCESS_KEY, $allowedHeaders);

        $exposedHeaders = explode(',', (string) $headers->get('Access-Control-Expose-Headers'));
        static::assertContains(PlatformRequest::HEADER_MCP_SESSION_ID, $exposedHeaders);
    }

    public function testSubRequestIsIgnored(): void
    {
        $listener = new CorsListener();
        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            Request::create('/store-api/_mcp', 'POST'),
            HttpKernelInterface::SUB_REQUEST,
            new Response(),
        );

        $listener->onKernelResponse($event);

        static::assertFalse($event->getResponse()->headers->has('Access-Control-Allow-Origin'));
    }

    public function testDefaultHeadersAreUnchangedWithoutProviders(): void
    {
        $default = 'Content-Type,Authorization,sw-context-token,sw-access-key,sw-language-id,sw-version-id,'
            . 'sw-inheritance,indexing-behavior,sw-include-seo-urls,mcp-session-id,mcp-protocol-version';

        $headers = $this->dispatchResponse(new CorsListener());

        static::assertSame($default, $headers->get('Access-Control-Allow-Headers'));
        static::assertSame($default, $headers->get('Access-Control-Expose-Headers'));
    }

    public function testProvidersContributeAdditionalHeaders(): void
    {
        $listener = new CorsListener([
            new StaticCorsHeaderProvider(['sw-subscription-plan', 'sw-subscription-interval'], []),
            new StaticCorsHeaderProvider(['sw-context-source'], ['sw-subscription-state']),
        ]);

        $headers = $this->dispatchResponse($listener);

        $allowed = explode(',', (string) $headers->get('Access-Control-Allow-Headers'));
        static::assertContains('sw-subscription-plan', $allowed);
        static::assertContains('sw-subscription-interval', $allowed);
        static::assertContains('sw-context-source', $allowed);
        static::assertNotContains('sw-subscription-state', $allowed);

        $exposed = explode(',', (string) $headers->get('Access-Control-Expose-Headers'));
        static::assertContains('sw-subscription-state', $exposed);
        static::assertNotContains('sw-subscription-plan', $exposed);

        // the core defaults keep their place in front of the contributed headers
        static::assertSame('Content-Type', $allowed[0]);
        static::assertContains(PlatformRequest::HEADER_MCP_SESSION_ID, $allowed);
    }

    public function testContributedHeadersAreDeduplicated(): void
    {
        $listener = new CorsListener([
            new StaticCorsHeaderProvider(['Authorization', 'SW-Context-Token', 'sw-subscription-plan'], []),
            new StaticCorsHeaderProvider(['sw-subscription-plan'], []),
        ]);

        $allowed = explode(',', (string) $this->dispatchResponse($listener)->get('Access-Control-Allow-Headers'));

        static::assertCount(1, array_keys($allowed, 'Authorization', true));
        static::assertCount(1, array_keys($allowed, 'sw-subscription-plan', true));
        static::assertNotContains('SW-Context-Token', $allowed);
        static::assertContains(PlatformRequest::HEADER_CONTEXT_TOKEN, $allowed);
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
class StaticCorsHeaderProvider implements CorsHeaderProviderInterface
{
    /**
     * @param list<string> $allowed
     * @param list<string> $exposed
     */
    public function __construct(
        private readonly array $allowed,
        private readonly array $exposed
    ) {
    }

    public function getAllowedHeaders(): array
    {
        return $this->allowed;
    }

    public function getExposedHeaders(): array
    {
        return $this->exposed;
    }
}
