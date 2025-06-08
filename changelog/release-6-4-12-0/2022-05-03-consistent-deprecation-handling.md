---
title: Consistent deprecation handling in core
issue: NEXT-20367
---
# Core
* Added method `triggerDeprecationOrThrow()` to `\Shopware\Core\Framework\Feature`, that should be called whenever a deprecated functionality is used.
* Deprecated method `triggerDeprecated()` of `\Shopware\Core\Framework\Feature`, the method will be removed in v6.5.0.0, use `triggerDeprecationOrThrow()` instead.
* Added new PhpStan rule `\Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\DeprecatedMethodsThrowDeprecationRule` to verify that all deprecated methods throw a deprecation notice.
___
# Next Major Version Changes
## Removal of `Feature::triggerDeprecated()`

The method `Feature::triggerDeprecated()` was removed, use `Feature::triggerDeprecationOrThrow()` instead.
