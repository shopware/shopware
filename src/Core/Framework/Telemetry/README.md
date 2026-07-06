# Telemetry
This component contains the code for the collection of telemetry in shopware applications.

Folder structure:
- `Metrics` - contains the abstractions for the metrics collection and reporting.
  - `Config` - metric and transport configuration loaded from `telemetry.yaml`.
  - `Exception` - telemetry-specific exceptions.
  - `Factory/MetricTransportFactoryInterface` - factory interface for creating transports from configuration.
  - `Metric` - value objects (`ConfiguredMetric`, `Metric`) and the `Type` enum.
  - `Transport` - `TransportCollection` that aggregates all registered transport factories.

## Metrics

### Supported Metric types
In the Shopware application, various types of metrics can be collected. These are defined in the `Type` enum:

- **Counter** - Represents a single numerical value that only increases and can be summed, such as the number of requests served, tasks completed, or errors. This metric is reported as a positive increment.

- **UpDownCounter** - Represents a single numerical value that can increase or decrease, such as the number of concurrently running tasks. This metric is reported as either a positive or negative increment

- **Gauge** - Represents a single numerical value, such as current memory usage or the number of active threads. This metric is reported as a current value.

- **Histogram** - Samples observations (typically things like request durations or response sizes) and counts them in predefined buckets. Each bucket represents a range of values, and the histogram tracks the number of observations that fall into each bucket. This allows for a detailed distribution analysis of the observed values.

For more details see the [OpenTelemetry Metrics API specification](https://opentelemetry.io/docs/specs/otel/metrics/api/#meter-operations) or
[Prometheus documentation](https://prometheus.io/docs/tutorials/understanding_metric_types/).

### Configuration

All metrics must be pre-configured under the `shopware.telemetry.metrics.definitions` key before they can be emitted. The framework ships default definitions in `src/Core/Framework/Resources/config/packages/telemetry.yaml`. Plugins, apps, and projects add their own definitions in a separate `config/packages/*.yaml` file — Symfony's configuration system deep-merges all files, so existing definitions are preserved.

The `Meter` validates each metric against this merged configuration at emit time. In `dev`/`test` environments, an unconfigured metric throws a `MissingMetricConfigurationException`. In production the exception is logged at error level and the metric is dropped (not emitted to transports).

A PHPStan rule (`NoUnconfiguredMetricAllowed`) additionally enforces at static analysis time that every `ConfiguredMetric` instantiation with a string-literal name has a matching definition in the configuration.

Example of adding metric definitions in a project or plugin:

```yaml
shopware:
    telemetry:
        metrics:
            namespace: '{example-metrics-namespace}'

            definitions:
                cache.invalidate.count:
                    description: 'Number of cache invalidations'
                    type: 'counter'

                messenger.message.size:
                    description: 'Size of the message in bytes'
                    enabled: true
                    type: 'histogram'
                    unit: 'byte'
                    parameters:
                        buckets: [0, 512, 1024, 2048, 4096, 8192, 16384, 32768]

                dal.associations.count:
                    description: 'Number of associations loaded'
                    type: 'histogram'
                    labels:
                        entity:
                            allowed_values: ['product', 'category', 'order']
```

Each definition supports:
- `type` (required) - one of `counter`, `updown_counter`, `gauge`, `histogram`.
- `description` (required) - human-readable description.
- `enabled` (optional, defaults to `true`) - allows disabling a metric without removing its emitters.
- `unit` (optional) - unit of measurement (e.g. `byte`).
- `parameters` (optional) - type-specific parameters (e.g. `buckets` for histograms).
- `labels` (optional) - map of label names to `allowed_values`. Labels supplied at emit time that are not in this allowlist are silently stripped.

### Transports

The telemetry component provides a way to emit metrics to different transports. The transport is responsible for sending the metrics to the monitoring backend. Each transport is created as an external library that implements the telemetry abstraction layer specification.

#### Creating a Transport

To create a new transport, you need to implement two interfaces:

1. `Shopware\Core\Framework\Telemetry\Metrics\Factory\MetricTransportFactoryInterface` - a factory that receives the full `TransportConfig` (containing all metric definitions) and returns a transport instance.
2. `Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface` - the transport itself, responsible for emitting a `Metric` to the monitoring backend.

Transports should support all basic metric types. If a transport does not support a specific metric type, it should throw a `Shopware\Core\Framework\Telemetry\Metrics\Exception\MetricNotSupportedException` exception.
If the exception is thrown, it will be logged, but the application will continue to work.

Here is an example of a custom transport implementation:

```php
<?php

use Shopware\Core\Framework\Telemetry\Metrics\Config\TransportConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Factory\MetricTransportFactoryInterface;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Metric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Type;
use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;
use Shopware\Core\Framework\Telemetry\TelemetryException;

class MyTransportFactory implements MetricTransportFactoryInterface
{
    public function create(TransportConfig $transportConfig): MetricTransportInterface
    {
        return new MyTransport($transportConfig);
    }
}

class MyTransport implements MetricTransportInterface
{
    public function __construct(private readonly TransportConfig $config)
    {
    }

    public function emit(Metric $metric): void
    {
        match ($metric->type) {
            Type::COUNTER => $this->handleCounter($metric),
            Type::UPDOWN_COUNTER => $this->handleUpDownCounter($metric),
            Type::HISTOGRAM => $this->handleHistogram($metric),
            Type::GAUGE => $this->handleGauge($metric),
            default => throw TelemetryException::metricNotSupported($metric, $this),
        };
    }
}
```

Once that is created, you need to register the transport factory as a service in your application and use the `shopware.metric_transport_factory` tag.

```xml
<service id="YourPackage\NameSpace\MyTransportFactory">
    <tag name="shopware.metric_transport_factory"/>
</service>
```

Real-world example of a transport can be found in the [shopware/opentelemetry](https://github.com/shopware/opentelemetry/) package.

### Usage

There are two recommended ways to emit metrics in the Shopware application. In both cases, you emit a `ConfiguredMetric` — a lightweight value object that carries only the metric name, value, and optional labels. The `Meter` resolves the full metric definition (type, description, unit) from the YAML configuration at emit time.

#### Using the Events system with an EventSubscriber

You can create an `EventSubscriberInterface` that injects the `Meter` service and emits metrics in response to domain events. The subscriber should be placed in the relevant package, as it may contain specific domain logic.

**Simple counter** (constant value):

```php
<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Telemetry;

use Shopware\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PluginTelemetrySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Meter $meter)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginPostInstallEvent::class => 'emitPluginInstallCountMetric',
        ];
    }

    public function emitPluginInstallCountMetric(): void
    {
        $this->meter->emit(new ConfiguredMetric(name: 'plugin.install.count', value: 1));
    }
}
```

**Dynamic value** derived from the event:

```php
<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Telemetry;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EntityTelemetrySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Meter $meter)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntitySearchedEvent::class => ['emitAssociationsCountMetric', 99],
        ];
    }

    public function emitAssociationsCountMetric(EntitySearchedEvent $event): void
    {
        $criteria = $event->getCriteria();
        $associationsCount = $this->getAssociationsCountFromCriteria($criteria);
        $this->meter->emit(new ConfiguredMetric(
            name: 'dal.associations.count',
            value: $associationsCount,
        ));
    }

    private function getAssociationsCountFromCriteria(Criteria $criteria): int
    {
        return array_reduce(
            $criteria->getAssociations(),
            fn (int $carry, Criteria $association) => $carry + 1 + $this->getAssociationsCountFromCriteria($association),
            0
        );
    }
}
```

#### Static Context via `MeterProvider` (Not recommended)

As a last resort, the static context can be used to emit the metric. This approach should only be employed when it is virtually impossible to inject the `Meter` service (see `RetryableQuery` and `RetryableTransaction`). It is not recommended because it makes the code harder to test and maintain, and it hooks into the global state.

```php
MeterProvider::meter()?->emit(new ConfiguredMetric(name: 'database.locks.count', value: 1));
```

### Default emitted metrics

| Metric name | Type | Emitter | Trigger |
|---|---|---|---|
| `plugin.install.count` | counter | `PluginTelemetrySubscriber` | `PluginPostInstallEvent` |
| `app.install.count` | counter | `AppTelemetrySubscriber` | `AppInstalledEvent` |
| `cache.invalidate.count` | counter | `CacheTelemetrySubscriber` | `InvalidateCacheEvent` |
| `messenger.message.size` | histogram | `MessageQueueTelemetrySubscriber` | `WorkerMessageReceivedEvent` |
| `dal.associations.count` | histogram | `EntityTelemetrySubscriber` | `EntitySearchedEvent` |
| `database.locks.count` | counter | `RetryableQuery` / `RetryableTransaction` | `RetryableException` / deadlock |

## Future improvements
- Currently, emit expects the metric value to be precalculated. That means that if some metric will be disabled, the

## ADRs
- [ADR-2024-07-30-Add-Telemetry-Abstraction-Layer](../../../../adr/2024-07-30-add-telemetry-abstraction-layer.md)
