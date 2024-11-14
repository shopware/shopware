---
title: Metrics public interfaces
issue: NEXT-37689
---
# Core
* Changed parts of the future public interface (metrics emitting view) from @internal to @experimental:
  * `Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric`
  * `Shopware\Core\Framework\Telemetry\Metrics\Meter`
* Changed parts of the future public interface (metrics transport view) from @internal to @experimental (`Metric` and `Type` classes are public already):
  * `Shopware\Core\Framework\Telemetry\Metrics\Factory\MetricTransportFactoryInterface`
  * `Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface`
  * `Shopware\Core\Framework\Telemetry\TelemetryException` 
* Added factory method `fromArray` to `Shopware\Core\Framework\Telemetry\Metrics\Metric\Metric` (to simplify testing of transports and keep `MetricConfig` internal)
