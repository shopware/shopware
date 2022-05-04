---
title: Better profiling
issue: NEXT-20696
---
# Core
* Added static class `\Shopware\Core\Profiling\Profiler` to trace various functions.
* Added `Profiler::trace()` calls to multiple services to get better profiles.
* Added interface `\Shopware\Core\Profiling\Integration\ProfilerInterface` as abstraction for multiple profilers
* Added `\Shopware\Core\Profiling\Integration\Datadog` to integrate the Profiler with Datadog.
* Added `\Shopware\Core\Profiling\Integration\Stopwatch` to integrate the Profiler with the Symfony Debug Toolbar.
* Added `\Shopware\Core\Profiling\Integration\Tideways` to integrate the Profiler with Tideways.
* Deprecated `\Shopware\Core\Profiling\Checkout\SalesChannelContextServiceProfiler`, the service will be removed in v6.5.0.0, use the `Profiler` directly in your services.
* Deprecated `\Shopware\Core\Profiling\Entity\EntityAggregatorProfiler`, the service will be removed in v6.5.0.0, use the `Profiler` directly in your services.
* Deprecated `\Shopware\Core\Profiling\Entity\EntitySearcherProfiler`, the service will be removed in v6.5.0.0, use the `Profiler` directly in your services.
* Deprecated `\Shopware\Core\Profiling\Entity\EntityReaderProfiler`, the service will be removed in v6.5.0.0, use the `Profiler` directly in your services.
___
# Upgrade Information
## Better profiling integration
Shopware now supports better profiling for multiple integrations.
To activate profiling and a specific integration, add the corresponding integration name to the `shopware.profiler.integrations` parameter in your shopware.yaml file.
___
# Next Major Version Changes
## New Profiling pattern
Due to a new and better profiling pattern we removed the following services:
* `\Shopware\Core\Profiling\Checkout\SalesChannelContextServiceProfiler`
* `\Shopware\Core\Profiling\Entity\EntityAggregatorProfiler`
* `\Shopware\Core\Profiling\Entity\EntitySearcherProfiler`
* `\Shopware\Core\Profiling\Entity\EntityReaderProfiler`

You can now use the `Profiler::trace()` function to add custom traces directly from your services.
