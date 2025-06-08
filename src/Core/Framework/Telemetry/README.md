# Telemetry
This component contains the code for the collection of telemetry in shopware applications.

Folder structure:
- `Metrics` - contains the abstractions for the metrics collection and reporting.

## Metrics

### Supported Metric types
In the Shopware application, various types of metrics can be collected:

- **Counter** - Represents a single numerical value that only increases and can be summed, such as the number of requests served, tasks completed, or errors. This metric is reported as a positive increment.

- **UpDownCounter** - Represents a single numerical value that can increase or decrease, such as the number of concurrently running tasks. This metric is reported as either a positive or negative increment

- **Gauge** - Represents a single numerical value, such as current memory usage or the number of active threads. This metric is reported as a current value.

- **Histogram** - Samples observations (typically things like request durations or response sizes) and counts them in predefined buckets. Each bucket represents a range of values, and the histogram tracks the number of observations that fall into each bucket. This allows for a detailed distribution analysis of the observed values.

For more details see the [OpenTelemetry Metrics API specification](https://opentelemetry.io/docs/specs/otel/metrics/api/#meter-operations) or 
[Prometheus documentation](https://prometheus.io/docs/tutorials/understanding_metric_types/).

### Transports

The telemetry component provides a way to emit metrics to different transports. The transport is responsible for sending the metrics to the monitoring backend. Each transport is created as an external library that implements the telemetry abstraction layer specification.

#### Creating a Transport

To create a new transport, you need to implement the `Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface` interface.

Transports should support all basic metric types. If a transport does not support a specific metric type, it should throw a `Shopware\Core\Framework\Telemetry\Metrics\Exception\MetricNotSupportedException` exception.
If the exception is thrown, it will be logged, but the application will continue to work.

Here is an example of a custom transport implementation:

```php
<?php

use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;use Shopware\Core\Framework\Telemetry\TelemetryException;

class MetricTransportImplementation implements MetricTransportInterface
{
    public function emit(MetricInterface $metric): void
    {
        match (true) {
            $metric instanceof UpDownCounter => $this->handleUpDownCounter($metric),
            $metric instanceof Counter => $this->handleCounter($metric),
            $metric instanceof Histogram => $this->handleHistogram($metric),
            $metric instanceof Gauge => $this->handleGauge($metric),
            default => throw TelemetryException::metricNotSupported($metric, $this),
        };
    }
}
```

Once that is created, you need to register the transport as service in your application and use the `metric_transport` tag.

```xml
<service id="YourPackage/NameSpace/MetricTransportImplementation">
    <tag name="shopware.metric_transport"/>
</service>
```

Real-world example of transport can be found in the http://github.com/shopware/opentelemetry/ package.

### Usage

There are two recommended ways to emit metrics in the Shopware application:

#### Using the Events system


##### Attaching to Events
You can attach metrics to the events using the predefined attributes from the `Shopware\Core\Framework\Telemetry\Metrics\Attribute` namespace.
This is the preferred way, that would work if the value for the metric is constant or can be directly retrieved from the event property.

```php

#[\Shopware\Core\Framework\Telemetry\Metrics\Attribute\Counter(name="my_metric", value: 1, description: "My metric description")]
class MyEvent
{
}
```

For more dynamic scenarios where the event value is not constant, the `TYPE_DYNAMIC` attribute type can be used to derive the value from the event. In such case value can be either property or a callable that yields one. It is recommended to use this approach when the property or method already exists in the event. If additional data is needed solely for the metric, it is better to use the EventListener approach.

```php
use Shopware\Core\Framework\Telemetry\Metrics\Attribute\Counter;

#[Counter(name="my_metric", type: Counter::TYPE_DYNAMIC, value: "myMetricValue", description: "My metric description")]
class MyEvent
{
    public AtomicType(int|float)|Closure $myMetricValue;

    # or a method with no arguments
    public function myMetricValue(): int|float {}
}
```

> If both a property and a method with the same name exist, the `attributeValue` will prioritize the property over the method.


#### Direct usage of the `Meter` service

##### Dependency Injection
You can inject the `Meter` service into any service that needs to emit metrics.

For example, if the metric is the result of an event but the metric value cannot be directly obtained from the event property, an event listener can be used. The listener should be placed in the relevant package, as it may contain specific domain logic.
 
```php
<?php declare(strict_types=1);

namespace Shopware\Package\Subscriber\Statistics;

final readonly class SomeEventListener {

    public function __construct(private Meter $meter) {}

    public function onEvent(SomeEvent $event): void {
        $this->meter->emit(new Counter(
            name: 'some.event',
            description: 'Number of some events',
            value: $this->getValueFromEvent($event),
        ));
    }
    
    private function getValueFromEvent(MyEvent $event) {
        // calculate value based on event
    }
}
```

##### Static Context (Not recommended)

As a last resort, the static context can be used to emit the metric. This approach should only be employed when it is virtually impossible to inject the service (see RetryableTransaction). It is not recommended because it makes the code harder to test and maintain, and it hooks into the global state.
```php
\Shopware\Core\Framework\Telemetry\Metrics\MeterProvider::meter()->emit(new \Shopware\Core\Framework\Telemetry\Metrics\Metric\Counter(name: 'my_metric', value: 1,  description:  'My metric description'));
```

## Customization

> Intentionally left vague, this is largely @todo and is relevant when the feature is no longer @internal

This part is intended for developers who want to customize the telemetry component. It includes:

- Adding the ability to support a new metric type
- Customizing the type of metrics supported in the Events system (e.g., adding support for new metric types)



## Decisions/Considerations

- The metric attributes are not supported in the current implementation. They have to be added with introducing
  configuration with a whitelist for metrics, labels and labels values (to be able to manage the cardinality and related
  costs).
- The **Histogram** metric does not have explicit support for buckets (like `public ?array $buckets = null`). The reason
  for this is that possibly this will be added to configuration on the later stage, probably at the transport level, to
  make it possible to configure the buckets depending on the shopware installation.  
  Additionally, OpenTelemetry SDK requires this to be configured during meter initialization, so doing this during
  the emit phase could impact the performance.

## Future improvements
- Currently, emit expects the metric value to be precalculated. That means that if some metric will be disabled, the
  calculation will still be performed. This can be tackled in the next versions of the implementation.
- Possibility to configure metrics, labels and labels values in the configuration both globally and on the transport
  level should be added. Unsupported label values should be ignored, with possible replacement to the `other` label
  value. This will allow to manage the cardinality and related costs.

## ADRs
- [ADR-2024-07-30-Add-Telemetry-Abstraction-Layer](../../../../adr/2024-07-30-add-telemetry-abstraction-layer.md)
