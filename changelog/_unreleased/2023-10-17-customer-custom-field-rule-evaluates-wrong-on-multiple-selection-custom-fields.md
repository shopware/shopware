---
title: Customer Custom Field Rule evaluates wrong on multiple-selection custom fields
issue: NEXT-23252
author: Jan Emig
author_email: j.emig@one-dot.de
author_github: @Xnaff
---
# Core
* Changed method `match` in `Shopware\Core\Framework\Rule\CustomFieldRule` to fix the validation of multi select fields
* Changed method `isFloat` in `Shopware\Core\Framework\Rule\CustomFieldRule` to public function to be able to use it in `Shopware\Core\Checkout\Cart\Rule\LineItemCustomFieldRule` to prevent duplicate code
* Changed method `getExpectedValue` in `Shopware\Core\Framework\Rule\CustomFieldRule` to public function to be able to use it in `Shopware\Core\Checkout\Cart\Rule\LineItemCustomFieldRule` to prevent duplicate code
* Changed method `getValue` in `Shopware\Core\Framework\Rule\CustomFieldRule` to public function to be able to use it in `Shopware\Core\Checkout\Cart\Rule\LineItemCustomFieldRule` to prevent duplicate code
* Changed method `floatMatch` in `Shopware\Core\Framework\Rule\CustomFieldRule` to public function to be able to use it in `Shopware\Core\Checkout\Cart\Rule\LineItemCustomFieldRule` to prevent duplicate code
* Changed method `getConstraints` in `Shopware\Core\Checkout\Cart\Rule\LineItemCustomFieldRule` to use same public function from `Shopware\Core\Checkout\Cart\Rule\LineItemCustomFieldRule` to prevent duplicate code
* Removed method `getRenderedFieldValueConstraints` in `Shopware\Core\Checkout\Cart\Rule\LineItemCustomFieldRule` to use same public function from `Shopware\Core\Checkout\Cart\Rule\LineItemCustomFieldRule` to prevent duplicate code
* Removed method `getValue` in `Shopware\Core\Checkout\Cart\Rule\LineItemCustomFieldRule` to use same public function from `Shopware\Core\Checkout\Cart\Rule\LineItemCustomFieldRule` to prevent duplicate code
* Removed method `getExpectedValue` in `Shopware\Core\Checkout\Cart\Rule\LineItemCustomFieldRule` to use same public function from `Shopware\Core\Checkout\Cart\Rule\LineItemCustomFieldRule` to prevent duplicate code
* Added method `isArray` in `Shopware\Core\Framework\Rule\CustomFieldRule` to validate if the field value is an array from a multi select field
* Added method `arrayMatch` in `Shopware\Core\Framework\Rule\CustomFieldRule` to validate the field value against the rule value if the field value is an array from a multi select field
