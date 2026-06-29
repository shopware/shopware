<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Telemetry;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Telemetry\Telemetry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Emits per-master-request HTTP metrics on kernel.terminate, which fires once per master request
 * (sub-requests excluded). Resolves the `area`, `domain` and `operation` labels once and reuses them
 * across all metrics.
 *
 * @internal
 *
 * @experimental feature:TELEMETRY_METRICS
 */
#[Package('framework')]
final class HttpRequestMetricSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Telemetry $telemetry,
        private readonly AreaResolver $areaResolver,
        private readonly DomainResolver $domainResolver,
        private readonly OperationResolver $operationResolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'onKernelTerminate',
        ];
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();

        $labels = [
            'area' => $this->areaResolver->resolve($request),
            'domain' => $this->domainResolver->resolve($request),
            'operation' => $this->operationResolver->resolve($request),
        ];

        $requestStart = $request->server->get('REQUEST_TIME_FLOAT');
        if ($requestStart !== null) {
            $this->telemetry->emit(new ConfiguredMetric(
                name: 'http.server.request.duration',
                value: (microtime(true) - (float) $requestStart) * 1000,
                labels: $labels + ['status_class' => $this->statusClass($event->getResponse()->getStatusCode())],
            ));
        }

        $this->telemetry->emit(new ConfiguredMetric(
            name: 'http.server.request.memory.peak',
            value: memory_get_peak_usage(true),
            labels: $labels,
        ));
    }

    private function statusClass(int $statusCode): string
    {
        return intdiv($statusCode, 100) . 'xx';
    }
}
