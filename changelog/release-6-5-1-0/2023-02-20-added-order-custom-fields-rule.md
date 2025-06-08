---
title: Added Order custom fields rule
issue: NEXT-21883
---
# Core
* Added `CustomFieldRule` class in `Shopware\Core\Framework\Rule` to make a common helper for custom fields rule.
* Added `OrderCustomFieldRule` rule in `Shopware\Core\Content\Flow\Rule`.
* Changed `CustomerCustomFieldRule` rule in `Shopware\Core\Checkout\Customer\Rule` to use the static functions in `CustomFieldRule` helper.
___
# Administration
* Added `sw-condition-order-custom-field` component in `src/Administration/Resources/app/administration/src/app/component/rule/condition-type/`.
