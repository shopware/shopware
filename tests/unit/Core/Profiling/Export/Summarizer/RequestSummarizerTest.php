<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\Export\Summarizer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Export\CollectorDataConverter;
use Shopware\Core\Profiling\Export\Summarizer\RequestSummarizer;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RequestSummarizer::class)]
class RequestSummarizerTest extends TestCase
{
    public function testSupportsOnlyTheRequestCollector(): void
    {
        $summarizer = new RequestSummarizer(new CollectorDataConverter());

        static::assertTrue($summarizer->supports(new RequestDataCollector()));
        static::assertFalse($summarizer->supports(new TimeDataCollector()));
    }

    public function testSummarizesTheRequestWithoutServerVariables(): void
    {
        $request = Request::create(
            '/store-api/product?limit=1',
            'POST',
            ['includes' => ['product' => ['id']]],
            ['sw-context' => 'abc'],
            [],
            ['HTTP_SW_ACCESS_KEY' => 'SWSCKEY', 'APP_SECRET' => 'secret'],
        );
        $request->attributes->set('_route', 'store-api.product.search');
        $response = new Response('{}', 200, ['Content-Type' => 'application/json']);
        $response->headers->setCookie(Cookie::create('session', 'xyz'));

        $collector = new RequestDataCollector();
        $collector->onKernelController(new ControllerEvent(static::createStub(HttpKernelInterface::class), (new RequestSummarizerControllerFixture())->search(...), $request, HttpKernelInterface::MAIN_REQUEST));
        $collector->collect($request, $response);
        $collector->lateCollect();

        $summary = (new RequestSummarizer(new CollectorDataConverter()))->summarize($collector, null);

        static::assertSame('POST', $summary['method']);
        static::assertSame('/store-api/product', $summary['path']);
        static::assertSame('store-api.product.search', $summary['route']);
        static::assertSame(RequestSummarizerControllerFixture::class . '::search', $summary['controller']);
        static::assertSame(200, $summary['status']);
        static::assertSame('application/json', $summary['contentType']);
        static::assertSame(['limit' => '1'], $summary['query']);
        static::assertSame(['includes' => ['product' => ['id']]], $summary['body']);
        static::assertSame('SWSCKEY', $summary['requestHeaders']['sw-access-key']);
        static::assertSame(['sw-context' => 'abc'], $summary['cookies']);
        static::assertArrayHasKey('session', $summary['responseCookies']);
        static::assertSame([], $summary['sessionAttributes']);
        static::assertIsInt($summary['serverVars']);
        static::assertArrayNotHasKey('request_server', $summary);
    }
}

/**
 * @internal
 */
class RequestSummarizerControllerFixture
{
    public function search(): Response
    {
        return new Response();
    }
}
