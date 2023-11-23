---
title: Deprecate autoloading associations in DAL entity definitions
date: 2023-02-02
area: core
tags: [dal, performance, api, core]
---

## Context

When using the `OneToOneAssociationField` & `ManyToOneAssociationField` associations it is possible to specify the parameter `autoload` as `true`. This causes the association to be loaded with every query, regardless of whether the data is used or not.

This is a performance issue due to unnecessary data transfer, it slows down SQL queries with extra joins, slows the application down with hydrating and data processing and finally, API payloads are larger than they need to be.

## Decision

We will deprecate the usage of autoload === true in the core codebase in version 6.5. All usages in entity definitions should be removed by the time 6.6 is released.

We have introduced a new PHPStan rule which will check for any usages of autoload === true and fail if it finds any. The failures are currently ignored in the `phpstan.neon.dist` file until they can be fixed by the respective teams.

## Migration Strategy

In order to safely migrate core code away from using autoload === true, the following steps should be followed:

1. Document all deprecations in the changelog
2. All internal APIs that rely on data that is autoloaded should now specify the association in the criteria objects.
3. All entity definitions should be updated to add the association conditionally based on the 6.6 feature flag, see below for an example.
4. In the run-up to the 6.6 release the feature conditional should be removed.


```
public function defineFields(): FieldCollection
{
   $fields = new FieldCollection(...);

  if (Feature::isActive('v6.6.0.0') {
     $fields->add(new ManyToOneAssociationField(..., autoload: false);
  } else {
     $fields->add(new ManyToOneAssociationField(..., autoload: true);
  }
}
```

## Breaking changes

For external consumers of the API who are relying on data which is autoloaded, removing the autoloaded entities will indeed be a BC break. However, this break should be minimal since it is not documented anywhere which associations are autoloaded. They would have found the data only by manually inspecting the responses.

External consumers will have to change their code to specifically request any associations they are using once 6.6 is released. They can of course specify the association, before 6.6 is released and it will continue to work as it did.
