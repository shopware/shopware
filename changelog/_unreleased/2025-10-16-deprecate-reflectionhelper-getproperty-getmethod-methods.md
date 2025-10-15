---
title: Deprecate `ReflectionHelper::{getProperty,getMethod}` methods
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Deprecated `\Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper::{getProperty,getMethod}` methods
___
# Upgrade Information
## Deprecated `\Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper::{getProperty,getMethod}` methods

As the accessible modifications are not needed anymore for reflection properties and methods since PHP 8.1, the `Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper::{getProperty,getMethod}` methods are an overhead, which they should be replaced by their native implementations, i.e. `\ReflectionMethod('My\Class\Name', 'myMethod')` and `\ReflectionProperty('My\Class\Name', 'myMethod')`.
___
# Next Major Version Changes
## Removed `\Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper::{getProperty,getMethod}` methods

As the accessible modifications are not needed anymore for reflection properties and methods since PHP 8.1, the `Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper::{getProperty,getMethod}` methods are an overhead, and therefore are removed. They should be replaced by their native implementations, i.e. `\ReflectionMethod('My\Class\Name', 'myMethod')` and `\ReflectionProperty('My\Class\Name', 'myMethod')`:
```diff
- $property = ReflectionHelper->getProperty(MyClass::class, 'myProperty');
+ $property = \ReflectionProperty(MyClass::class, 'myProperty');
- $method = ReflectionHelper->getMethod(MyClass::class, 'myMethod');
+ $method = \ReflectionMethod(MyClass::class, 'myMethod');
```
