<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver;
use Shopware\Core\Framework\Routing\Telemetry\AreaResolver;
use Shopware\Core\Framework\Routing\Telemetry\DomainResolver;
use Shopware\Core\Framework\Routing\Telemetry\HttpRequestMetricSubscriber;
use Shopware\Core\Framework\Routing\Telemetry\OperationResolver;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Telemetry\Telemetry;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[CoversClass(HttpRequestMetricSubscriber::class)]
class HttpRequestMetricSubscriberTest extends TestCase
{
    /**
     * @var list<ConfiguredMetric>
     */
    private array $emitted = [];

    public function testSubscribesToTerminate(): void
    {
        static::assertSame(
            [KernelEvents::TERMINATE => 'onKernelTerminate'],
            HttpRequestMetricSubscriber::getSubscribedEvents()
        );
    }

    public function testEmitsMetricsWithSharedLabels(): void
    {
        $this->createSubscriber()
            ->onKernelTerminate($this->createTerminateEvent('store-api.product.search', ['store-api'], 200, microtime(true)));

        static::assertCount(2, $this->emitted);

        $sharedLabels = ['area' => 'store-api', 'domain' => 'product', 'operation' => 'none'];

        $duration = $this->getMetric('http.server.request.duration');
        static::assertIsFloat($duration->value);
        static::assertGreaterThanOrEqual(0.0, $duration->value);
        static::assertSame($sharedLabels + ['status_class' => '2xx'], $duration->labels);

        $memory = $this->getMetric('http.server.request.memory.peak');
        static::assertIsInt($memory->value);
        static::assertGreaterThan(0, $memory->value);
        static::assertSame($sharedLabels, $memory->labels);
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

    private function createSubscriber(): HttpRequestMetricSubscriber
    {
        $telemetry = $this->createMock(Telemetry::class);
        $telemetry->method('emit')->willReturnCallback(function (ConfiguredMetric $metric): void {
            $this->emitted[] = $metric;
        });

        return new HttpRequestMetricSubscriber(
            $telemetry,
            new AreaResolver(),
            new DomainResolver(new EntityGroupResolver()),
            new OperationResolver(),
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
            $this->createMock(HttpKernelInterface::class),
            $request,
            new Response('', $statusCode)
        );
    }
}
