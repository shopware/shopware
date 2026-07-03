---
title: Introduce BC-change attributes for internal BC-planning markers
author: Jonas Elfering
author_email: j.elfering@shopware.com
---
# Core
* Added marker interface `Shopware\Core\Framework\Deprecation\BCChange\BCChangeAttribute` for reflection-based discovery of BC-change attributes
* Added the following PHP attributes in `Shopware\Core\Framework\Deprecation\BCChange` to document planned BC-affecting changes that are not deprecations:
  * `ReturnTypeChange`
  * `NewOptionalParameter`
  * `ParameterNameChange`
  * `ParameterTypeChange`
  * `ParameterTypeExtension`
  * `ExceptionChange`
  * `BecomesInternal`
  * `BecomesFinal`
  * `ClassHierarchyChange`
  * `VisibilityChange`
___
# Upgrade Information
## New BC-change attributes replace `@deprecated reason:*` planning markers
Shopware previously used `@deprecated tag:vX.Y.Z - reason:*` PHPDoc annotations for planned
backwards-compatibility-affecting changes that are not actual deprecations (e.g. return type
narrowing, new optional parameters). These annotations caused `Call to deprecated method`
errors in static analysis of plugin code, although no action was required and no replacement
API exists.

Such changes will now be annotated with dedicated attributes under
`Shopware\Core\Framework\Deprecation\BCChange`, which are invisible to PHPStan's deprecation
rules. The `@deprecated` annotation remains reserved for functionality that is removed or
replaced and requires migration.

Tooling can discover all BC-change markers via reflection:
```php
$reflection->getAttributes(BCChangeAttribute::class, \ReflectionAttribute::IS_INSTANCEOF);
```
