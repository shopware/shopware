---
title: Handling feature flags
date: 2020-08-12
area: core
tags: [feature-flag, workflow, major]
---

## Superseded by [Feature flags for major versions](../2022-01-20-feature-flags-for-major-versions.md)

## Context

We need a decent definition how we should capsulate feature changes with a feature flag. The Goal for this adr is to have a final definition for all common cases.
This adr should be used as a reference to implement a feature and get sure a MR can be merged and takes account to any of the defined points here.

# What has to be behind a flag
* Everything that can be reached from external calls (routes, api definitions, schema, etc) needs to be hidden behind the flag. 
* Code, which is changed or introduced by the new feature, should **not** be executed.
* Everything that is not possible to hide (new constants, new classes) needs to be annotated

## Detailed cases
### New Entity Definitions
New Entity Definitions have to be hidden behind the flag in the container.
### New Services and other classes (subscriber, event, resolver)
New Services have to be hidden behind the flag in the container.
### Changes in current classes
Changes inside current classes should be conditioned with the flag.
### Additions to current classes
Access to new constants or public functions cannot be prevented by the feature flag system. In this case you have to annotate the not available part with an *@internal (flag:FEATURE_NEXT_1128)* comment
```php
//@internal (flag:FEATURE_NEXT_1128)
const NEW_FEATURE_CONST = true;
```
### New Routes
New Routes have to return the *NotFoundHttpException* if the flag is not active.

### Deprecations
Don't annotate planned deprecations with the official ```@deprecated``` annotation, to avoid making deprecations public before the feature is released. (Everything behind a feature flag is unstable and can be removed and changed at any time).
Instead, use the annotation ```@feature-deprecated (flag:FEATURE_NEXT_1128)```.
This annotation will be replaced with the real ```@deprecated``` annotation when the feature flag will be removed.
