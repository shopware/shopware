---
title: Add ForwardCompatibility for EntityRepositoryInterface removal
issue: NEXT-25115
---
# Core
* Added `EntityRepositoryForwardCompatibilityDecorator` to improve forward compatible with the deprecation of `EntityRepositoryInterface`.
___
# Upgrade Information
## `EntityRepositoryInterface` removal forward compatible
To improve forward compatible with the `EntityRepositoryInterface` we added a `ForwardCompatibilityDecorator` to all `EntityRepositories`.
This decorator extends the `EntityRepository` and is added with negative priority, so this class is always the outermost decorator.
The decorator is empty and only delegates to the inner implementations, but by extending the `EntityRepository` it is now possible to switch the type hints from `EntityRepositoryInterface` to `EntityRepository` already in preparation for the breaking change in 6.5.0.0.
