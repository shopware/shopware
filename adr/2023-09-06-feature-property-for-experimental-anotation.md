---
title: Add Feature property to `@experimental` annotation
date: 2023-09-06
area: core, administration, storefront
tags: [process, backwards compatibility]
---

## Context
Our current development process uses ['Experimental features'](./2023-05-10-experimental-features.md) to publish features in an early state to gather feedback regarding those features.
During the implementation, developers may encounter challenges related to the effective management of extensive code scattered throughout the platform, particularly in connection with specific experimental features. This codebase fragmentation presents impediments to the tracking, maintenance, and comprehensive understanding of each feature's scope, thereby hindering our development progress.
 
Potential problems:
* Update `stableVersion` property for Prolonged Experiments
    * When a decision is made to extend an experiment, locating all relevant sections of code for updating the property `stableVersion` in `@experimental` annotation becomes a cumbersome task.
* Deprecation of Killed Features
    * Identifying and marking as deprecated the components associated with a deprecated experimental feature is problematic, particularly when multiple experimental features coexist simultaneously within the platform.
    * The ['Experimental features'](./2023-05-10-experimental-features.md) stipulates the "Killing Feature" rule, which mandates that a feature must remain within the platform's codebase until the next major version and be appropriately marked as deprecated. However, it is hardly possible to check with current annotation.

In all the above case main problem is detection to which feature belongs experimental code.

## Decision
To address the existing challenges, we propose implementing a refined approach to the use of the `@experimental` annotation. 

The key modifications are as follows:
* Mandatory `feature` property: 
  * Every `@experimental` annotation will now require a mandatory `feature` property. This property is a string that must contain the name of the associated feature.
* Uniform feature Naming: 
  * To enhance code organization and traceability, all sections of code related to a particular feature must use the same feature name in the `feature` property of the `@experimental` annotation.
  * Feature names should follow the conventions.
    * Feature names cannot contain spaces
    * Feature names should be written in `ALL_CAPS`.

## Consequences
### Core
Implementation of the new `feature` property for the `@experimental` annotation will require the following changes:
* To `@experimental` annotation should be added required string property `feature`. The value of the features should follow the conventions.
* There will be implemented a static analysis rule / unit test, that checks that every `@experimental` annotation has the `feature` property.

Examples of usage:
php
```php
/**
 * @experimental stableVersion:v6.6.0 feature:WISHLIST
 */
class testClass()
{
    //...
}
```
js
```js
/**
 * @experimental stableVersion:v6.6.0 feature:WISHLIST
 */
Component.register('sw-new-component', {
    ...
});
```

In twig blocks can be wrapped as being experimental:
```twig
{# @experimental stableVersion:v6.6.0 feature:WISHLIST #}
{% block awesome_new_feature %}
   ...
{% endblock %}

```

In addition to that, we can also mark the whole template as experimental:
```twig
{# @experimental stableVersion:v6.6.0 feature:WISHLIST #}
{% sw_extends '@Storefront/storefront/page/product-detail/index.html.twig' %}
```

## Combining `@experimental` annotation and `feature flag` 

Despite that, the `@experimental` annotation and the `feature flag` are two different concepts.  The `@experimental` annotation is used to mark code as experimental and influential only on BC promises regarding this code, while the `feature flag` is used to control the visibility of the experimental code.
There might be scenarios where introducing a feature flag (akin to a switch) becomes necessary, for example, in integration points. 'Experimental features' ADR doesn't explicitly prohibit this practice and does not regulate it in any way. Simultaneously, it would be beneficial to ensure a clear linkage between the feature flag and the experimental functionality it enables.

To achieve this linkage, we recommend the following:
1. Ensure that the feature flag's name matches the name used in the @experimental annotation's `feature` property.
2. The description field in the feature flag configuration should include the experimental annotation along with all the required properties, namely 'stableVersion' and 'feature'.  

Example:

feature.yaml
```yaml
shopware:
  feature:
    flags:
      - name: WISHLIST
        default: false
        major: true
        description: "experimental stableVersion:v6.6.0 feature:WISHLIST"
```
New experimental class
```php
/**
 * @experimental stableVersion:v6.6.0 feature:WISHLIST
 */
class Foo
{
}
```
Connection point
```php
if (Feature.isActive('WISHLIST') {
        $obj = new Foo();
        // New implementation
} else {
        // Old/current implementation
}
```
