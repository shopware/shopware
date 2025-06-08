---
title: Telemetry abstraction layer
date: 2024-07-30
area: core
tags: [core, profile, performance, datadog, metrics, monitoring]
---
## Context

Observability is a key aspect of modern software development. It is essential to have the right tools in place to monitor and analyze runtime statistics of the application.

Many tools and backends are available to enable telemetry and monitoring. The context of this ADR is to provide a streamlined and simple way to enable the integration of any observability tool into the Shopware platform.

## Decision

To address the need for a unified way to track metrics and performance data, we will introduce a telemetry abstraction layer. This layer will provide a common interface for integrating different monitoring tools into the Shopware platform.

The telemetry abstraction layer will consist of the following components:

### Shopware's abstraction layer

The abstraction layer will provide a common interface for telemetry integration. It will define the methods and data structures required to send telemetry data to the monitoring backend.

### Events subsystem attachment

The telemetry abstraction layer will be integrated with the existing events subsystem. This integration will allow developers to hook into specific events and capture telemetry data related to those events.

### Transport layer (integrations)

Vendor specific implementation will not be part of the core. Those would be shipped as external libraries that implement the telemetry abstraction layer specification. The core will provide documentation on how to integrate these libraries into the Shopware platform.

Each transport layer should at least be aware of the following metrics objects:
- `Shopware\Core\Framework\Telemetry\Metrics\Metric\Counter`
- `Shopware\Core\Framework\Telemetry\Metrics\Metric\Gauge`
- `Shopware\Core\Framework\Telemetry\Metrics\Metric\Histogram`
- `Shopware\Core\Framework\Telemetry\Metrics\Metric\UpDownCounter`

Or more generally, should aim to cover all the metric types defined inside the `Shopware\Core\Framework\Telemetry\Metrics\Metric` namespace.

### Implementation and Considerations

Each transport should implement the `MetricTransportInterface`. This interface defines a method `emit` that takes a `MetricInterface` object as an argument. The `MetricInterface` object represents a single metric that needs to be sent to the monitoring backend.

If an instance of an unsupported metric type is passed to the transport, it should throw a `MetricNotSupportedException`. This ensures that the transport layer is decoupled from the core and can be extended to support new metric types in the future.

> `MetricNotSupportedException` is gracefully handled, and the application will skip over the unsupported metric type. 

```php
interface MetricTransportInterface
{
    /**
     * @throws MetricNotSupportedException
     */
    public function emit(MetricInterface $metric): void;}
```

The `MetricInterface` is a generic empty interface. This approach provides flexibility for different monitoring tools to define their own metric structures alongside the core ones.

```php
interface MetricInterface
{
}
```


## Consequences

By implementing a telemetry abstraction layer, we provide a unified way to integrate monitoring tools into the Shopware platform. This approach simplifies the process of adding telemetry to the application and ensures consistency across different monitoring tools.


## Usage

See [README.md](../src/Core/Framework/Telemetry/README.md) for the implementation and usage details.
