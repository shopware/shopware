<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output\Encoder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\Output\Encoder\ContentDataPageEncoder;
use Shopware\Core\Framework\ContentSystem\Output\Encoder\ContentDecomposedPageEncoder;
use Shopware\Core\Framework\ContentSystem\Output\Encoder\ContentPageEncoder;
use Shopware\Core\Framework\ContentSystem\Output\Encoder\ContentResponseEncodingListener;
use Shopware\Core\Framework\ContentSystem\Output\Encoder\ResolvedValueIndexEncoder;
use Shopware\Core\Framework\ContentSystem\Output\Index\ResolvedValueIndex;
use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentSkeletonPage;
use Shopware\Core\Framework\ContentSystem\Output\Struct\EncodedContentPage;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentDataRouteResponse;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentDecomposedRouteResponse;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentRouteResponse;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentSkeletonRouteResponse;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Api\StructEncoder;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentResponseEncodingListener::class)]
class ContentResponseEncodingListenerTest extends TestCase
{
    #[TestDox('subscribes below the SEO resolver and above the store-api encoder, so the swap precedes the route encode event')]
    public function testSubscribesStrictlyBetweenTheTwoNeighbourPriorities(): void
    {
        $subscribed = ContentResponseEncodingListener::getSubscribedEvents();

        static::assertArrayHasKey(KernelEvents::RESPONSE, $subscribed);

        $listener = $subscribed[KernelEvents::RESPONSE];
        static::assertIsArray($listener);
        static::assertSame('onResponse', $listener[0]);
        static::assertIsInt($listener[1]);
        static::assertGreaterThan(10000, $listener[1]);
        static::assertLessThan(11000, $listener[1]);
    }

    #[TestDox('replaces the full-format response with the encoded carrier, keeping its status and headers')]
    public function testOnResponseReplacesTheFullFormatResponseWithTheCarrier(): void
    {
        $response = new ContentRouteResponse($this->renderResult());
        $response->setStatusCode(Response::HTTP_OK);
        $response->headers->set('sw-cache-tags', 'content-layout-1');

        $event = $this->event(new Request(), $response);

        $this->listener()->onResponse($event);

        $replacement = $event->getResponse();
        static::assertNotSame($response, $replacement);
        static::assertInstanceOf(StoreApiResponse::class, $replacement);

        $body = $replacement->getObject();
        static::assertInstanceOf(EncodedContentPage::class, $body);
        static::assertSame(ContentPageEncoder::PAGE_API_ALIAS, $body->getApiAlias());
        static::assertSame([], $body->jsonSerialize()['elements'] ?? null);
        static::assertSame(Response::HTTP_OK, $replacement->getStatusCode());
        static::assertSame('content-layout-1', $replacement->headers->get('sw-cache-tags'));
    }

    #[TestDox('replaces the decomposed-format response with the decomposed encoder body, keeping its status and headers')]
    public function testOnResponseReplacesTheDecomposedFormatResponseWithTheDecomposedBody(): void
    {
        $response = new ContentDecomposedRouteResponse($this->indexedRenderResult());
        $response->setStatusCode(Response::HTTP_OK);
        $response->headers->set('sw-cache-tags', 'content-layout-1');

        $event = $this->event(new Request(), $response);

        $this->listener()->onResponse($event);

        $replacement = $event->getResponse();
        static::assertNotSame($response, $replacement);
        static::assertInstanceOf(StoreApiResponse::class, $replacement);

        $body = $replacement->getObject();
        static::assertInstanceOf(EncodedContentPage::class, $body);
        static::assertSame('content_decomposed_page', $body->getApiAlias());
        static::assertArrayHasKey('skeletons', $body->jsonSerialize());
        static::assertSame('T-shirt', $body->jsonSerialize()['data']['product-ref-1'] ?? null);
        static::assertSame(['root' => ['title' => 'product-ref-1']], $body->jsonSerialize()['assignments'] ?? null);
        static::assertSame(Response::HTTP_OK, $replacement->getStatusCode());
        static::assertSame('content-layout-1', $replacement->headers->get('sw-cache-tags'));
    }

    #[TestDox('replaces the data-format response with the data encoder body')]
    public function testOnResponseReplacesTheDataFormatResponseWithTheDataBody(): void
    {
        $response = new ContentDataRouteResponse($this->indexedRenderResult());

        $event = $this->event(new Request(), $response);

        $this->listener()->onResponse($event);

        $replacement = $event->getResponse();
        static::assertNotSame($response, $replacement);
        static::assertInstanceOf(StoreApiResponse::class, $replacement);

        $body = $replacement->getObject();
        static::assertInstanceOf(EncodedContentPage::class, $body);
        static::assertSame('content_data_page', $body->getApiAlias());
        static::assertSame('T-shirt', $body->jsonSerialize()['data']['product-ref-1'] ?? null);
        static::assertSame(['root' => ['title' => 'product-ref-1']], $body->jsonSerialize()['assignments'] ?? null);
        static::assertArrayNotHasKey('skeletons', $body->jsonSerialize());
        static::assertArrayNotHasKey('elements', $body->jsonSerialize());
    }

    #[TestDox('leaves the skeleton format to the framework encoder')]
    public function testOnResponseDoesNotReplaceTheSkeletonResponse(): void
    {
        $response = new ContentSkeletonRouteResponse(new ContentSkeletonPage('layout-1', [], 'Landing', '1.0.0'));
        $event = $this->event(new Request(), $response);

        $this->listener()->onResponse($event);

        static::assertSame($response, $event->getResponse());
    }

    #[TestDox('leaves a response that is not a content response untouched')]
    public function testOnResponseIgnoresAnUnrelatedResponse(): void
    {
        $response = new Response('unrelated');
        $event = $this->event(new Request(), $response);

        $this->listener()->onResponse($event);

        static::assertSame($response, $event->getResponse());
    }

    /**
     * @param 'attributes'|'query'|'request' $bag
     */
    #[DataProvider('fieldSelectionBagProvider')]
    #[TestDox('leaves the $bag bag of a content request untouched: the refusal happens at the route, not here')]
    public function testOnResponseLeavesEveryParameterBagUntouched(string $bag): void
    {
        $request = new Request();
        $request->{$bag}->set('includes', ['content_page' => ['elements']]);
        $request->{$bag}->set('excludes', ['content_element' => ['style']]);

        $event = $this->event($request, new ContentRouteResponse($this->renderResult()));

        $this->listener()->onResponse($event);

        static::assertTrue($request->{$bag}->has('includes'));
        static::assertTrue($request->{$bag}->has('excludes'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function fieldSelectionBagProvider(): iterable
    {
        yield 'attributes' => ['attributes'];
        yield 'query' => ['query'];
        yield 'request' => ['request'];
    }

    private function listener(): ContentResponseEncodingListener
    {
        $structEncoder = static::createStub(StructEncoder::class);
        $indexEncoder = new ResolvedValueIndexEncoder($structEncoder);

        return new ContentResponseEncodingListener(
            new ContentPageEncoder($structEncoder),
            new ContentDecomposedPageEncoder($indexEncoder),
            new ContentDataPageEncoder($indexEncoder),
        );
    }

    private function renderResult(): RenderResult
    {
        return new RenderResult(
            [],
            LayoutReference::create('layout-1', 'Landing', '1.0.0'),
            null,
        );
    }

    private function indexedRenderResult(): RenderResult
    {
        return new RenderResult(
            [new RenderedElement('root', 'Sw:Content:Text')],
            LayoutReference::create('layout-1', 'Landing', '1.0.0'),
            new ResolvedValueIndex(['product-ref-1' => 'T-shirt'], ['root' => ['title' => 'product-ref-1']]),
        );
    }

    private function event(Request $request, Response $response): ResponseEvent
    {
        return new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );
    }
}
