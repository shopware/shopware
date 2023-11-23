---
title: Consistent deprecation notices in Core
date: 2022-02-09
area: core
tags: [deprecation, annotations, trigger-deprecation]
---

## Context

Currently, we use `@deprecated` annotations to warn 3rd party developers that we will introduce some breaking change in the next major version.
This annotation instructs the IDE to warn the developer that the method/class is deprecated, but has no consequences at runtime.

PHP/Symfony has also a built-in runtime deprecation mechanism with `trigger_deprecation`. This is only used sparsely in the core in `Feature::triggerDeprecated()`.

## Decision

In the future we will use both `@deprecated` and runtime deprecation notices over `trigger_deprecation`.
This means wherever a `@deprecated` annotation is used we will also throw a deprecation notice.

The deprecation notices can be thrown conditionally, e.g., when a new parameter in a method will become required, we will only throw the deprecation if the method is called in the old/deprecated way. 
If it is already used in the new way, there is no need to trigger the deprecation.

This has the benefit that 3rd party developers get deprecation notices during runtime with a concrete deprecation message and the stacktrace where the deprecation was triggered.
This is useful, e.g., to run the test suite of a plugin against a new shopware version to get a list of all deprecations.

Additionally, we can use this to provide better feedback to 3rd party developers, e.g., if App Scripts use a deprecated method/class or if some private apps in the cloud rely on deprecated functionality.

### Ensuring the correct usage during CI

To ensure that this guideline is followed, we add a step in the CI (e.g., a custom PHPStan rule or a special unit test) that checks that every method that has a `@deprecated` annotation triggers also a deprecation notice, and vice versa.

There are some special cases where we use a `@deprecated` annotation, but a according triggered deprecation notice makes no sense:
* Classes/methods marked as deprecated, because they will be considered `internal` starting with the next major version.
* Methods are deprecated because the return type will change.
For both cases we will add special keywords to the `@deprecated` annotation and our CI-check will skip those annotations.

### Common Implementation

We will add a common implementation inside the core that should be used everywhere. This makes it easier to change the deprecation handling later on in a single place and makes it possible to provide custom deprecation warnings, e.g., for app scripts inside Symfony's debug toolbar.

The new method will accept the deprecation message as string and the feature flag of the major version, where the deprecation will be removed.
The method will then trigger a deprecation notice if the major feature flag is not active. If the flag is active, it will throw an exception instead. 
This ensures that we inside the core don't rely on deprecated functionality as we have a test-pipeline where the major feature flag is set to true.

A POC implementation in the `Feature`-class can look something like this:
```php
    public static function triggerDeprecationOrThrow(string $message, string $majorFlag): void
    {
        if (self::isActive($majorFlag) || !self::has($majorFlag)) {
            throw new \RuntimeException('Deprecated Functionality: ' . $message);
        }

        trigger_deprecation('', '', $message);
    }
```
Additionally, we will deprecate the `triggerDeprecated()` method, because it will only trigger deprecation messages if the feature flag is active, but in that case the deprecated code will already be removed and the deprecation message never thrown.

### Consistent deprecation notice format

To be as useful as possible, we should use a consistent format for the deprecation messages.

Most importantly, we should ensure that the following information is present in the deprecation message:
* The name of the method/class that is deprecated
* The version in which the deprecation will be removed and the announced changes will be applied
* What to do instead to get rid of the deprecation, e.g., using another method/class or provide an additional param etc.

As an example:
* **Bad:** Will be removed, use NewFeature::method() instead
* **Good:** Method OldFeature::method() will be removed in v6.5.0.0, use NewFeature::method() instead
