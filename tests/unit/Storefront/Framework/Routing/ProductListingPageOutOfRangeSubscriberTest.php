<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Framework\Routing\ProductListingPageOutOfRangeSubscriber;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[CoversClass(ProductListingPageOutOfRangeSubscriber::class)]
class ProductListingPageOutOfRangeSubscriberTest extends TestCase
{
    public function testSubscribesToKernelExceptionWithExplicitPriority(): void
    {
        $events = ProductListingPageOutOfRangeSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(KernelEvents::EXCEPTION, $events);
        // Priority must be > -100 (NotFoundSubscriber) so we run before the 404 page is rendered.
        static::assertSame(['onKernelException', 10], $events[KernelEvents::EXCEPTION]);
    }

    public function testRedirectsWithStrippedPParameterOnStorefrontRequest(): void
    {
        $event = $this->buildEvent(
            originalUri: '/Damen/?p=99',
            isSalesChannel: true,
            exception: ProductException::pageOutOfRange(99, 3),
        );

        (new ProductListingPageOutOfRangeSubscriber())->onKernelException($event);

        $response = $event->getResponse();
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_MOVED_PERMANENTLY, $response->getStatusCode());
        static::assertSame('/Damen/', $response->getTargetUrl());
    }

    public function testPreservesOtherQueryParameters(): void
    {
        $event = $this->buildEvent(
            originalUri: '/search?search=phone&p=42&order=price-asc&manufacturer-filter=apple',
            isSalesChannel: true,
            exception: ProductException::pageOutOfRange(42, 2),
        );

        (new ProductListingPageOutOfRangeSubscriber())->onKernelException($event);

        $response = $event->getResponse();
        static::assertInstanceOf(RedirectResponse::class, $response);
        $target = $response->getTargetUrl();
        static::assertStringStartsWith('/search?', $target);
        static::assertStringNotContainsString('p=42', $target);
        static::assertStringNotContainsString('p=', $target);
        static::assertStringContainsString('search=phone', $target);
        static::assertStringContainsString('order=price-asc', $target);
        static::assertStringContainsString('manufacturer-filter=apple', $target);
    }

    public function testFallsBackToCurrentRequestUriWhenOriginalAttributeMissing(): void
    {
        $request = new Request(['p' => 5]);
        $request->server->set('REQUEST_URI', '/some-path?p=5');
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST, true);

        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            ProductException::pageOutOfRange(5, 1),
        );

        (new ProductListingPageOutOfRangeSubscriber())->onKernelException($event);

        $response = $event->getResponse();
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/some-path', $response->getTargetUrl());
    }

    public static function provideNonRedirectCases(): \Generator
    {
        yield 'non-storefront request: do nothing (let 404 propagate)' => [
            'isSalesChannel' => false,
            'exception' => ProductException::pageOutOfRange(99, 3),
        ];

        yield 'storefront request, unrelated exception: do nothing' => [
            'isSalesChannel' => true,
            'exception' => new \RuntimeException('something else'),
        ];

        yield 'storefront request, ProductException with different code: do nothing' => [
            'isSalesChannel' => true,
            'exception' => ProductException::categoryNotFound('does-not-matter'),
        ];
    }

    #[DataProvider('provideNonRedirectCases')]
    public function testDoesNothingWhenNotApplicable(bool $isSalesChannel, \Throwable $exception): void
    {
        $event = $this->buildEvent(
            originalUri: '/Damen/?p=99',
            isSalesChannel: $isSalesChannel,
            exception: $exception,
        );

        (new ProductListingPageOutOfRangeSubscriber())->onKernelException($event);

        static::assertNull($event->getResponse());
    }

    public function testDoesNotClobberResponseAlreadySetByEarlierListener(): void
    {
        $event = $this->buildEvent(
            originalUri: '/Damen/?p=99',
            isSalesChannel: true,
            exception: ProductException::pageOutOfRange(99, 3),
        );

        $existingResponse = new Response('custom soft-404 body', Response::HTTP_GONE);
        $event->setResponse($existingResponse);

        (new ProductListingPageOutOfRangeSubscriber())->onKernelException($event);

        static::assertSame($existingResponse, $event->getResponse());
    }

    private function buildEvent(string $originalUri, bool $isSalesChannel, \Throwable $exception): ExceptionEvent
    {
        $request = new Request();
        if ($isSalesChannel) {
            $request->attributes->set(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST, true);
        }
        $request->attributes->set(RequestTransformer::ORIGINAL_REQUEST_URI, $originalUri);

        return new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );
    }
}
