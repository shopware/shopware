---
title: Customer Custom Field Rule evaluates wrong on multiple-selection custom fields
issue: NEXT-23252
author: Jan Emig
author_email: j.emig@one-dot.de
author_github: @Xnaff
---
# Core
* Changed method `match` in `Shopware\Core\Framework\Rule\CustomFieldRule` to fix the validation of multi select fields
* Changed `isFloat`, `getExpectedValue`, `getValue` and `floatMatch` methods in `Shopware\Core\Framework\Rule\CustomFieldRule` to public function to be able to use it in `Shopware\Core\Checkout\Cart\Rule\LineItemCustomFieldRule` to prevent duplicate code
* Changed `Shopware\Core\Checkout\Cart\Rule\LineItemCustomFieldRule` to allow the usage of multi select fields
* Added method `isArray` in `Shopware\Core\Framework\Rule\CustomFieldRule` to validate if the field value is an array from a multi select field
* Added method `arrayMatch` in `Shopware\Core\Framework\Rule\CustomFieldRule` to validate the field value against the rule value if the field value is an array from a multi select field
