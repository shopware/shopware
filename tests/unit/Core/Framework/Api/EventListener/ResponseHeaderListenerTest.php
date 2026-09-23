<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\EventListener\ResponseHeaderListener;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ResponseHeaderListener::class)]
class ResponseHeaderListenerTest extends TestCase
{
    public function testSubscribesToKernelResponseEvent(): void
    {
        static::assertSame([
            KernelEvents::RESPONSE => 'onResponse',
        ], ResponseHeaderListener::getSubscribedEvents());
    }

    public function testCopiesVersionAndLanguageHeaders(): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_VERSION_ID, 'version-id');
        $request->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, 'language-id');

        $response = $this->handleResponse($request);

        static::assertSame('version-id', $response->headers->get(PlatformRequest::HEADER_VERSION_ID));
        static::assertSame('language-id', $response->headers->get(PlatformRequest::HEADER_LANGUAGE_ID));
    }

    public function testDoesNotCopyContextTokenHeader(): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'context-token');

        $response = $this->handleResponse($request);

        static::assertFalse($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    #[DisabledFeatures(['v6.8.0.0', 'CACHE_REWORK'])]
    public function testCopiesContextTokenHeaderWithoutCacheRework(): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'context-token');

        $response = $this->handleResponse($request);

        static::assertSame('context-token', $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testKeepsExplicitContextTokenHeaderFromResponse(): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'request-context-token');
        $response = new Response();
        $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'response-context-token');

        $response = $this->handleResponse($request, $response);

        static::assertSame('response-context-token', $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    private function handleResponse(Request $request, ?Response $response = null): Response
    {
        $response ??= new Response();

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        (new ResponseHeaderListener())->onResponse($event);

        return $event->getResponse();
    }
}
