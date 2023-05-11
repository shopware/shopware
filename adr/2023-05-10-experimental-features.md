---
title: Experimental features
date: 2023-05-10
area: core, administration, storefront
tags: [process, backward compatibility]
---

## Context

Currently, it is hard to publish features in an early state to gather feedback regarding those features. If they are useful, what needs to be improved etc.
One major reason is that everything we publish (that is not marked as internal) is part of our backward compatibility promise, thus changing foundational parts of features is quite hard after first release.
That leads to features being developed over quite some time without getting actual feedback from users or being able to release them, as they need to be implemented to a pretty final state in order to confidently release them in a stable manner, where we will keep backwards compatibility.

This at the same time also means that the approach is not beneficial to our ecosystem, whom the whole backwards compatibility promise should benefit, because the features are built behind close curtains they can't chime in with ideas and use cases regarding extendability, etc.

## Decision

To ship features earlier, we add the concept of "experimental" features, thus giving early access to meaningful increments of features that are still in active development.
That means in particular that there is no backward compatibility promise for experimental features, thus we can change the implementation as is needed, without having to worry about breaking changes.
We mark the code for those features with a new `experimental` annotation, to make it clear on code level that the API is **not yet** stable.
For code where already expect that it should never become part of the public API we will use the `@internal` annotation directly, to make sure that even if the feature is stable we will continue to tread those parts of the code as internal and not keep backwards compatible.
Everything that is marked with `@experimental` is designed to be part of the public API, when the feature is stable.

At the same time, it offers a way for the ecosystem to give early feedback on the feature, as well as to test it in their own projects. Especially, extension developers can check how they might want to integrate and extend the feature being built, and thus suggest the needed extension points during the development process.
To make this possible that means that there also will be documentation (API docs, dev docs and user docs) for experimental features

All experimental features are developed with a specific target version, beginning with that version, the feature is considered stable, and the APIs will be kept backwards compatible.
This means that `experimental` annotation/attribute have to be removed, before the version can be released. Because it is hard to estimate exactly with which release a feature may be stable (as it also depends on the feedback we get) it makes sense to mark them as being stable with the next major version.
That does not mean that the feature won't be finished and stable earlier (we can remove the experimental status with any minor version), it only means that at the latest with that version it is considered stable, this prevents a situation where a lot of features stay in the experimental state for a long time.

### Our experimental promise

Experimental features don't compromise in terms of quality or any other guidelines we have, that means experimental features are production ready.
While the UI and processes and functionalities of a single feature may change considerably during the experimental phase, we won't discard any data that was generated when the feature was actively used in a previous stage, meaning that even if there are changes to the underlying data, we will migrate the existing data. 
This ensures that customers using early version of the feature can continue working with that feature. 

As said earlier experimental features do not hone our backwards compatibility promise, allowing us to react more flexibly to the feedback we gather based on the earlier iterations of the feature.

### Killing a feature

It may happen that during development of a feature we get the feedback that our feature idea does not provide the value we expected, if that is the case we may kill a feature again.
If that is the case we will mark the feature as deprecated for the next major version, so even if the feature was marked as experimental and does not fall under the backwards compatible promise we will not remove a experimental feature with a minor version. We will only kill the feature for the next major version, and announce the deprecation as soon as possible.

This is also important as features can't stay in the experimental state forever, that means either they are further developed to a stable state, or they are killed to the next major version.

### How does this compare to the "old" feature flag approach?

With the old feature flag approach work in progress code was hidden with a feature flagging mechanism. That meant that code that was not production ready was in the released product, but it was turned off via flag.
Experimental features are neither work in progress, nor finished and finalized features. Whatever is included in an experimental feature is production ready and ready to use for customers, but it may mean that not all functionalities we envision for a feature are ready yet, but those that are can be used standalone.

# TBD - Do you have to opt-in to experimental features or are they always there?
First guess, say are always there because it does not add additional complexity in terms of permutations and testing etc.

## Consequences
### Core

We add a `@experimental` annotation, that can be used similar as the `@internal` annotation, to indicate parts of the code (class or method level) that are not yet stable, and thus not covered by the backwards compatibility promise.+
Additionally all `@experimental` annotation need to have a `stableVersion` property when the feature will be made available as stable at the latest, e.g. `@experimental stableVersion:v6.6.0`.
This means that at the latest with that major version the feature should be stable (or removed), however the `@experimental` annotation can always be removed earlier. As experimental features can be considered as technical debt we should strive to stabilize features as soon as possible.
When a feature can not be stabilized for the targeted major version, the experimental phase can be extended on a case by case basis.

There will be a static analysis rule / unit test, that checks that every `@experimental` annotations has the stable version propterty and there are no `@experimental` annotation for a version that is already released (similar to the test case we have for `@deprecated`).
Additionally, the BC checker needs to be adapted to handle the `@experimental` annotation in the same way as it handles `@internal`.

We use an annotation here over an attribute because of the following reasons:
* Similarity to other annotations like `@deprecated` and `@internal`
* Symfony also uses an `@experimental` annotation, see [this example](https://github.com/symfony/symfony/blob/6.3/src/Symfony/Component/Webhook/Client/AbstractRequestParser.php#LL23C5-L23C17) and their [documentation for experimental code](https://symfony.com/doc/current/contributing/code/experimental.html)
* The same annotation can be used for PHP, JS and template code
* We don't need to evaluate the annotation at runtime, so using attributes over annotations won't bring that much benefit

### Database Migrations

As said earlier data from experimental features needs to be migrated if the underlying structure changes, so that no customer data is lost.
But additionally, we also provide a blue/green compatible migration system, this means that all destructive changes to the DB layout (e.g. dropping a table or column) can only be done in a major version and can not happen immediately.
As blue/green compatibility is a overall system property we can't exclude `@experimental` features from that.

### API

API routes and also entity definitions (that automatically will be mapped to the auto-generated CRUD-API) can be marked as experimental, meaning that they are also not covered by the backwards compatibility promise.
The experimental state then will be reflected in the OpenAPI definition for those routes.

### Admin

Modules, Components, Services, etc. can be marked as experimental, meaning that they are not covered by the backwards compatibility promise.

```js
/**
 * @experimental stableVersion:v6.6.0
 */
Component.register('sw-new-component', {
    ...
}
```
### Storefront

Blocks, SCSS classes, JS plugins etc. can be marked as experimental, meaning that they are not covered by the backwards compatibility promise.

In twig blocks can be wrapped as being experimental:
```twig
{# @experimental stableVersion:v6.6.0 #}
{% block awesome-new-feature %}
   ...
{% endblock %}

```

In addition to that, we can also mark the whole template as experimental:
```twig
{# @experimental stableVersion:v6.6.0 #}
{% sw_extends '@Storefront/storefront/page/product-detail/index.html.twig' %}
```

### UI

# TBD - How do we mark experimental features in the UI? So that customers know they are still experimental

### Commercial

For commercial the same thing applies as for platform itself. There is no difference in how we handle experimental core features and experimental commercial features.

### Docs

Experimental features will be documented. This includes Dev docs, API docs and user docs. As we want to encourage the use of the features for end-users, they have to understand how the feature works under the hood.
For external developers, documentation for experimental features is also important, as they can check how they might want to integrate and extend the feature being built, and thus suggest the needed extension points during the development process.
In the docs it will also be marked that the features are experimental and that the APIs and user interface is not yet stable.

### Roadmap

The experimental status of features should also be reflected in the roadmap. That means that for a given feature, the progress in the roadmap can have a progress of 30% but already released in an experimental state. 
In that case, the version where it was made available as experimental should be shown in the roadmap.
When a feature is completed, it leaves the experimental state and all features that are displayed under "released" in the roadmap are stable.
