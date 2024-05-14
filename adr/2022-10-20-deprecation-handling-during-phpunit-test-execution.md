---
title: Deprecation handling during PHPUnit test execution
date: 2022-10-20
area: core
tags: [phpunit, deprecation, test]
---

## Context 

To stay innovative and on the bleeding edge of technology it is important, that we don't rely on deprecated functionality, as it prevents us from using the latest and greatest versions of libraries, that may include important bug fixes, performance improvements or new features.
Relying on deprecated functionality makes continuously upgrading the dependencies harder, as you have a lot of work to remove the deprecated usages before being able to do the upgrades.
This is not just important for external dependencies, but also for internal deprecations, that is we still rely on some deprecated functionality it makes removing the deprecation very hard.

One opportunity is to rely on our PHPUnit test suite to detect usages of deprecated functionality, so we can continuously remove them as they appear and ensure that the code base is always forward compatible.

## Solution

Because the handling for internal and external deprecations is quite different we probably need different solutions for those cases.
Especially as for internal deprecations we still want to ensure that they continue to work and that those deprecated code paths are also covered by tests.

### Using Symfony's Deprecation Helper for external deprecations

Symfony offers a tool to report all deprecations that are encountered when running the test inside their [PHPUnit Bridge](https://symfony.com/doc/current/components/phpunit_bridge.html).
With enabling the [`SYMFONY_DEPRECATIONS_HELPER`](https://symfony.com/doc/current/components/phpunit_bridge.html#trigger-deprecation-notices) for our testsuite we can ensure that no deprecations are triggered while executing the tests.
Previously we could not enable this as it also reported all deprecation usages for internal deprecations and also reported on deprecations that were triggered from inside external dependencies that we could not fix from inside shopware.

But since lately a feature was added to use a [`ignoreFile`](https://symfony.com/doc/current/components/phpunit_bridge.html#ignoring-deprecations), in order to ignore specific deprecations by regex.

We leverage this feature by using it in a way to ignore all deprecations that we can't fix immediately. Those cases especially include:
1. Ignoring all internal deprecations (as they are handled differently, see next section)
2. Ignoring all deprecations from inside external dependencies (those ignores should be commented by the package that is triggering them, so we can remove them once we are able to update the dependency that is triggering them)
3. Ignoring all deprecations that would be too much to fix immediately. E.g. if in a library update a lot of new deprecations are added (say DBAL renaming a big portion of it's public API), that would be too much work to fix immediately we can ignore those deprecations **temporarily** and create a ticket to remove those deprecations.

### Using our Feature Flag system for internal deprecations

Internally we use the feature flag system to trigger deprecation messages, or throw exceptions if the major feature flag is activated as explained in the [deprecation handling ADR](../adr/2022-02-28-consistent-deprecation-notices-in-core.md).
We already use that system in our new unit test suite with a custom `@ActiveFeatures()` annotations, that allows us to run single test cases with a specific set of feature flags.
But the current implementation has the big drawback that feature flags have to be actively enabled, this leads to following problems:
1. There are already tests that are not passing after all deprecations are removed, because they rely on deprecated behaviour.
2. We can't check automatically that our implementation is forward compatible, as the default way of executing tests is without any major flag activated.
3. It is hard to directly see which test cases are there only to cover legacy/deprecated functionality and can safely be removed after the deprecations are removed.

Therefore, the workflow is updated in the following way:
1. All unit tests get executed with all major feature flags activated.
2. The `@ActiveFeatures()` will be removed, and we introduce a `@DisableFeatures` annotation, that works in the exact opposite way => disabling all feature flags that are passed.

This has the upside that now the default behaviour of our test suite is the new/not-deprecated behaviour, and the deprecated code paths are treated as the exceptional case instead the other way around.
Additionally, all tests that are relying on deprecated behaviour are marked with the `@DisableFeatures` annotations, so it is easy to detect them and simply remove them, if the underlying deprecation was removed.
