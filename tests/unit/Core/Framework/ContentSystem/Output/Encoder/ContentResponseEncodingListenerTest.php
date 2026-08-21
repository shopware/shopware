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
        static::assertInstanceOf(EncodedContentPage::class, $replacement->getObject());
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

    #[TestDox('removes the field selection from all three bags of a skeleton response it does not replace')]
    public function testOnResponseStripsFieldSelectionFromEveryBagOfTheSkeletonResponse(): void
    {
        $request = new Request();
        $request->attributes->set('includes', ['content_skeleton_page' => ['elements']]);
        $request->attributes->set('excludes', ['content_skeleton_element' => ['style']]);
        $request->query->set('includes', ['content_skeleton_page' => ['elements']]);
        $request->query->set('excludes', ['content_skeleton_element' => ['style']]);
        $request->request->set('includes', ['content_skeleton_page' => ['elements']]);
        $request->request->set('excludes', ['content_skeleton_element' => ['style']]);

        $response = new ContentSkeletonRouteResponse(new ContentSkeletonPage('layout-1', [], 'Landing', '1.0.0'));
        $event = $this->event($request, $response);

        $this->listener()->onResponse($event);

        static::assertSame($response, $event->getResponse());
        static::assertFalse($request->attributes->has('includes'));
        static::assertFalse($request->attributes->has('excludes'));
        static::assertFalse($request->query->has('includes'));
        static::assertFalse($request->query->has('excludes'));
        static::assertFalse($request->request->has('includes'));
        static::assertFalse($request->request->has('excludes'));
    }

    #[TestDox('leaves a response that is not a content response untouched')]
    public function testOnResponseIgnoresAnUnrelatedResponse(): void
    {
        $response = new Response('unrelated');
        $request = new Request(['includes' => ['product' => ['name']]]);
        $event = $this->event($request, $response);

        $this->listener()->onResponse($event);

        static::assertSame($response, $event->getResponse());
        static::assertTrue($request->query->has('includes'), 'An unrelated response must keep its field selection.');
    }

    /**
     * @param 'attributes'|'query'|'request' $bag
     */
    #[DataProvider('fieldSelectionBagProvider')]
    #[TestDox('removes the field selection from the $bag bag of a content response')]
    public function testOnResponseRemovesFieldSelectionFromEveryBag(string $bag): void
    {
        $request = new Request();
        $request->{$bag}->set('includes', ['content_page' => ['elements']]);
        $request->{$bag}->set('excludes', ['content_element' => ['style']]);

        $event = $this->event($request, new ContentRouteResponse($this->renderResult()));

        $this->listener()->onResponse($event);

        static::assertFalse($request->{$bag}->has('includes'));
        static::assertFalse($request->{$bag}->has('excludes'));
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

    #[TestDox('removes the field selection from a format it does not replace, so no content format can be filtered')]
    public function testOnResponseRemovesFieldSelectionForANonReplacedFormat(): void
    {
        $request = new Request(['includes' => ['content_skeleton_page' => ['elements']]]);
        $event = $this->event($request, new ContentSkeletonRouteResponse(new ContentSkeletonPage('layout-1', [], 'Landing', '1.0.0')));

        $this->listener()->onResponse($event);

        static::assertFalse($request->query->has('includes'));
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
