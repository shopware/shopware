---
title: Deprecate `ReflectionHelper::{getProperty,getMethod}` methods
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Deprecated the `\Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper`
___
# Upgrade Information
## Deprecated the `\Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper`

As the accessible modifications are not needed anymore for reflection properties and methods since PHP 8.1, the `Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper::{getProperty,getMethod}` methods are an overhead, which they should be replaced by their native implementations, i.e. `\ReflectionMethod('My\Class\Name', 'myMethod')` and `\ReflectionProperty('My\Class\Name', 'myMethod')`.

Also the `Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper::getPropertyValue` method should be replaced by `\ReflectionProperty('My\Class\Name', 'myProperty')->getValue($object)`. And the `Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper::getFileName` method should be replaced by `\ReflectionClass('My\Class\Name')->getFileName()`.

___
# Next Major Version Changes
## Removed the `\Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper`

As the accessible modifications are not needed anymore for reflection properties and methods since PHP 8.1, the `Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper::{getProperty,getMethod}` methods are an overhead, and therefore were removed. They need to be replaced by their native implementations, i.e. `\ReflectionMethod('My\Class\Name', 'myMethod')` and `\ReflectionProperty('My\Class\Name', 'myMethod')`.

Also the `Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper::getPropertyValue` method need to be replaced by `\ReflectionProperty('My\Class\Name', 'myProperty')->getValue($object)`. And the `Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper::getFileName` method needs to be replaced by `\ReflectionClass('My\Class\Name')->getFileName()`.

```diff
- $property = ReflectionHelper->getProperty(MyClass::class, 'myProperty');
+ $property = \ReflectionProperty(MyClass::class, 'myProperty');
```

```diff
- $method = ReflectionHelper->getMethod(MyClass::class, 'myMethod');
+ $method = \ReflectionMethod(MyClass::class, 'myMethod');
```

```diff
- $propertyValue = ReflectionHelper->getPropertyValue($object, 'myProperty');
+ $propertyValue = \ReflectionProperty(MyClass::class, 'myProperty')->getValue($object);
```

```diff
- $fileName = ReflectionHelper->getFileName(MyClass::class);
+ $fileName = \ReflectionClass(MyClass::class)->getFileName();
```
