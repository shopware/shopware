---
title: Move comparison methods to comparison classes
issue: NEXT-23252
---
# Core
* Added new method `compare` in `src/Core/Framework/Util/FloatComparator.php`
* Added new class `src/Core/Framework/Util/ArrayComparator.php`
* Deprecated two methods `floatMatch` and `arrayMatch` in `src/Core/Framework/Rule/CustomFieldRule.php`
___
# Upgrade Information
## Deprecation of methods floatMatch and arrayMatch in CustomFieldRule
### Before

```php
CustomFieldRule::floatMatch($operator, $floatA, $floatB)
CustomFieldRule::arrayMatch($operator, $arrayA, $arrayB)
```
### After
We introduced new `compare` method in `FloatComparator` and `ArrayComparator` classes.
```php
FloatComparator::compare($floatA, $floatB, $operator)
ArrayComparator::compare($arrayA, $arrayB, $operator)
```
___
# Next Major Version Changes
## Deprecated comparison methods:
* `floatMatch` and `arrayMatch` methods in `src/Core/Framework/Rule/CustomFieldRule.php` will be removed for Shopware 6.7.0.0
