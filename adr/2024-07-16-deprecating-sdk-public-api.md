---
title: Deprecating Meteor Admin SDK public SDK
date: 2024-07-18
area: admin
tags: [admin, meteor admin sdk, component section]
---

## Context

Recently, the need arose to deprecate the `Meteor Admin SDK` public API built into the Shopware Core.
We need to be able to deprecate the public API, which consists of `component sections` and `data sets`.

### Component sections
The `sw-extension-component-section` component represents component sections in Shopware.
This component is added to templates, assigned a `position identifier,` and allows rendering components in place via the SDK.

```vue
<template>
    <sw-extension-component-section
        position-identifier="sw-chart-card__before"
    />
</template>
```

### Data sets
Data sets can range from whole entities to a subset of such or scalar values. These data sets are published in the component's code like so:

```javascript
createdComponent() {
    Shopware.ExtensionAPI.publishData({
        id: 'sw-dashboard-detail__todayOrderData',
        path: 'todayOrderData',
        scope: this,
    });
},
```

No mechanisms were in place to mark `component sections` and `data sets` as deprecated.
As we promise that the SDK will be our most stable extension tool, we need to ensure that this API is treated as such.

## Decision

### Monitoring
We decided to monitor the SDK's public API to gain an overview and ensure that it is not diminished by accident.
This is achieved by the `meta.spec.ts` file. The test uses committed JSON files containing all `data set ID's` and `component section - position identifiers`.
It then checks the committed file against a run-time computed list to determine if any of these were removed.

### Deprecating
Both `component sections` and `data sets` must be able to be deprecated.
For `component sections`, we added two props:

- deprecated: Boolean
- deprecationMessage: String

```vue
<template>
    {# @deprecated tag:v6.7.0 - Will be removed use position XYZ instead #}
    <sw-extension-component-section
        position-identifier="sw-chart-card__before"
        :deprecated="true"
        deprecation-message="Use position XYZ instead."
    />
</template>
```

For `data sets`, we mimicked the same in the publishing options:

- deprecated: Boolean
- deprecationMessage: String

```javascript
createdComponent() {
    /* @deprecated tag:v6.7.0 - Will be removed, use API instead */ 
    Shopware.ExtensionAPI.publishData({
        id: 'sw-dashboard-detail__todayOrderData',
        path: 'todayOrderData',
        scope: this,
        deprecated: true,
        deprecationMessage: 'No replacement available, use API instead.'
    });
},
```

#### Best practices
It is considered best practice to add a comment with the usual `@deprecated` annotation, so these parts are not missed in a major version update.

## Consequences
- Both `component sections` and `data sets` marked as deprecated will throw an error in a dev environment
- Both `component sections` and `data sets` marked as deprecated will publish a warning in a prod environment

The error or warning message will always state which extension used the deprecated `data set` or `component section` and provide the corresponding ID's:

```shell
# component section
[CORE] The extension "TestApp" uses a deprecated position identifier "foo_bar". Use position identifier "XYZ" instead.

# data set
[CORE] The extension "TestApp" uses a deprecated data set "foo_bar". No replacement available, use API instead.
```

The first sentence containing the app name and the `data set`/ `component section` ID will always be the same format.
Any information provided through the `deprecationMessage` will be appended as an addition.

## Conclusion
With all this, we assured that the public API of the Meteor Admin SDK is treated as such, but we have the possibility to properly deprecate.
