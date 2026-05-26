<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Controller\AgenticDiscoveryController;
use Shopware\Storefront\Framework\Cache\AgenticDiscoveryCacheSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[CoversClass(AgenticDiscoveryCacheSubscriber::class)]
class AgenticDiscoveryCacheSubscriberTest extends TestCase
{
    public function testSubscribesToResponseEventAtMinusTwoThousand(): void
    {
        $events = AgenticDiscoveryCacheSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(KernelEvents::RESPONSE, $events);
        static::assertSame(['onKernelResponse', -2000], $events[KernelEvents::RESPONSE]);
    }

    public function testDoesNothingWhenRequestCarriesNoMarker(): void
    {
        $request = new Request();
        $response = new Response();
        $response->headers->set('Cache-Control', 'no-cache, private');

        $event = $this->createResponseEvent($request, $response);
        (new AgenticDiscoveryCacheSubscriber())->onKernelResponse($event);

        static::assertSame('no-cache, private', $response->headers->get('Cache-Control'));
        static::assertFalse($response->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testRewritesCacheHeadersWhenMarkerIsPresent(): void
    {
        $request = new Request();
        $request->attributes->set(AgenticDiscoveryController::REQUEST_ATTRIBUTE_AGENTIC_DISCOVERY, [
            'salesChannelId' => 'sc-abc',
            'maxAge' => 300,
            'sMaxAge' => 3600,
        ]);
        $response = new Response('body', 200);
        $response->headers->set('Cache-Control', 'no-cache, private');
        $response->headers->set('Set-Cookie', 'session-=foo; path=/; httponly');

        $event = $this->createResponseEvent($request, $response);
        (new AgenticDiscoveryCacheSubscriber())->onKernelResponse($event);

        $cacheControl = (string) $response->headers->get('Cache-Control');
        static::assertStringContainsString('public', $cacheControl);
        static::assertStringContainsString('max-age=300', $cacheControl);
        static::assertStringContainsString('s-maxage=3600', $cacheControl);
        static::assertStringContainsString('stale-while-revalidate=60', $cacheControl);
        static::assertSame('Host', $response->headers->get('Vary'));
        static::assertSame('agentic_discovery_sc-abc', $response->headers->get('sw-invalidation-states'));
        static::assertSame('1', $response->headers->get(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
        static::assertCount(0, $response->headers->getCookies(), 'Set-Cookie must be stripped');
    }

    public function testSkipsNonSuccessfulResponses(): void
    {
        $request = new Request();
        $request->attributes->set(AgenticDiscoveryController::REQUEST_ATTRIBUTE_AGENTIC_DISCOVERY, [
            'salesChannelId' => 'sc-abc',
            'maxAge' => 300,
            'sMaxAge' => 3600,
        ]);
        $response = new Response('not found', 404);
        $response->headers->set('Cache-Control', 'no-cache, private');

        $event = $this->createResponseEvent($request, $response);
        (new AgenticDiscoveryCacheSubscriber())->onKernelResponse($event);

        // Unchanged: cache subscriber only rewrites successful responses.
        static::assertSame('no-cache, private', $response->headers->get('Cache-Control'));
    }

    private function createResponseEvent(Request $request, Response $response): ResponseEvent
    {
        return new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );
    }
}
