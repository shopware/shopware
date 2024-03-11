---
title: Add ADR for insider preview
issue: NEXT-31834
---
# Core
* Added a new static method `toggle` in `\Shopware\Core\Framework\Feature` to activate or deactivate a feature flag on demand
* Added a new service `FeatureFlagService` to register feature flags and to toggle them
* Changed the method `FeatureFlagCompilerPass::process` to register the feature flags when the container is built
* Changed the method `Framework::boot` to register the feature flags when the container is booted
* Added new profiler `\Shopware\Core\Profiling\FeatureFlag\FeatureFlagProfiler` and template in `src/Core/Profiling/Resources/views/Collector/flags.html.twig` to profile the feature flags