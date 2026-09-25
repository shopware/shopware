---
title: Remove deprecated services with major feature flags
date: 2026-09-25
area: framework
tags: [dependency-injection, deprecation, feature-flag, phpstan]
---

## Context

Major feature flags let us test removals before a major release. A deprecated PHP class or service definition can still be registered in the compiled container when its removal flag is active, so the container continues to expose a service that should already be gone in that mode.

PHP service configuration is loaded before the feature registry is initialized. Calling `Feature::isActive()` in `services.php` therefore cannot decide whether to register a service. The existing `shopware.feature` tag solves the opposite case: it removes a new service while its flag is inactive.

Moved classes can also have a deprecated Symfony service alias for their previous class name. That service alias is distinct from the PHP class alias maintained by `ClassAliasRegistry`, and Symfony service aliases cannot carry tags. Symfony's `->deprecate()` records when an alias became deprecated; its version argument does not identify the major flag that removes it.

## Decision

The `FeatureFlagCompilerPass` evaluates service availability during container compilation, after registering the flags from the container parameter. A service scheduled for removal gets a `shopware.inactiveFeature` tag with its major flag. The compiler pass removes that definition when the flag is active. `shopware.feature` keeps its existing inverse meaning for services introduced by a flag. If a future removal flag has not been registered yet, the deprecated service remains available.

For deprecated Symfony service aliases, `FeatureFlagCompilerPass::ALIASES_TO_REMOVE` lists alias IDs under their removal flag. The compiler pass removes those aliases when the flag is active, without removing their target services or changing the PHP class alias registry. An adjacent `// @deprecated tag:vX.Y.Z` comment identifies the removal version in `services.php`; `->deprecate()` continues to provide Symfony's deprecation notice.

PHPStan checks the convention in both places: a service whose class has a versioned `@deprecated` annotation needs the matching `shopware.inactiveFeature` tag, and a deprecated `$services->set()` registration needs that tag even if only its service configuration is deprecated. An annotated `$services->alias()` registration must appear under the matching flag in `ALIASES_TO_REMOVE`.

Removing a service is a decision made when the container is compiled, but the feature flag can have a different value when code runs, for example when a test changes it without rebuilding the container. The PHP class also remains loadable and can be instantiated or called without obtaining it from the container. The existing `Feature::triggerDeprecationOrThrow()` checks on deprecated class methods therefore remain necessary for direct use: they check the current flag and throw after activation regardless of how the class is reached. Before activation, they emit a deprecation warning only when deprecation emission is enabled. Disabling deprecation warnings in production must not disable the major-mode throw.

## Consequences

The service and alias list reflects the flag at compilation time. If the container was built with the flag inactive, turning it on at runtime leaves deprecated services available until a rebuild. If it was built with the flag active, turning it off at runtime cannot restore removed services or aliases; a rebuild is required before the old dependency graph can be used again.

The feature-active container lets tests verify that deprecated service definitions and aliases are absent, that the replacement service graph still compiles, and that callers no longer require removed services through constructor arguments, decorators, service lookups, or tagged-service discovery. A tagged console command whose service is removed also disappears from Symfony's command map; invoking its old name fails with a command-not-found error even while the command class remains in the codebase.

Routes have a separate lifecycle. Shopware imports controller routes from PHP attributes without checking whether the controller is registered as a service. Removing a controller service therefore leaves its route matchable. On a request, Symfony first looks for the controller in the container and then tries to instantiate the class itself: a controller with required constructor arguments fails during resolution instead of returning a 404, while one without required arguments may still execute. When a deprecated controller service is removed, its controller file must also be excluded from the broad attribute route import and loaded conditionally while the removal flag is inactive. For example, for a hypothetical `LegacyController` in `src/Core/Framework/Resources/config/routes.php`, with `Feature` imported:

```php
$legacyController = '../../Api/Controller/LegacyController.php';
$routes->import('../../Api/Controller/**/*Controller.php', 'attribute', exclude: $legacyController);

if (!Feature::isActive('v6.8.0.0')) {
    $routes->import($legacyController, 'attribute');
}
```

The route collection is cached, so a flag change requires rebuilding it. Tests must check that the route is absent when the flag is active. A Symfony route condition can prevent a match, but the route remains in the collection and can still be used for URL generation. `Feature::triggerDeprecationOrThrow()` still throws when an active route calls it, but it does not remove the route from the collection.

Adding a deprecated service now requires a removal tag; adding a deprecated service alias requires an annotation and an entry in the removal list. Code that depends on a removed service must also account for its absence when the flag is active. The explicit alias list needs maintenance until those aliases are permanently removed in the major release.
