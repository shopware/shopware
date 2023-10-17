---
title: Fix creating Rule for customer custom fields
issue: NEXT-30650
---
# Core
* Changed method `validateCondition` in `src/Core/Content/Rule/RuleValidator.php` to get correct mixing properties in creating rules for customer custom fields and order custom fields.
* Changed property `renderedFieldValue` to allow multi select in custom fields in these files:
    * `src/Core/Checkout/Cart/Rule/LineItemCustomFieldRule.php`
    * `src/Core/Checkout/Customer/Rule/CustomerCustomFieldRule.php`
    * `src/Core/Content/Flow/Rule/OrderCustomFieldRule.php`
    * `src/Core/Framework/Rule/CustomFieldRule.php`
  