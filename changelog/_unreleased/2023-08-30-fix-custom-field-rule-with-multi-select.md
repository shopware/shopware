---
title: Fix custom field rule with multi select
issue: NEXT-23252
author: Jan Emig
author_email: j.emig@one-dot.de
author_github: @Xnaff
---
# Core
* Changed type declaration of `$renderedFieldValue` in `Shopware\Core\Checkout\Customer\Rule\CustomerCustomFieldRule` to also use `array` as type for multi select fields 
* Changed type declaration of `$renderedFieldValue` in `Shopware\Core\Content\Flow\Rule\OrderCustomFieldRule` to also use `array` as type for multi select fields 
* Changed type declaration of `$renderedFieldValue` in `Shopware\Core\Framework\Rule\CustomFieldRule` to also use `array` as type for multi select fields 
* Changed method `match` in `Shopware\Core\Framework\Rule\CustomFieldRule` to fix the validation of multi select fields 
* Added method `isArray` in `Shopware\Core\Framework\Rule\CustomFieldRule` to validate if the field value is an array from a multi select field 
* Added method `arrayMatch` in `Shopware\Core\Framework\Rule\CustomFieldRule` to validate the field value against the rule value if the field value is an array from a multi select field 
