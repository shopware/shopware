<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing\Telemetry;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Telemetry\AreaResolver;
use Shopware\Core\Framework\Routing\Telemetry\DomainResolver;
use Shopware\Core\Framework\Routing\Telemetry\HttpRequestMetricSubscriber;
use Shopware\Core\Framework\Routing\Telemetry\OperationResolver;
use Shopware\Core\Framework\Telemetry\Doctrine\QueryCounter;
use Shopware\Core\Framework\Telemetry\Doctrine\QueryCountMiddleware;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Telemetry\Telemetry;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(HttpRequestMetricSubscriber::class)]
class HttpRequestMetricSubscriberTest extends TestCase
{
    /**
     * @var list<ConfiguredMetric>
     */
    private array $emitted = [];

    public function testSubscribesToResponseAndTerminate(): void
    {
        static::assertSame(
            [
                KernelEvents::RESPONSE => 'onKernelResponse',
                KernelEvents::TERMINATE => 'onKernelTerminate',
            ],
            HttpRequestMetricSubscriber::getSubscribedEvents()
        );
    }

    public function testResolvesLabelsFromRoutedRequestCapturedOnResponse(): void
    {
        $subscriber = $this->createSubscriber();

        // routed (transformed) request carries the route attributes
        $routed = Request::create('/');
        $routed->attributes->set('_route', 'frontend.detail.page');
        $routed->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['storefront']);
        $subscriber->onKernelResponse($this->createResponseEvent($routed, HttpKernelInterface::MAIN_REQUEST));

        // terminate gets the pre-transform request without route attributes
        $subscriber->onKernelTerminate($this->createTerminateEvent('', [], 200, microtime(true)));

        static::assertSame('storefront', $this->getMetric('http.server.request.duration')->labels['area']);
    }

    public function testIgnoresEsiFragmentsAndSubRequests(): void
    {
        $subscriber = $this->createSubscriber();

        // page request is captured
        $page = Request::create('/');
        $page->attributes->set('_route', 'frontend.detail.page');
        $page->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['storefront']);
        $subscriber->onKernelResponse($this->createResponseEvent($page, HttpKernelInterface::MAIN_REQUEST));

        // an ESI fragment must not overwrite the page request
        $fragment = Request::create('/');
        $fragment->attributes->set('_sw_esi', true);
        $fragment->attributes->set('_route', 'frontend.cms.page');
        $fragment->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['store-api']);
        $subscriber->onKernelResponse($this->createResponseEvent($fragment, HttpKernelInterface::MAIN_REQUEST));

        // a real sub-request must not overwrite it either
        $sub = Request::create('/');
        $sub->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['store-api']);
        $subscriber->onKernelResponse($this->createResponseEvent($sub, HttpKernelInterface::SUB_REQUEST));

        $subscriber->onKernelTerminate($this->createTerminateEvent('', [], 200, microtime(true)));

        static::assertSame('storefront', $this->getMetric('http.server.request.duration')->labels['area']);
    }

    public function testEmitsMetricsWithSharedLabels(): void
    {
        $counter = new QueryCounter();
        $counter->increment();
        $counter->increment();
        $counter->increment();

        $this->createSubscriber($counter)
            ->onKernelTerminate($this->createTerminateEvent('store-api.product.search', ['store-api'], 200, microtime(true)));

        static::assertCount(3, $this->emitted);

        $sharedLabels = ['area' => 'store-api', 'domain' => 'product', 'operation' => 'none'];

        $duration = $this->getMetric('http.server.request.duration');
        static::assertIsFloat($duration->value);
        static::assertGreaterThanOrEqual(0.0, $duration->value);
        static::assertSame($sharedLabels + ['status_class' => '2xx'], $duration->labels);

        $queries = $this->getMetric('http.server.request.queries.count');
        static::assertSame(3, $queries->value);
        static::assertSame($sharedLabels, $queries->labels);

        $memory = $this->getMetric('http.server.request.memory.peak');
        static::assertIsInt($memory->value);
        static::assertGreaterThan(0, $memory->value);
        static::assertSame($sharedLabels, $memory->labels);

        // counter is reset after emission so the next request starts fresh
        static::assertSame(0, $counter->count());
    }

    public function testQueryCountIsNotEmittedWhenMiddlewareIsNotWired(): void
    {
        // connection without the counting middleware in its chain — the metric is skipped entirely
        $this->createSubscriber()
            ->onKernelTerminate($this->createTerminateEvent('frontend.detail.page', ['storefront'], 200, microtime(true)));

        $names = array_map(static fn (ConfiguredMetric $m): string => $m->name, $this->emitted);
        static::assertNotContains('http.server.request.queries.count', $names);
    }

    public function testStatusClassReflectsResponseCode(): void
    {
        $this->createSubscriber()
            ->onKernelTerminate($this->createTerminateEvent('frontend.detail.page', ['storefront'], 404, microtime(true)));

        static::assertSame('4xx', $this->getMetric('http.server.request.duration')->labels['status_class']);
    }

    public function testDurationIsSkippedWithoutRequestStartTime(): void
    {
        $this->createSubscriber()
            ->onKernelTerminate($this->createTerminateEvent('frontend.detail.page', ['storefront'], 200, null));

        $names = array_map(static fn (ConfiguredMetric $m): string => $m->name, $this->emitted);
        static::assertNotContains('http.server.request.duration', $names);
    }

    private function getMetric(string $name): ConfiguredMetric
    {
        foreach ($this->emitted as $metric) {
            if ($metric->name === $name) {
                return $metric;
            }
        }

        static::fail(\sprintf('Metric "%s" was not emitted', $name));
    }

    /**
     * @param QueryCounter|null $counter when null, the connection exposes no counting middleware
     */
    private function createSubscriber(?QueryCounter $counter = null): HttpRequestMetricSubscriber
    {
        $telemetry = static::createStub(Telemetry::class);
        $telemetry->method('emit')->willReturnCallback(function (ConfiguredMetric $metric): void {
            $this->emitted[] = $metric;
        });

        $configuration = static::createStub(Configuration::class);
        $configuration->method('getMiddlewares')->willReturn($counter === null ? [] : [new QueryCountMiddleware($counter)]);
        $connection = static::createStub(Connection::class);
        $connection->method('getConfiguration')->willReturn($configuration);

        return new HttpRequestMetricSubscriber(
            $telemetry,
            new AreaResolver(),
            new DomainResolver(new EntityGroupResolver()),
            new OperationResolver(),
            $connection,
        );
    }

    /**
     * @param list<string> $scopes
     */
    private function createTerminateEvent(string $route, array $scopes, int $statusCode, ?float $requestTime): TerminateEvent
    {
        $request = Request::create('/');
        $request->attributes->set('_route', $route);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, $scopes);
        if ($requestTime === null) {
            $request->server->remove('REQUEST_TIME_FLOAT');
        } else {
            $request->server->set('REQUEST_TIME_FLOAT', $requestTime);
        }

        return new TerminateEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            new Response('', $statusCode)
        );
    }

    private function createResponseEvent(Request $request, int $requestType): ResponseEvent
    {
        return new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            $requestType,
            new Response()
        );
    }
}
