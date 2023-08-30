---
title: Experimental features
date: 2023-05-10
area: core, administration, storefront
tags: [process, backwards compatibility]
---

## Context

Currently, it is hard to publish features in an early state in order to gather feedback about them, i.e: if they are useful, what needs to be improved, etc.
One major reason for this is that everything we publish (which has not been marked as internal), is part of our backwards compatibility promise, thus changing foundational parts of features is quite hard after first release.
This restriction leads to features being developed over quite some time without getting actual feedback from users or being able to release them; they need to be implemented in a relatively 'final' state in order to confidently release them in a stable manner, due to backwards compatibility.

At the same time this also means that our current approach is not beneficial to our ecosystem, for whom the whole backwards compatibility promise should benefit. Because features are being built behind closed curtains they can't chime in with ideas and use cases regarding extensibility, etc.

Examples of features that could benefit from an earlier, experimental release:
* B2B:
  * We could release the multi account B2B feature with a "simple" employee management system first, and then add the more complex budget management or access control on top of that later.
* Advanced Search:
  * We could release a first version of the new advanced search feature, without all configuration options and customizability that we might envision.

In both cases, releasing the first increments of the features without an "experimental"-flag would mean that we would have to keep backwards compatibility for the whole feature, even if we later decide to change the implementation of the feature, thus making the development of the feature harder.
Also in getting feedback from the customers what additional functionalities are needed after we released the first foundational increment of the feature, we can base our further prioritization on real customer feedback.
Thus, we can ship business value sooner to our customers and lower the risk of building the wrong thing.

## Decision

In order to ship features earlier, we are adding the concept of "experimental" features, thus giving early access to meaningful increments of features that are still in active development.
Specifically, this means that there is no backwards compatibility promise for experimental features, thus we can change the implementation as is needed, without having to worry about breaking changes.
We will mark the code for those features with a new `experimental` annotation, to make it clear at code-level that the API it implements is **not yet** stable.
For code where we anticipate that it will never become part of the public Shopware API we will use the `@internal` annotation immediately, to ensure that even if the feature is stable we will continue to treat those parts of the code as internal and not maintain backwards compatibility there.
Everything that is marked with `@experimental` is designed to be part of the public API, when the feature is stable.

At the same time, experimental features offers a way for the ecosystem to give early feedback on the feature, as well as to test it in their own projects. More specifically, extension developers can check how they might want to integrate and extend the feature being built, and thus allow them to suggest required extension points during the features' development process.
To better facilitate this process there also will be documentation (API docs, dev docs and user docs) for experimental features.

All experimental features are developed with a specific target version: beginning with that version, the feature is considered stable, and the APIs will be kept backwards compatible.
This means that `experimental` annotation/attribute have to be removed, before the version (declared in the experimental feature) can be released. Because it is hard to estimate during which release cycle a feature may be stable (as it also depends on the feedback we get), it makes sense to mark them as being stable with the next major version.
This practise does not mean that the feature won't be finished and stable earlier (we can remove the experimental status with any minor version), it only means that (at the latest) with the stated version release it is considered stable. This prevents a situation where a lot of features stay in the experimental state for a long time.

### Our experimental promise

Experimental features don't compromise in terms of quality or any other guidelines we have, that means experimental features are production ready.
While the UI and processes and functionalities of a single feature may change considerably during the experimental phase, we won't discard any data that was generated when the feature was in active use in a previous stage, meaning that even if there are changes to the underlying data, we will migrate the existing data. 
This ensures that customers using an early version of the feature can continue working with that feature. 

As said earlier experimental features do not honor our backwards compatibility promise, allowing us to react more flexibly to the feedback we gather based on the earlier iterations of the feature.

### Killing a feature

It may happen that during development of a feature we get feedback that our feature idea does not provide the value we expected: if this is the case we may kill a feature under development.
In these circumstances, we will mark the feature as deprecated for the next major version - even if the feature was marked as experimental and does not fall under our backwards compatibility promise we will not remove a experimental feature with a minor version. We will only kill the feature at the next major version, and will announce the deprecation as soon as possible.

As features can't stay in the experimental state forever, this practise also means that either features are further developed to a stable state, or they are killed at the next major version.

### How does this compare to the "old" feature flag approach?

With the old feature flag approach, "work-in-progress" code was hidden with a feature flagging mechanism. That meant that code that was not production ready was in the released product, but it was turned off via flag.
Experimental features are neither work in progress, nor finished and finalized features. Whatever is included in an experimental feature is production ready and ready to use for customers, but it may mean that not all functionalities we envision for a feature are ready yet, but those that are can be used as standalone.

# Do you have to opt-in to experimental features or are they always there?

From a technical perspective, experimental features are always there and they can not be deactivated. This reduces the number of permutations of the system and greatly reduces the overall complexity and thus makes testing, etc. a lot easier.
From the perspective of an external developer, this makes things also more predictable, as externals can rely on the feature being there in a given version, independent of the specific systems configuration, thus it will help in getting real feedback from the ecosystem.

From a merchants/users perspective, this might not be the case and it might be beneficial for them that some early features are opt-in only.
But as already detailed in the [UI section](#UI) this ADR does not focus on the UI and merchants perspective and leaves that open for a future ADR.
However, if the decision is made to make certain features opt-in for the user we should strive to build it in a way that only the UI of the new feature is hidden, but from the technical perspective the whole feature is always present. 
The opt-in then only makes the entry point to the new feature visible for the user.

## Consequences
### Core

We add an `@experimental` annotation (which can be used similarly to the `@internal` annotation), to indicate the parts of the code (class or method level) that are not yet stable, and are therefore exempt from the backwards compatibility promise.+
Additionally, all `@experimental` annotations need to have a `stableVersion` property which declares the latest version at which the feature will be made available as stable, e.g. `@experimental stableVersion:v6.6.0`.
This means that at the latest with that major version the feature should be stable (or removed), however the `@experimental` annotation can always be removed earlier. As experimental features can be considered as technical debt, we should strive to stabilize features as soon as possible.
When a feature can not be stabilized for the targeted major version, the experimental phase can be extended on a case by case basis.

There will be a static analysis rule / unit test, that checks that every `@experimental` annotation has the stable version property and there are no `@experimental` annotations for a version that is already released (similar to the test case we have for `@deprecated`).
Additionally, the BC checker needs to be adapted to handle the `@experimental` annotation in the same way as it handles `@internal`.

We use an annotation here over an attribute because of the following reasons:
* Similarity to other annotations like `@deprecated` and `@internal`
* Symfony also uses an `@experimental` annotation, see [this example](https://github.com/symfony/symfony/blob/6.3/src/Symfony/Component/Webhook/Client/AbstractRequestParser.php#LL23C5-L23C17) and their [documentation for experimental code](https://symfony.com/doc/current/contributing/code/experimental.html)
* The same annotation can be used for PHP, JS and template code
* We don't need to evaluate the annotation at runtime, so using attributes over annotations won't bring that much benefit

### Database Migrations

As said earlier data from experimental features needs to be migrated if the underlying structure changes, so that no customer data is lost.
Additionally, we also provide a blue/green compatible migration system, this means that all destructive changes to the DB layout (e.g. dropping a table or column) can only be done in a major version and can not happen immediately.
As blue/green compatibility is a overall system property we can't exclude `@experimental` features from that.

### API

API routes and also entity definitions (that automatically will be mapped to the auto-generated CRUD-API) can be marked as experimental, meaning that they are also not covered by the backwards compatibility promise.
The experimental state then will be reflected in the OpenAPI definition for those routes.
To declare an API route experimental:
 - Add the `Experimental` tag to the OpenApi definition of the route
 - Add a hint that that route currently still is experimental in the summary for that route
 - Use the `@experimental` annotation on the entity definition class.

### Admin

Modules, Components, Services, etc. can be marked as experimental, meaning that they are not covered by our backwards compatibility promise.

```js
/**
 * @experimental stableVersion:v6.6.0
 */
Component.register('sw-new-component', {
    ...
});
```
### Storefront

Blocks, SCSS classes, JS plugins etc. can be marked as experimental, meaning that they are not covered by our backwards compatibility promise.

In twig blocks can be wrapped, thereby marking them as experimental:
```twig
{# @experimental stableVersion:v6.6.0 #}
{% block awesome_new_feature %}
   ...
{% endblock %}

```

In addition, we can also mark the whole template as experimental:
```twig
{# @experimental stableVersion:v6.6.0 #}
{% sw_extends '@Storefront/storefront/page/product-detail/index.html.twig' %}
```

### UI

This text does not deal with how experimental features may be displayed to the merchant on a UI level.
While the concepts are overlapping, we keep them separate, therefore this ADR only answers the technical side and should enable teams who are developing features to work in a incremental and iterative way,
without being able to revisit early decisions (because they are covered by our BC promise) and without the need to use a long-lived feature branch.

As far as this ADR is concerned (with respect to UI), there is by default no way for a merchant to distinguish between experimental and stable features.
If this is deemed necessary, it can be decided individually per-feature or more generally in a separate ADR.
Such considerations should not hinder us from starting to use the `@experimental` annotation as explained here.

### Commercial

For Commercial the same thing applies as for platform itself. There is no difference in how we handle experimental core features and experimental commercial features.

### Docs

Experimental features will be documented. This includes Dev docs, API docs and user docs. As we want to encourage the use of the features for end-users, they have to understand how the feature works under the hood.
For external developers, documentation for experimental features is also important, as they can check how they might want to integrate and extend the feature being built, and thus suggest the necessary extension points during the development process.
In the docs it will also be marked that the features are experimental and that the APIs and user interface is not yet stable.

### Roadmap

The experimental status of features should also be reflected in the roadmap. That means that for a given feature, the progress in the roadmap can have a progress of 30% but already released in an experimental state. 
In that case, the version where it was made available as experimental should be shown in the roadmap.
When a feature is completed, it leaves the experimental state and all features that are displayed under "released" in the roadmap are stable.

### Automated checks

We will add the following automated checks to ensure that the `@experimental` annotation is used correctly:
* Static analysis rule / unit test, that checks that every `@experimental` annotation has the "stable version" property and there are no `@experimental` annotations for a version that is already released (similar to the test case we have for `@deprecated`).
* The BC checker will be adapted to handle the `@experimental` annotation in the same way as it handles `@internal`.
* The API schema generator will be adapted to add the `Experimental` tag to all auto-generated CRUD-routes if the entity definition is marked as experimental.
* The test that checks that all API routes have OpenApi specification also checks that the route is marked as experimental in the documentation when the route or controller method is marked as experimental.
