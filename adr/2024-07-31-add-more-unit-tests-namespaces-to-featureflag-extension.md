---
title: Add more unit tests namespaces to FeatureFlag extension
date: 2024-07-31
area: core
tags: [ core, plugin, test, unit, major ]
---

## Context

The `Shopware\Core\Test\PHPUnit\Extension\FeatureFlag\Subscriber\TestPreparationStartedSubscriber` only allows
the `Shopware\Tests\Unit` namespace to be considered when enabling the major feature in the unit tests suite.

## Decision

To be able to unit test the upcoming major feature in other plugins we will enable the possibility to add other
namespaces to
the `Shopware\Core\Test\PHPUnit\Extension\FeatureFlag\Subscriber\TestPreparationStartedSubscriber`.

We'll add a static method called `addNamespace()` method to the
`Shopware\Core\Test\PHPUnit\Extension\FeatureFlag\FeatureFlagExtension` and by this we are able to add other namespaces
to the allowlist of the namespaces to be considered when enabling the major flags in the unit test suite.

This can be useful for plugins that wants to enable the major flags in their unit tests suite.

Therefore, add the extension to the extension list in the `phpunit.xml`:

```xml

<extensions>
    ...
    <bootstrap class="Shopware\Core\Test\PHPUnit\Extension\FeatureFlag\FeatureFlagExtension"/>
</extensions>
```

And register your test namespace in your test bootstrap file:

```php
FeatureFlagExtension::addTestNamespace('Your\\Unit\\Tests\\Namespace\\');
```

For example, in the Commercial plugin, we added the following code to the `tests/TestBootstrap.php` file:

```php
FeatureFlagExtension::addTestNamespace('Shopware\\Commercial\\Tests\\Unit\\');
```

## Consequences

If your namespace will be added via the `FeatureFlagExtension::addNamespace()` method, the major flags will be enabled
by default in your unit tests suite, and you have to explicitly disable the feature you don't want to be executed with
the upcoming major flag turned on. If you want to know the decision why all feature flags are activated by default,
please read [this ADR](https://github.com/shopware/shopware/blob/trunk/adr/2022-10-20-deprecation-handling-during-phpunit-test-execution.md#L15-L14). 
